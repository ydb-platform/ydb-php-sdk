<?php

namespace YdbPlatform\Ydb\Slo\commands;

use Exception;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;
use YdbPlatform\Ydb\Traits\TypeHelpersTrait;

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

    public function execute(string $endpoint, string $path, array $options)
    {
        print_r($options);
        @mkdir('./logs');
        shell_exec('./go-server/testHttpServer > ./logs/go-server.log &');
        sleep(2);
        $childs = array();
        $tableName = $options["table-name"] ?? Defaults::TABLE_NAME;
        $initialDataCount = (int)($options["initial-data-count"] ?? Defaults::GENERATOR_DATA_COUNT);
        $promPgw = ($options["prom-pgw"] ?? Defaults::PROMETHEUS_PUSH_GATEWAY);
        $reportPeriod = (int)($options["report-period"] ?? Defaults::PROMETHEUS_PUSH_PERIOD);
        $readForks = ((int)($options["read-rps"] ?? Defaults::READ_RPS)) / Defaults::RPS_PER_FORK;
        $readTimeout = (int)($options["read-timeout"] ?? Defaults::READ_TIMEOUT);
        $writeForks = ((int)($options["write-rps"] ?? Defaults::WRITE_RPS)) / Defaults::RPS_PER_FORK;
        $writeTimeout = (int)($options["write-timeout"] ?? Defaults::WRITE_TIMEOUT);
        $time = (int)($options["time"] ?? Defaults::READ_TIME);
        $shutdownTime = (int)($options["shutdown-time"] ?? Defaults::SHUTDOWN_TIME);

        Utils::initPush($promPgw, $reportPeriod, $time);

        for ($i = 0; $i < $readForks; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0) {
                try {
                    $this->readJob($endpoint, $path, $tableName, $initialDataCount, $time-2, $readTimeout, $i, $shutdownTime);
                } catch (\Exception $e) {
                    echo "Error on $i'th fork: " . $e->getMessage();
                }
                exit(0);
            } else {
                $childs[] = $pid;
            }
        }
        for ($i = 0; $i < $writeForks; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0) {
                try {
                    $this->writeJob($endpoint, $path, $tableName, $initialDataCount, $time-2, $writeTimeout, $i,$shutdownTime);
                } catch (\Exception $e) {
                    echo "Error on $i'th fork: " . $e->getMessage();
                }
                exit(0);
            } else {
                $childs[] = $pid;
                usleep($i * 1e4);
            }
        }

        sleep($time-1);
        for ($j = 0; $j < $readForks; $j++) {
            echo "read-$j logs:\n";
            echo file_get_contents("./logs/read-$j.log");
            echo "\n\n";
        }
        for ($j = 0; $j < $writeForks; $j++) {
            echo "write-$j logs:\n";
            echo file_get_contents("./logs/write-$j.log");
            echo "\n\n";
        }
        exit(0);
    }

    protected function readJob(string $endpoint, string $path, string $tableName, int $initialDataCount,
                               int    $time, int $readTimeout, int $process, int $shutdownTime)
    {
        usleep($process * 5e4);
        try {
            $ydb = Utils::initDriver($endpoint, $path, "read-$process");
            $dataGenerator = new DataGenerator();
            $dataGenerator::setMaxId($initialDataCount);
            $query = sprintf(Defaults::READ_QUERY, $tableName);
            $startTime = microtime(true);
            $table = $ydb->table();
        } catch (\Exception $e){
            echo $e->getMessage();
        }

        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            $status = Utils::metricInflight("read", $process);
            if ($status!=200){
                $table->getLogger()->error("Error post inflight read process. Code $status");
            }
            $attemps = 0;
            try {
                $table->retryTransaction(function (\YdbPlatform\Ydb\Session $session)
                use ($query, $dataGenerator, $tableName, &$attemps) {
                    $attemps++;
                    return $session->query($query, [
                        "\$id" => (new \YdbPlatform\Ydb\Types\Uint64Type($dataGenerator->getRandomId()))->toTypedValue()
                    ]);
                }, true, new \YdbPlatform\Ydb\Retry\RetryParams($readTimeout));
                $status = Utils::metricDone("read", $process, $attemps);
                if ($status!=200){
                    $table->getLogger()->error("Error post done read process. Code $status");
                }
            } catch (\Exception $e) {
                print_r($e->getMessage());
//                if ($attemps == 0) $attemps++;
                $table->getLogger()->error($e->getMessage());
                $status = Utils::metricFail("read", $process, $attemps, get_class($e));
                if ($status!=200){
                    $table->getLogger()->error("Error post fail read process. Code $status");
                }
            } finally {
                $delay = ($begin - microtime(true)) * 1e6 + 1e6 / Defaults::RPS_PER_FORK;
                usleep($delay > 0 ? $delay : 1);
            }

        }

    }

    protected function writeJob(string $endpoint, string $path, $tableName, int $initialDataCount,
                                int    $time, int $writeTimeout, int $process, int $shutdownTime)
    {
        usleep($process * 5e4);
        try {
            $ydb = Utils::initDriver($endpoint, $path, "write-$process");
            $dataGenerator = new DataGenerator();
            $dataGenerator::setMaxId($initialDataCount);
            $query = sprintf(Defaults::WRITE_QUERY, $tableName);
            $startTime = microtime(true);
            $table = $ydb->table();
        }catch (\Exception $e){
            echo $e->getMessage();
        }

        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            $status = Utils::metricInflight("write", $process);
            if ($status!=200){
                $table->getLogger()->error("Error post done read process. Code $status");
            }
            $attemps = 0;
            try {
                $table->retryTransaction(function (\YdbPlatform\Ydb\Session $session)
                use ($query, $dataGenerator, $tableName, &$attemps) {
                    $attemps++;
                    return $session->query($query, $dataGenerator::getUpsertData());
                }, true, new \YdbPlatform\Ydb\Retry\RetryParams($writeTimeout));
                $status = Utils::metricDone("write", $process, $attemps);
                if ($status!=200){
                    $table->getLogger()->error("Error post done read process. Code $status");
                }
            } catch (\Exception $e) {
                $status = Utils::metricFail("write", $process, $attemps, get_class($e));
                if ($status!=200){
                    $table->getLogger()->error("Error post done read process. Code $status");
                }
            } finally {
                $delay = ($begin - microtime(true)) * 1e6 + 1e6 / Defaults::RPS_PER_FORK;
                usleep($delay > 0 ? $delay : 1);
            }
        }
    }
}
