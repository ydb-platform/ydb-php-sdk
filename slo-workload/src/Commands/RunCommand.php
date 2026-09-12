<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use Closure;
use Exception;
use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\Command;
use YdbPlatform\Ydb\Slo\Config;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\EventQueue;
use YdbPlatform\Ydb\Slo\Fork;
use YdbPlatform\Ydb\Slo\Metrics\Metrics;
use YdbPlatform\Ydb\Slo\Metrics\OtlpExporter;
use YdbPlatform\Ydb\Slo\SimpleSloLogger;
use YdbPlatform\Ydb\Slo\Utils;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Ydb;

/**
 * Reads and writes rows with the requested RPS and reports the metrics over OTLP.
 *
 * PHP has no threads, so the workload is a set of forked processes: every read and
 * write worker runs its own driver, and a dedicated metrics process aggregates the
 * events all workers send over a System V message queue.
 */
class RunCommand extends Command
{
    /** @var int ids of every writer start at $prefillCount + $writerIndex * self::WRITER_ID_RANGE */
    const WRITER_ID_RANGE = 1000000000;

    public $name = "run";
    public $description = "runs the workload (reads and writes rows with the given RPS)";

    /** @var EventQueue */
    protected $queue;
    /** @var bool set by the SIGTERM/SIGINT handler */
    protected $stopped = false;

