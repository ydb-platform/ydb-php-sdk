<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use Closure;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use PrometheusPushGateway\PushGateway;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\Command;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;
use YdbPlatform\Ydb\Traits\TypeHelpersTrait;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Ydb;

class RunCommand extends Command
{
    use TypeHelpersTrait;

    public $name = "run";
    public $description = "runs workload (read and write to table with sets RPS)";
    public $options = [
        [
            "alias" => ["t", "-table-name"],
            "type" => "string",
            "description" => "table name to create"
        ],
        [
            "alias" => ["-initial-data-count"],
            "type" => "int",
            "description" => "amount of initially created rows"
        ],
        [
            "alias" => ["-prom-pgw"],
            "type" => "string",
            "description" => "prometheus push gateway"
        ],
        [
            "alias" => ["-report-period"],
            "type" => "int",
            "description" => "prometheus push period in milliseconds"
        ],
        [
            "alias" => ["-read-rps"],
            "type" => "int",
            "description" => "read RPS"
        ],
        [
            "alias" => ["-read-timeout"],
            "type" => "int",
            "description" => "read timeout milliseconds"
        ],
        [
            "alias" => ["-write-rps"],
            "type" => "int",
            "description" => "write RPS"
        ],
        [
            "alias" => ["-write-timeout"],
            "type" => "int",
            "description" => "write timeout milliseconds"
        ],
        [
            "alias" => ["-time"],
            "type" => "int",
            "description" => "run time in seconds"
        ],
        [
            "alias" => ["-shutdown-time"],
            "type" => "int",
            "description" => "graceful shutdown time in seconds"
        ]
    ];
    public $help = "run <endpoint> <db> [options]
Arguments:
  endpoint                        YDB endpoint to connect to
  db                              YDB database to connect to

Options:
  -t -table-name         <string> table name to create

  -initial-data-count    <int>    amount of initially created rows

  -prom-pgw              <string> prometheus push gateway
  -report-period         <int>    prometheus push period in milliseconds

  -read-rps              <int>    read RPS
  -read-timeout          <int>    read timeout milliseconds

  -write-rps             <int>    write RPS
  -write-timeout         <int>    write timeout milliseconds

  -time                  <int>    run time in seconds
  -shutdown-time         <int>    graceful shutdown time in seconds";
    /**
     * @var int
     */
    protected $metricsQueueId;
    protected $readQueueId;
    protected $writeQueueId;

    public function execute(string $endpoint, string $path, array $options)
    {
        $startTime = microtime(true);
        $tableName = $options["-table-name"] ?? Defaults::TABLE_NAME;
        $initialDataCount = (int)($options["-initial-data-count"] ?? Defaults::GENERATOR_DATA_COUNT);
        $promPgw = ($options["-prom-pgw"] ?? Defaults::PROMETHEUS_PUSH_GATEWAY);
        $reportPeriod = (int)($options["-report-period"] ?? Defaults::PROMETHEUS_PUSH_PERIOD);
        $readRps = ((int)($options["-read-rps"] ?? Defaults::READ_RPS));
        $readTimeout = (int)($options["-read-timeout"] ?? Defaults::READ_TIMEOUT);
        $writeRps = ((int)($options["-write-rps"] ?? Defaults::WRITE_RPS));
        $writeTimeout = (int)($options["-write-timeout"] ?? Defaults::WRITE_TIMEOUT);
        $time = (int)($options["-time"] ?? Defaults::READ_TIME);
        $shutdownTime = (int)($options["-shutdown-time"] ?? Defaults::SHUTDOWN_TIME);

        $this->createQuery('m', $this->metricsQueueId);
        $this->createQuery('w', $this->readQueueId);
        $this->createQuery('r', $this->writeQueueId);

        $pIds = [];

        $fullQueryPIds = $this->forkJob(function (int $i) use ($readRps, $writeRps, $startTime, $time) {
            $this->fillQueryJob($readRps, $writeRps, $startTime, $time);
        }, 1);
        $pIds = array_merge($pIds, $fullQueryPIds);

        $metricsPIds = $this->forkJob(function (int $i) use ($startTime, $reportPeriod, $time, $promPgw) {
            $this->metricsJob($reportPeriod, $startTime, $time, $promPgw);
        }, 1);
        $pIds = array_merge($pIds, $metricsPIds);

        $readPIds = $this->forkJob(function (int $i) use ($endpoint, $path, $tableName, $initialDataCount, $time, $readTimeout, $shutdownTime, $startTime) {
            $this->readJob($endpoint, $path, $tableName, $initialDataCount, $time, $readTimeout, $i, $shutdownTime, $startTime);
        }, Defaults::READ_FORKS);
        $pIds = array_merge($pIds, $readPIds);

        $writePIds = $this->forkJob(function (int $i) use ($endpoint, $path, $tableName, $initialDataCount, $time, $writeTimeout, $shutdownTime, $startTime) {
            $this->writeJob($endpoint, $path, $tableName, $initialDataCount, $time, $writeTimeout, $i, $shutdownTime, $startTime);
        }, Defaults::WRITE_FORKS);
        $pIds = array_merge($pIds, $writePIds);

        foreach ($pIds as $pid) {
            pcntl_waitpid($pid, $status);
            unset($pIds[$pid]);
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $promPgw . '/metrics/job/workload-php/sdk/php/sdkVersion/' . Ydb::VERSION,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
        ));

