<?php

namespace YdbPlatform\Ydb\Slo;

use Closure;
use Exception;
use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Ydb;

/**
 * The phases of the workload: create, run, cleanup.
 */

/** ids of the writer number N start at prefillCount + N * WRITER_ID_RANGE */
const WRITER_ID_RANGE = 1000000000;

/**
 * Creates the table and fills it with the initial data.
 */
function create(Config $config)
{
    $table = connect($config, 'create')->table();
    $logger = $table->getLogger();

    $logger->info('Create table', ['tableName' => $config->tableName]);
    $table->retrySession(function (Session $session) use ($config) {
        $session->schemeQuery(createTableQuery($config));
    }, true, new RetryParams($config->writeTimeout));

    $query = writeQuery($config->tableName);
    $table->retryTransaction(function (Session $session) use ($query, $config) {
        $prepared = $session->prepare($query);
        for ($id = 1; $id <= $config->prefillCount; $id++) {
            $prepared->execute(writeParams($id));
        }
    }, false, new RetryParams($config->writeTimeout));

    $logger->info('Table created', ['rows' => $config->prefillCount]);
}

/**
 * Drops the table.
 */
function cleanup(Config $config)
{
    $table = connect($config, 'cleanup')->table();

    $table->retrySession(function (Session $session) use ($config) {
        $session->dropTable($config->tableName);
    }, true, new RetryParams($config->writeTimeout));

    $table->getLogger()->info('Dropped table', ['tableName' => $config->tableName]);
}

/**
 * Runs the workload: read and write processes do the operations, one more process
 * counts their events and pushes the metrics.
 *
 * @throws Exception when a process failed before the shutdown deadline
 */
function run(Config $config)
{
    // The queue is created before the workers are forked, see EventQueue.
    $queue = EventQueue::create();
    $logger = new SimpleSloLogger(SimpleSloLogger::INFO, 'run');
    $logger->info('Start workload', [
        'ref' => $config->ref,
        'tableName' => $config->tableName,
        'duration' => max(0, (int)round($config->runDeadline - microtime(true))),
        'readRps' => $config->readRps,
        'writeRps' => $config->writeRps,
        'otlpEndpoint' => $config->otlpEndpoint,
    ]);

    $workersCount = $config->readForks + $config->writeForks;

    $pids = [];
    $pids[] = forkProcess(function () use ($config, $queue, $workersCount) {
        stopOnSignals();
        metricsWorker($config, $queue, $workersCount);
    });
    foreach ([Metrics::READ, Metrics::WRITE] as $operation) {
        $forks = $operation == Metrics::READ ? $config->readForks : $config->writeForks;
        for ($index = 0; $index < $forks; $index++) {
            $pids[] = forkProcess(function () use ($config, $queue, $operation, $index) {
                stopOnSignals();
                worker($config, $queue, $operation, $index);
            });
        }
    }

    // The workload itself is stopped by passing the signal to the workers.
    pcntl_async_signals(true);
    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, function ($receivedSignal) use ($pids) {
            foreach ($pids as $pid) {
                posix_kill($pid, $receivedSignal);
            }
        });
    }

    // A process stuck in an operation must not hold the container: the action gives
    // the whole container only the workload duration plus a minute.
    $killed = false;
    pcntl_signal(SIGALRM, function () use ($pids, $logger, &$killed) {
        $killed = true;
        $logger->warning('Workload processes did not finish in time, killing them');
        foreach ($pids as $pid) {
            posix_kill($pid, SIGKILL);
        }
    });
    pcntl_alarm(max(1, (int)ceil($config->killDeadline - microtime(true))));

    $failed = [];
    foreach ($pids as $pid) {
        if (waitForProcess($pid) != 0) {
            $failed[] = $pid;
        }
    }
    $queue->remove();

    if ($failed && !$killed) {
        throw new Exception('workload processes failed: ' . implode(', ', $failed));
    }
    if ($failed) {
        // Killing a worker that is in the middle of a retry is a normal end of the
        // workload, not a failure: the metrics of the whole run are already pushed.
        $logger->warning('Workload processes killed at the shutdown deadline', [
            'pids' => implode(', ', $failed),
        ]);
    }

    $logger->info('Workload finished');
}

/**
 * Reads or writes rows until the deadline, keeping the per-process share of the
 * requested RPS as an upper bound: a slower process simply runs at its own pace.
 *
 * @param string $operation Metrics::READ or Metrics::WRITE
 * @param int $index number of the process among the ones doing the same operation
 */