    public function execute(Config $config)
    {
        $deadline = $config->runDeadline();

        // The queue is created before the workers are forked, see EventQueue.
        $this->queue = EventQueue::create(__FILE__);

        $workersCount = $config->readForks + $config->writeForks;
        $logger = new SimpleSloLogger(SimpleSloLogger::INFO, "run");
        $logger->info("Start workload", [
            "ref" => $config->ref,
            "tableName" => $config->tableName,
            "duration" => max(0, (int)round($deadline - microtime(true))),
            "readRps" => $config->readRps,
            "writeRps" => $config->writeRps,
            "otlpEndpoint" => $config->otlpEndpoint,
        ]);

        $pids = [];
        $pids[] = $this->fork(function () use ($config, $workersCount, $deadline) {
            $this->metricsJob($config, $workersCount, $deadline);
        });
        for ($i = 0; $i < $config->readForks; $i++) {
            $index = $i;
            $pids[] = $this->fork(function () use ($config, $index, $deadline) {
                $this->readJob($config, $index, $deadline);
            });
        }
        for ($i = 0; $i < $config->writeForks; $i++) {
            $index = $i;
            $pids[] = $this->fork(function () use ($config, $index, $deadline) {
                $this->writeJob($config, $index, $deadline);
            });
        }

        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function ($receivedSignal) use ($pids) {
                foreach ($pids as $pid) {
                    posix_kill($pid, $receivedSignal);
                }
            });
        }

        // A process stuck in an operation must not hold the container: the action
        // gives the whole container only the workload duration plus a minute.
        pcntl_signal(SIGALRM, function () use ($pids, $logger) {
            $logger->error("Workload processes did not finish in time, killing them");
            foreach ($pids as $pid) {
                posix_kill($pid, SIGKILL);
            }
        });
        pcntl_alarm(max(1, (int)ceil($config->hardDeadline() - microtime(true))));

        $failed = [];
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $exitCode = Fork::exitCode($status);
            if ($exitCode != 0) {
                $failed[] = $pid;
            }
        }

        $this->queue->remove();

        if ($failed) {
            throw new Exception("workload processes failed: " . implode(", ", $failed));
        }

        $logger->info("Workload finished");
    }

    protected function fork(Closure $job): int
    {
        return Fork::start(function () use ($job) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, function () {
                    $this->stopped = true;
                });
            }

            $job();
        });
    }

    protected function readJob(Config $config, int $index, float $deadline)
    {
        $ydb = Utils::initDriver($config, "read-$index");
        $table = $ydb->table();
        $query = sprintf(Defaults::READ_QUERY, $config->tableName);
        $maxId = max(1, $config->prefillCount);

        $this->workerLoop($config->readRps, $config->readForks, $deadline, function () use ($table, $query, $config, $maxId) {
            $id = (new Uint64Type(mt_rand(1, $maxId)))->toTypedValue();

            $this->operation(Metrics::READ, $config->readTimeout, function (Session $session) use ($query, $id) {
                $session->query($query, ['$id' => $id]);
            }, $table);
        });

        $this->queue->workerDone();
    }

    protected function writeJob(Config $config, int $index, float $deadline)
    {
        $ydb = Utils::initDriver($config, "write-$index");
        $table = $ydb->table();
        $query = sprintf(Defaults::WRITE_QUERY, $config->tableName);
        $dataGenerator = new DataGenerator($config->prefillCount + $index * self::WRITER_ID_RANGE);

        $this->workerLoop($config->writeRps, $config->writeForks, $deadline, function () use ($table, $query, $config, $dataGenerator) {
            $data = $dataGenerator->getUpsertData();

            $this->operation(Metrics::WRITE, $config->writeTimeout, function (Session $session) use ($query, $data) {
                $session->query($query, $data);
            }, $table);
        });

        $this->queue->workerDone();
    }

    /**
     * Runs one operation and reports it to the metrics job.
     */
    protected function operation(string $type, int $timeoutMs, Closure $userFunc, Table $table)
    {
        $attempts = 1;
        $begin = microtime(true);
        try {
            $table->retryTransaction($userFunc, true, new RetryParams($timeoutMs), [
                'callback_on_error' => function (Exception $e) use (&$attempts, $type) {
                    $attempts++;
                    $this->queue->operationRetried($type, get_class($e));
                }
            ]);
            $this->queue->operationSucceeded($type, $attempts, microtime(true) - $begin);
        } catch (Exception $e) {
            $table->getLogger()->error($type . " failed: " . $e->getMessage());
            $this->queue->operationFailed($type, $attempts, get_class($e), microtime(true) - $begin);
        }
    }

    /**
     * Calls $operation until the deadline, keeping the per-process share of $rps
     * as an upper bound: a slower process simply runs at its own pace.
     */
    protected function workerLoop(int $rps, int $forks, float $deadline, Closure $operation)
    {
        $interval = $rps > 0 ? $forks / $rps : 0.0;
        $next = microtime(true);

        while (!$this->stopped && microtime(true) < $deadline) {
            $operation();

            $next += $interval;
            $sleep = $next - microtime(true);
            if ($sleep > 0) {
                usleep((int)($sleep * 1e6));
            } else {
                // Falling behind the requested RPS, no reason to accumulate the debt.
                $next = microtime(true);
            }
        }
    }

    /**
     * Aggregates the events of all workers and pushes the metrics over OTLP.
     */
    protected function metricsJob(Config $config, int $workersCount, float $deadline)
    {
        $logger = new SimpleSloLogger(SimpleSloLogger::INFO, "metrics");
        $metrics = new Metrics($config->ref);
        $exporter = $config->otlpEndpoint === null ? null : new OtlpExporter($config->otlpEndpoint, [
            'service.name' => $config->workloadName,
            'ref' => $config->ref,
            'sdk' => 'php',
            'sdk_version' => Ydb::VERSION,
        ], $config->startTime);

        $workersLeft = $workersCount;
        $lastPush = 0.0;
        $pushed = 0;
        $pushFailed = 0;

        // The workers are given some extra time on top of the deadline to finish.
        $hardDeadline = $config->hardDeadline();

        while (true) {
            $message = null;
            $received = $this->queue->receive($message);
            if ($received && $this->handleMessage($metrics, $message)) {
                $workersLeft--;
            }

            $now = microtime(true);
            if ($now - $lastPush >= $config->reportPeriod / 1000) {
                $lastPush = $now;
                if ($exporter) {
                    if ($exporter->export($metrics->snapshot())) {
                        $pushed++;
                    } else {
                        $pushFailed++;
                        $logger->warning($exporter->lastError());
                    }
                } else {
                    $metrics->snapshot();
                }
            }

            if ($workersLeft <= 0 || ($this->stopped && !$received) || $now > $hardDeadline) {
                break;
            }

            if (!$received) {
                usleep(1000);
            }
        }

        if ($exporter) {
            // The last push carries the final counters.
            if ($exporter->export($metrics->snapshot())) {
                $pushed++;
            } else {
                $pushFailed++;
                $logger->warning($exporter->lastError());
            }

            $logger->info("Metrics pushed", ["successful" => $pushed, "failed" => $pushFailed]);
            if ($pushed == 0) {
                throw new Exception("every metrics push failed: " . $exporter->lastError());
            }
        }
    }

    /**
     * @param array|null $message
     * @return bool true when the message means that a worker has finished
     */
    protected function handleMessage(Metrics $metrics, $message): bool
    {
        if (!is_array($message) || !isset($message['type'])) {
            return false;
        }

        switch ($message['type']) {
            case 'ok':
                $metrics->operationFinished($message['job'], Metrics::SUCCESS, $message['attempts'], $message['latency']);
                break;
            case 'err':
                $metrics->operationFinished($message['job'], Metrics::FAILURE, $message['attempts'], $message['latency']);
                $metrics->errorOccurred($message['job'], $message['error']);
                break;
            case 'retried':
                $metrics->errorOccurred($message['job'], $message['error']);
                break;
            case 'done':
                return true;
        }

        return false;
    }
}
