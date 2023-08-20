<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;
use YdbPlatform\Ydb\Traits\TypeHelpersTrait;
use YdbPlatform\Ydb\Ydb;

class RunCommand extends \YdbPlatform\Ydb\Slo\Command
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
    protected $queueId;

    public function execute(string $endpoint, string $path, array $options)
    {
        $startTime = microtime(true);
        print_r($options);
        exec('./go-server/testHttpServer > /dev/null &');
        sleep(1);
        $childs = array();
        $tableName = $options["table-name"] ?? Defaults::TABLE_NAME;
        $initialDataCount = (int)($options["initial-data-count"] ?? Defaults::GENERATOR_DATA_COUNT);
        $promPgw = ($options["prom-pgw"] ?? Defaults::PROMETHEUS_PUSH_GATEWAY);
        $reportPeriod = (int)($options["report-period"] ?? Defaults::PROMETHEUS_PUSH_PERIOD);
        $readForks = ((int)($options["read-rps"] ?? Defaults::READ_RPS)) / Defaults::RPS_PER_READ_FORK;
        $readTimeout = (int)($options["read-timeout"] ?? Defaults::READ_TIMEOUT);
        $writeForks = ((int)($options["write-rps"] ?? Defaults::WRITE_RPS)) / Defaults::RPS_PER_WRITE_FORK;
        $writeTimeout = (int)($options["write-timeout"] ?? Defaults::WRITE_TIMEOUT);
        $time = (int)($options["time"] ?? Defaults::READ_TIME);
        $shutdownTime = (int)($options["shutdown-time"] ?? Defaults::SHUTDOWN_TIME);

        $this->queueId = ftok(__FILE__, 'm');
        $msgQueue = msg_get_queue($this->queueId);

        for ($i = 0; $i < $readForks; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0) {
                try {
                    $this->readJob($endpoint, $path, $tableName, $initialDataCount, $time, $readTimeout, $i, $shutdownTime, $startTime);
                } catch (\Exception $e) {
                    echo "Error on $i'th fork: " . $e->getMessage();
                }
                exit(0);
            } else {
                $childs[] = $pid;
                usleep($i * 1e4);
            }
        }
        for ($i = 0; $i < $writeForks; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0) {
                try {
                    $this->writeJob($endpoint, $path, $tableName, $initialDataCount, $time, $writeTimeout, $i,$shutdownTime,$startTime);
                } catch (\Exception $e) {
                    echo "Error on $i'th fork: " . $e->getMessage();
                }
                exit(0);
            } else {
                $childs[] = $pid;
                usleep($i * 1e4);
            }
        }

        $pid = pcntl_fork();
        if ($pid == -1) {
            echo "Error fork";
            exit(1);
        } elseif ($pid == 0) {
            try {
                $this->metricsJob($reportPeriod);
            } catch (\Exception $e) {
                echo "Error on $i'th fork: " . $e->getMessage();
            }
            exit(0);
        }

        foreach ($childs as $pid) {
            pcntl_waitpid($pid, $status);
            unset($childs[$pid]);
        }
        Utils::reset($this->queueId);
        exit(0);
    }

    protected function readJob(string $endpoint, string $path, string $tableName, int $initialDataCount,
                               int    $time, int $readTimeout, int $process, int $shutdownTime, int $startTime)
    {
        try {
            $ydb = Utils::initDriver($endpoint, $path, "read-$process");
            $dataGenerator = new DataGenerator();
            $dataGenerator::setMaxId($initialDataCount);
            $query = sprintf(Defaults::READ_QUERY, $tableName);
            $table = $ydb->table();
        } catch (\Exception $e){
            echo $e->getMessage();
        }

        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            Utils::metricInflight("read", $this->queueId);
            $attemps = 0;
            try {
                $table->retryTransaction(function (\YdbPlatform\Ydb\Session $session)
                use ($query, $dataGenerator, $tableName, &$attemps) {
                    $attemps++;
                    return $session->query($query, [
                        "\$id" => (new \YdbPlatform\Ydb\Types\Uint64Type($dataGenerator->getRandomId()))->toTypedValue()
                    ]);
                }, true, new \YdbPlatform\Ydb\Retry\RetryParams($readTimeout));
                Utils::metricDone("read", $this->queueId, $attemps, (microtime(true)-$begin)*1000);
            } catch (\Exception $e) {
//                if ($attemps == 0) $attemps++;
                $table->getLogger()->error($e->getMessage());
                Utils::metricFail("read", $this->queueId, $attemps, get_class($e), (microtime(true)-$begin)*1000);
            } finally {
                $delay = ($begin - microtime(true)) * 1e6 + 1e6 / Defaults::RPS_PER_READ_FORK;
                usleep($delay > 0 ? $delay : 1);
            }

        }

    }

    protected function writeJob(string $endpoint, string $path, $tableName, int $initialDataCount,
                                int    $time, int $writeTimeout, int $process, int $shutdownTime, int $startTime)
    {
        try {
            $ydb = Utils::initDriver($endpoint, $path, "write-$process");
            $dataGenerator = new DataGenerator();
            $dataGenerator::setMaxId($initialDataCount);
            $query = sprintf(Defaults::WRITE_QUERY, $tableName);
            $table = $ydb->table();
        }catch (\Exception $e){
            echo $e->getMessage();
        }

        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            Utils::metricInflight("read", $this->queueId);
            $attemps = 0;
            try {
                $table->retryTransaction(function (\YdbPlatform\Ydb\Session $session)
                use ($query, $dataGenerator, $tableName, &$attemps) {
                    $attemps++;
                    return $session->query($query, [
                        "\$id" => DataGenerator::getUpsertData()
                    ]);
                }, true, new \YdbPlatform\Ydb\Retry\RetryParams($writeTimeout));
                Utils::metricDone("read", $this->queueId, $attemps, (microtime(true)-$begin)*1000);
            } catch (\Exception $e) {
//                if ($attemps == 0) $attemps++;
                $table->getLogger()->error($e->getMessage());
                Utils::metricFail("read", $this->queueId, $attemps, get_class($e), (microtime(true)-$begin)*1000);
            } finally {
                $delay = ($begin - microtime(true)) * 1e6 + 1e6 / Defaults::RPS_PER_READ_FORK;
                usleep($delay > 0 ? $delay : 1);
            }
        }
    }

    protected function metricsJob(int $reportPeriod, int $time, int $startTime, string $url)
    {
        $registry = new CollectorRegistry(new InMemory);
        $pushGateway = new \PrometheusPushGateway\PushGateway($url);

        $latencies = $registry->getOrRegisterSummary('', 'latency', 'summary of latencies in ms', ['jobName', 'status'], 600, [0.5, 0.99, 0.999]);
        $oks = $registry->getOrRegisterGauge('', 'oks', 'amount of OK requests', ['jobName']);
        $notOks = $registry->getOrRegisterGauge('', 'not_oks', 'amount of not OK requests', ['jobName']);
        $inflight = $registry->getOrRegisterGauge('', 'inflight', 'amount of requests in flight', ['jobName']);
        $errors = $registry->getOrRegisterGauge('', 'errors', 'amount of errors', ['jobName', 'class']);
        $attempts = $registry->getOrRegisterHistogram('', 'attempts', 'summary of amount for request', ['jobName', 'status'], range(1,10,1));
        $msgQueue = msg_get_queue($this->queueId);
        $lastPushTime = microtime(true);

        foreach ($this->errors as $error){
            $errors->inc(['read', $error]);
            $errors->inc(['write', $error]);
        }

        while (true){
            while (msg_receive($msgQueue, 1, $msgType, 1024, $message)){
                switch ($message['type']){
                    case 'reset':
                        $pushGateway->delete('workload-php');
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
                        $errors->inc([$message['job'], $message['error']]);
                        break;

                }
            }
            if((microtime(true)-$lastPushTime)*1000>$reportPeriod){
                $pushGateway->push($registry, "workload-php", [
                    'sdk'       => 'php',
                    'version'   => Ydb::VERSION
                ]);
                $lastPushTime = microtime(true);
            }
            usleep(1e3);
        }
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

}
