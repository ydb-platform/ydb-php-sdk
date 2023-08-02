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
            "alias" => ["t", "table-name"],
            "type" => "string",
            "description" => "table name to create"
        ],
        [
            "alias" => ["initial-data-count"],
            "type" => "int",
            "description" => "amount of initially created rows"
        ],
        [
            "alias" => ["prom-pgw"],
            "type" => "string",
            "description" => "prometheus push gateway"
        ],
        [
            "alias" => ["report-period"],
            "type" => "int",
            "description" => "prometheus push period in milliseconds"
        ],
        [
            "alias" => ["read-rps"],
            "type" => "int",
            "description" => "read RPS"
        ],
        [
            "alias" => ["read-timeout"],
            "type" => "int",
            "description" => "read timeout milliseconds"
        ],
        [
            "alias" => ["write-rps"],
            "type" => "int",
            "description" => "write RPS"
        ],
        [
            "alias" => ["write-timeout"],
            "type" => "int",
            "description" => "write timeout milliseconds"
        ],
        [
            "alias" => ["time"],
            "type" => "int",
            "description" => "run time in seconds"
        ],
        [
            "alias" => ["shutdown-time"],
            "type" => "int",
            "description" => "graceful shutdown time in seconds"
        ]
    ];

    public function execute(string $endpoint, string $path, array $options)
    {
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
            usleep($i*1e3);
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0){
                $this->readJob($endpoint, $path, $tableName, $initialDataCount, $time, $readTimeout,$i);
                exit(0);
            }
        }
        for ($i = 0; $i < $writeForks; $i++) {
            usleep($i*1e3);
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo "Error fork";
                exit(1);
            } elseif ($pid == 0){
                $this->writeJob($endpoint, $path, $tableName, $initialDataCount, $time, $writeTimeout,$i);
                exit(0);
            }
        }
        sleep($time+1);
        exec("pkill php");
        exec("pkill test");
    }

    protected function readJob(string $endpoint, string $path, string $tableName, int $initialDataCount,
                               int $time, int $readTimeout, int $process)
    {
        $ydb = Utils::initDriver($endpoint, $path);
        $dataGenerator = new DataGenerator();
        $dataGenerator::setMaxId($initialDataCount);
        $query = sprintf(Defaults::READ_QUERY, $tableName);
        $startTime = microtime(true);
        $table = $ydb->table();

        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            Utils::metricInflight("read", $process);
            $attemps = 0;
            try {
                $result = $table->retryTransaction(function (\YdbPlatform\Ydb\Session $session)
                use ($query, $dataGenerator, $tableName, &$attemps) {
                    $attemps++;
                    return $session->query($query, [
                        "\$id" => (new \YdbPlatform\Ydb\Types\Uint64Type($dataGenerator->getRandomId()))->toTypedValue()
                    ]);
                }, true, new \YdbPlatform\Ydb\Retry\RetryParams($readTimeout));
                Utils::metricDone("read", $process, $attemps);
            } catch (Exception $e) {
                if ($attemps == 0) $attemps++;
                Utils::metricFail("read", $process, $attemps, get_class($e));
            } finally {
                $delay = ($begin-microtime(true))*1e6+1e6/Defaults::RPS_PER_FORK;
                usleep($delay>0?$delay:1);
            }

        }

    }

    protected function writeJob(string $endpoint, string $path, $tableName, int $initialDataCount,
                                int $time, int $writeTimeout, int $process)
    {
        $ydb = Utils::initDriver($endpoint, $path);
        $dataGenerator = new DataGenerator();
        $dataGenerator::setMaxId($initialDataCount);
        $query = sprintf(Defaults::READ_QUERY, $tableName);
        $startTime = microtime(true);
        $table = $ydb->table();

        while (microtime(true) <= $startTime + $time) {
            $begin = microtime(true);
            Utils::metricInflight("write", $process);
            $attemps = 0;
            try {
                $result = $table->retryTransaction(function (\YdbPlatform\Ydb\Session $session)
                use ($query, $dataGenerator, $tableName, &$attemps) {
                    $attemps++;
                    return $session->query($query, $dataGenerator::getUpsertData());
                }, true, new \YdbPlatform\Ydb\Retry\RetryParams($writeTimeout));
                Utils::metricDone("write", $process, $attemps);
            } catch (Exception $e) {
                if ($attemps == 0) $attemps++;
                Utils::metricFail("write", $process, $attemps, get_class($e));
            } finally {
                $delay = ($begin-microtime(true))*1e6+1e6/Defaults::RPS_PER_FORK;
                usleep($delay>0?$delay:1);
            }
        }
    }
}