        curl_exec($curl);

        curl_close($curl);
    }

    function forkJob(Closure $function, int $count): array
    {
        $pIds = [];
        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0) {
                try {
                    $function($i);
                } catch (\Exception $e) {
                    echo "Error on $i'th fork: " . $e->getMessage();
                }
                exit(0);
            } else {
                $pIds[] = $pid;
                usleep($i * 1e3);
            }
        }
        return $pIds;
    }

    protected function readJob(string $endpoint, string $path, $tableName, int $initialDataCount, int $time, int $readTimeout, int $process, int $shutdownTime, $startTime)
    {
        $ydb = Utils::initDriver($endpoint, $path, "read-$process");
        $dataGenerator = new DataGenerator($initialDataCount);
        $query = sprintf(Defaults::READ_QUERY, $tableName);
        $table = $ydb->table();

        while (microtime(true) <= $startTime + $time) {
            if ($this->checkQuery($this->readQueueId)) {
                $begin = microtime(true);
                Utils::metricsStart("read", $this->metricsQueueId);
                $attemps = 0;
                $id = (new Uint64Type($dataGenerator->getRandomId()))->toTypedValue();
                try {
                    $table->retryTransaction(function (Session $session)
                    use ($id, $query, $dataGenerator, $tableName, &$attemps) {
                        try {
                            $session->query($query, [
                                "\$id" => $id
                            ]);
                            $attemps++;
                        } catch (\Exception $exception) {
                            Utils::retriedError($this->metricsQueueId, 'read', get_class($exception));
                        }
                    }, true, null, [
                        'callback_on_error' => function (\Exception $e) use (&$attemps) {
                            $attemps++;
                            Utils::retriedError($this->metricsQueueId, 'write', get_class($e));
                        }
                    ]);
                    Utils::metricDone("read", $this->metricsQueueId, $attemps, $this->getLatencyMilliseconds($begin));
                } catch (\Exception $e) {
                    $table->getLogger()->error($e->getMessage());
                    Utils::metricFail("read", $this->metricsQueueId, $attemps, get_class($e), $this->getLatencyMilliseconds($begin));
                }
            }
            usleep(1000);

        }
    }

    protected function writeJob(string $endpoint, string $path, $tableName, int $initialDataCount, int $time, int $readTimeout, int $process, int $shutdownTime, $startTime)
    {
        $ydb = Utils::initDriver($endpoint, $path, "write-$process");
        $dataGenerator = new DataGenerator($initialDataCount);
        $query = sprintf(Defaults::WRITE_QUERY, $tableName);
        $table = $ydb->table();
        while (microtime(true) <= $startTime + $time) {
            if ($this->checkQuery($this->writeQueueId)) {
                $begin = microtime(true);
                Utils::metricsStart("write", $this->metricsQueueId);
                $attemps = 0;
                $upsertData = $dataGenerator->getUpsertData();
                try {
                    $table->retryTransaction(function (Session $session)
                    use ($upsertData, $query, $dataGenerator, $tableName, &$attemps) {
                        $session->query($query, $upsertData);
                        $attemps++;
                    }, true, null, [
                        'callback_on_error' => function (\Exception $e) use (&$attemps) {
                            $attemps++;
                            Utils::retriedError($this->metricsQueueId, 'write', get_class($e));
                        }
                    ]);
                    Utils::metricDone("write", $this->metricsQueueId, $attemps, $this->getLatencyMilliseconds($begin));
                } catch (\Exception $e) {
                    $table->getLogger()->error($e->getMessage());
                    Utils::metricFail("write", $this->metricsQueueId, $attemps, get_class($e), $this->getLatencyMilliseconds($begin));
                }
            }
            usleep(1000);
        }
    }

    protected function metricsJob(int $reportPeriod, float $startTime, int $time, string $promPgw)
    {
        $registry = new CollectorRegistry(new InMemory);
        $pushGateway = new PushGateway($promPgw);

        $latencies = $registry->getOrRegisterSummary('', 'latency', 'summary of latencies in ms', ['jobName', 'status'], 15, [0.5, 0.99, 0.999]);
        $queryLatencies = $registry->getOrRegisterSummary('', 'query_latency', 'summary of latencies in ms in query', [], 15, [0.5, 0.99, 0.999]);
        $oks = $registry->getOrRegisterGauge('', 'oks', 'amount of OK requests', ['jobName']);
        $notOks = $registry->getOrRegisterGauge('', 'not_oks', 'amount of not OK requests', ['jobName']);
        $inflight = $registry->getOrRegisterGauge('', 'inflight', 'amount of requests in flight', ['jobName']);
        $errors = $registry->getOrRegisterGauge('', 'errors', 'amount of errors', ['jobName', 'class', 'in']);
        $attempts = $registry->getOrRegisterHistogram('', 'attempts', 'summary of amount for request', ['jobName', 'status'], range(1, 10, 1));
        $msgQueue = msg_get_queue($this->metricsQueueId);
        $pushGateway->delete('workload-php', [
            'sdk' => 'php',
            'sdkVersion' => Ydb::VERSION
        ]);

        $lastPushTime = microtime(true);
        foreach ($this->errors as $error) {
            $errors->incBy(0, ['read', $error, 'finally']);
            $errors->incBy(0, ['write', $error, 'finally']);
            $errors->incBy(0, ['read', $error, 'retried']);
            $errors->incBy(0, ['write', $error, 'retried']);
        }

        $pushGateway->push($registry, "workload-php", [
            'sdk' => 'php',
            'sdkVersion' => Ydb::VERSION
        ]);

        while (microtime(true) <= $startTime + $time) {
            msg_receive($msgQueue, Utils::MSG_METRICS_TYPE, $msgType, Utils::MESSAGE_SIZE_LIMIT_BYTES, $message);
            $queryLatencies->observe($this->getLatencyMilliseconds($message["sent"]));
            switch ($message['type']) {
                case 'reset':
                    $pushGateway->delete('workload-php', [
                        'sdk' => 'php',
                        'sdkVersion' => Ydb::VERSION
                    ]);
                    return;
                case  'start':
                    $inflight->inc([$message['job']]);
                    break;
                case 'ok':
                    $inflight->dec([$message['job']]);
                    $latencies->observe($message['latency'], [$message['job'], $message['type']]);
                    $attempts->observe($message['attempts'], [$message['job'], $message['type']]);
                    $oks->inc([$message['job']]);
                    break;
                case 'err':
                    $inflight->dec([$message['job']]);
                    $latencies->observe($message['latency'], [$message['job'], $message['type']]);
                    $attempts->observe($message['attempts'], [$message['job'], $message['type']]);
                    $notOks->inc([$message['job']]);
                    $errors->inc([$message['job'], $message['error'], 'finally']);
                    break;
                case 'retried':
                    $errors->inc([$message['job'], $message['error'], 'retried']);
                    break;
            }
            if ((microtime(true) - $lastPushTime) * 1000 > $reportPeriod) {
                $pushGateway->push($registry, "workload-php", [
                    'sdk' => 'php',
                    'sdkVersion' => Ydb::VERSION
                ]);
                $lastPushTime = microtime(true);
            }
        }
        msg_remove_queue($msgQueue);
    }

    protected function fillQueryJob(int $readRps, int $writeRps, float $startTime, int $time)
    {
        $readQuery = msg_get_queue($this->readQueueId);
        $writeQuery = msg_get_queue($this->writeQueueId);
        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            msg_remove_queue($readQuery);
            for ($i = 0; $i < $readRps; $i++) {
                msg_send($readQuery, Utils::MSG_READ_TYPE, 0);
            }
            msg_remove_queue($writeQuery);
            for ($i = 0; $i < $writeRps; $i++) {
                msg_send($writeQuery, Utils::MSG_WRITE_TYPE, 0);
            }
            usleep(($begin + 1 - microtime(true)) * 1000000);
        }
        msg_remove_queue($readQuery);
        msg_remove_queue($writeQuery);
    }

    protected function getLatencyMilliseconds(float $begin): float
    {
        return (microtime(true) - $begin) * 1000;
    }

    protected $errors = [
        "GRPC_CANCELLED",
        "GRPC_UNKNOWN",
        "GRPC_INVALID_ARGUMENT",
        "GRPC_DEADLINE_EXCEEDED",
        "GRPC_NOT_FOUND",
        "GRPC_ALREADY_EXISTS",
        "GRPC_PERMISSION_DENIED",
        "GRPC_RESOURCE_EXHAUSTED",
        "GRPC_FAILED_PRECONDITION",
        "GRPC_ABORTED",
        "GRPC_OUT_OF_RANGE",
        "GRPC_UNIMPLEMENTED",
        "GRPC_INTERNAL",
        "GRPC_UNAVAILABLE",
        "GRPC_DATA_LOSS",
        "GRPC_UNAUTHENTICATED",
        "YDB_STATUS_CODE_UNSPECIFIED",
        "YDB_SUCCESS",
        "YDB_BAD_REQUEST",
        "YDB_UNAUTHORIZED",
        "YDB_INTERNAL_ERROR",
        "YDB_ABORTED",
        "YDB_UNAVAILABLE",
        "YDB_OVERLOADED",
        "YDB_SCHEME_ERROR",
        "YDB_GENERIC_ERROR",
        "YDB_TIMEOUT",
        "YDB_BAD_SESSION",
        "YDB_PRECONDITION_FAILED",
        "YDB_ALREADY_EXISTS",
        "YDB_NOT_FOUND",
        "YDB_SESSION_EXPIRED",
        "YDB_CANCELLED",
        "YDB_UNDETERMINED",
        "YDB_UNSUPPORTED",
        "YDB_SESSION_BUSY"
    ];

    protected function createQuery(string $id, int &$query)
    {
        $query = ftok(__FILE__, $id);
        msg_remove_queue(msg_get_queue($query));
    }

    protected function checkQuery(int $queryId): bool
    {
        $query = msg_get_queue($queryId);
        return msg_receive($query, Utils::MSG_METRICS_TYPE, $msgType, Utils::MESSAGE_SIZE_LIMIT_BYTES, $message, true, MSG_IPC_NOWAIT);
    }

}