function worker(Config $config, EventQueue $queue, string $operation, int $index)
{
    $table = connect($config, "$operation-$index")->table();
    $reading = $operation == Metrics::READ;

    $timeout = $reading ? $config->readTimeout : $config->writeTimeout;
    $rps = $reading ? $config->readRps : $config->writeRps;
    $forks = $reading ? $config->readForks : $config->writeForks;
    $query = $reading ? readQuery($config->tableName) : writeQuery($config->tableName);

    $maxId = max(1, $config->prefillCount);
    // Every writer gets its own range of ids, to avoid overwriting rows of the others.
    $id = $config->prefillCount + $index * WRITER_ID_RANGE;

    $interval = $rps > 0 ? $forks / $rps : 0.0;
    $next = microtime(true);

    while (!stopRequested() && microtime(true) < $config->runDeadline) {
        if ($reading) {
            $params = readParams(mt_rand(1, $maxId));
        } else {
            $id++;
            $params = writeParams($id);
        }

        operation($config, $queue, $table, $operation, $timeout, function (Session $session) use ($query, $params) {
            $session->query($query, $params);
        });

        $next += $interval;
        $sleep = $next - microtime(true);
        if ($sleep > 0) {
            usleep((int)($sleep * 1e6));
        } else {
            // Falling behind the requested RPS, no reason to accumulate the debt.
            $next = microtime(true);
        }
    }

    $queue->send(Event::workerDone());
}

/**
 * Runs one operation with retries and reports it to the metrics process.
 *
 * @param string $operation Metrics::READ or Metrics::WRITE
 * @param int $timeout operation timeout in milliseconds
 * @param Closure $call the operation itself, `function (Session $session)`
 */
function operation(Config $config, EventQueue $queue, Table $table, string $operation, int $timeout, Closure $call)
{
    $begin = microtime(true);
    $attempts = 1;

    // Retries must not outlive the shutdown deadline, or the worker gets killed in
    // the middle of an operation and the container overruns its time budget.
    $timeout = (int)max(100, min($timeout, ($config->killDeadline - $begin) * 1000));

    try {
        $table->retryTransaction($call, true, new RetryParams($timeout), [
            'callback_on_error' => function (Exception $e) use (&$attempts, $queue, $operation) {
                $attempts++;
                $queue->send(Event::retried($operation, errorName(get_class($e))));
            },
        ]);
        $queue->send(Event::succeeded($operation, $attempts, microtime(true) - $begin));
    } catch (Exception $e) {
        $table->getLogger()->error("$operation failed: " . $e->getMessage());
        $queue->send(Event::failed($operation, $attempts, microtime(true) - $begin, errorName(get_class($e))));
    }
}

/**
 * Counts the events of all workers and pushes the metrics every report period.
 *
 * @param int $workersCount how many workers have to finish for the workload to end
 * @throws Exception when not a single metrics push succeeded
 */
function metricsWorker(Config $config, EventQueue $queue, int $workersCount)
{
    $logger = new SimpleSloLogger(SimpleSloLogger::INFO, 'metrics');
    $metrics = new Metrics($config->ref);
    $resourceAttributes = [
        'service.name' => $config->workloadName,
        'ref' => $config->ref,
        'sdk' => 'php',
        'sdk_version' => Ydb::VERSION,
    ];

    $workersLeft = $workersCount;
    $lastPush = 0.0;
    $pushed = 0;
    $pushFailed = 0;
    $lastError = '';

    while (true) {
        $event = $queue->receive();
        if ($event !== null) {
            if ($event->type == Event::WORKER_DONE) {
                $workersLeft--;
            } else {
                $metrics->add($event);
            }
        }

        // Everyone has finished, the workload is being stopped, or the time is up:
        // the last push carries the final counters.
        $now = microtime(true);
        $finished = $workersLeft <= 0
            || (stopRequested() && $event === null)
            || $now > $config->killDeadline;

        if ($finished || $now - $lastPush >= $config->reportPeriod / 1000) {
            $lastPush = $now;
            $payload = $metrics->payload($resourceAttributes, $config->startTime);

            if ($config->otlpEndpoint !== '') {
                $lastError = Otlp::push($config->otlpEndpoint, $payload);
                if ($lastError === '') {
                    $pushed++;
                } else {
                    $pushFailed++;
                    $logger->warning($lastError);
                }
            }
        }

        if ($finished) {
            break;
        }
        if ($event === null) {
            usleep(1000);
        }
    }

    if ($config->otlpEndpoint !== '') {
        $logger->info('Metrics pushed', ['successful' => $pushed, 'failed' => $pushFailed]);
        if ($pushed == 0) {
            throw new Exception('every metrics push failed: ' . $lastError);
        }
    }
}
