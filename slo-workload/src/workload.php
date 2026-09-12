<?php

use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\SimpleSloLogger;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Ydb;

/** ids of the writer number N start at prefill_count + N * SLO_WRITER_ID_RANGE */
const SLO_WRITER_ID_RANGE = 1000000000;

/**
 * Creates the table and fills it with the initial data.
 */
function slo_create(array $config)
{
    $table = slo_connect($config, 'create')->table();
    $logger = $table->getLogger();

    $logger->info('Create table', ['tableName' => $config['table_name']]);
    $table->retrySession(function (Session $session) use ($config) {
        $session->schemeQuery(slo_create_table_query($config));
    }, true, new RetryParams($config['write_timeout']));

    $query = slo_write_query($config['table_name']);
    $table->retryTransaction(function (Session $session) use ($query, $config) {
        $prepared = $session->prepare($query);
        for ($id = 1; $id <= $config['prefill_count']; $id++) {
            $prepared->execute(slo_write_params($id));
        }
    }, false, new RetryParams($config['write_timeout']));

    $logger->info('Table created', ['rows' => $config['prefill_count']]);
}

/**
 * Drops the table.
 */
function slo_cleanup(array $config)
{
    $table = slo_connect($config, 'cleanup')->table();

    $table->retrySession(function (Session $session) use ($config) {
        $session->dropTable($config['table_name']);
    }, true, new RetryParams($config['write_timeout']));

    $table->getLogger()->info('Dropped table', ['tableName' => $config['table_name']]);
}

/**
 * Runs the workload: read and write processes do the operations, one more process
 * collects their events and pushes the metrics.
 */
function slo_run(array $config)
{
    // The queue is created before the workers are forked, see slo_queue_create().
    $queue = slo_queue_create();
    $logger = new SimpleSloLogger(SimpleSloLogger::INFO, 'run');
    $logger->info('Start workload', [
        'ref' => $config['ref'],
        'tableName' => $config['table_name'],
        'duration' => max(0, (int)round($config['run_deadline'] - microtime(true))),
        'readRps' => $config['read_rps'],
        'writeRps' => $config['write_rps'],
        'otlpEndpoint' => $config['otlp_endpoint'],
    ]);

    $workersCount = $config['read_forks'] + $config['write_forks'];

    $pids = [];
    $pids[] = slo_fork(function () use ($config, $queue, $workersCount) {
        slo_stop_on_signals();
        slo_metrics_worker($config, $queue, $workersCount);
    });
    foreach (['read', 'write'] as $operation) {
        for ($index = 0; $index < $config[$operation . '_forks']; $index++) {
            $pids[] = slo_fork(function () use ($config, $queue, $operation, $index) {
                slo_stop_on_signals();
                slo_worker($config, $queue, $operation, $index);
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
    pcntl_alarm(max(1, (int)ceil($config['kill_deadline'] - microtime(true))));

    $failed = [];
    foreach ($pids as $pid) {
        if (slo_wait($pid) != 0) {
            $failed[] = $pid;
        }
    }
    slo_queue_remove($queue);

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
 * @param string $operation `read` or `write`
 * @param int $index number of the process among the ones doing the same operation
 */
function slo_worker(array $config, $queue, $operation, $index)
{
    $table = slo_connect($config, "$operation-$index")->table();
    $timeout = $config[$operation . '_timeout'];
    $rps = $config[$operation . '_rps'];
    $forks = $config[$operation . '_forks'];

    if ($operation == 'read') {
        $query = slo_read_query($config['table_name']);
        $maxId = max(1, $config['prefill_count']);
    } else {
        $query = slo_write_query($config['table_name']);
        // Every writer gets its own range of ids, to avoid overwriting rows of the others.
        $id = $config['prefill_count'] + $index * SLO_WRITER_ID_RANGE;
    }

    $interval = $rps > 0 ? $forks / $rps : 0.0;
    $next = microtime(true);

    while (!slo_stopped() && microtime(true) < $config['run_deadline']) {
        if ($operation == 'read') {
            $params = slo_read_params(mt_rand(1, $maxId));
        } else {
            $id++;
            $params = slo_write_params($id);
        }

        slo_operation($config, $queue, $table, $operation, $timeout, function (Session $session) use ($query, $params) {
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

    slo_queue_send($queue, ['type' => 'done']);
}

/**
 * Runs one operation with retries and reports it to the metrics process.
 */
function slo_operation(array $config, $queue, Table $table, $operation, $timeout, Closure $call)
{
    $begin = microtime(true);
    $attempts = 1;

    // Retries must not outlive the shutdown deadline, or the worker gets killed in
    // the middle of an operation and the container overruns its time budget.
    $timeout = (int)max(100, min($timeout, ($config['kill_deadline'] - $begin) * 1000));

    try {
        $table->retryTransaction($call, true, new RetryParams($timeout), [
            'callback_on_error' => function (Exception $e) use (&$attempts, $queue, $operation) {
                $attempts++;
                slo_queue_send($queue, [
                    'type' => 'retried',
                    'operation' => $operation,
                    'error' => slo_error_name(get_class($e)),
                ]);
            },
        ]);
        slo_queue_send($queue, [
            'type' => 'ok',
            'operation' => $operation,
            'attempts' => $attempts,
            'latency' => microtime(true) - $begin,
        ]);
    } catch (Exception $e) {
        $table->getLogger()->error("$operation failed: " . $e->getMessage());
        slo_queue_send($queue, [
            'type' => 'err',
            'operation' => $operation,
            'attempts' => $attempts,
            'error' => slo_error_name(get_class($e)),
            'latency' => microtime(true) - $begin,
        ]);
    }
}

/**
 * Counts the events of all workers and pushes the metrics every report period.
 */
function slo_metrics_worker(array $config, $queue, $workersCount)
{
    $logger = new SimpleSloLogger(SimpleSloLogger::INFO, 'metrics');
    $metrics = slo_metrics_new($config['ref']);
    $resourceAttributes = [
        'service.name' => $config['workload_name'],
        'ref' => $config['ref'],
        'sdk' => 'php',
        'sdk_version' => Ydb::VERSION,
    ];

    $workersLeft = $workersCount;
    $lastPush = 0.0;
    $pushed = 0;
    $pushFailed = 0;
    $lastError = '';

    while (true) {
        $event = null;
        $received = slo_queue_receive($queue, $event);
        if ($received && is_array($event)) {
            if ($event['type'] == 'done') {
                $workersLeft--;
            } else {
                slo_metrics_add($metrics, $event);
            }
        }

        // Everyone has finished, the workload is being stopped, or the time is up:
        // the last push carries the final counters.
        $now = microtime(true);
        $finished = $workersLeft <= 0 || (slo_stopped() && !$received) || $now > $config['kill_deadline'];

        if ($finished || $now - $lastPush >= $config['report_period'] / 1000) {
            $lastPush = $now;
            $payload = slo_metrics_payload($metrics, $resourceAttributes, $config['start_time']);

            if ($config['otlp_endpoint'] !== '') {
                $lastError = slo_metrics_push($config['otlp_endpoint'], $payload);
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
        if (!$received) {
            usleep(1000);
        }
    }

    if ($config['otlp_endpoint'] !== '') {
        $logger->info('Metrics pushed', ['successful' => $pushed, 'failed' => $pushFailed]);
        if ($pushed == 0) {
            throw new Exception('every metrics push failed: ' . $lastError);
        }
    }
}
