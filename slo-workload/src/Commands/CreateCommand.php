<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\Command;
use YdbPlatform\Ydb\Slo\Config;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;

class CreateCommand extends Command
{
    public $name = "create";
    public $description = "creates the table and fills it with the initial data";

    public function execute(Config $config)
    {
        $ydb = Utils::initDriver($config, "create");
        $table = $ydb->table();
        $logger = $table->getLogger();

        $logger->info("Create table", [
            "tableName" => $config->tableName,
            "minPartitionsCount" => $config->minPartitionsCount,
            "maxPartitionsCount" => $config->maxPartitionsCount,
            "partitionSize" => $config->partitionSize,
        ]);

        $yql = "CREATE TABLE IF NOT EXISTS `$config->tableName`
(
    `hash` Uint64,
    `id` Uint64,
    `payload_double` Double,
    `payload_hash` Uint64,
    `payload_str` Utf8,
    `payload_timestamp` Timestamp,
    PRIMARY KEY (`hash`, `id`)
)
WITH(
    AUTO_PARTITIONING_MIN_PARTITIONS_COUNT = $config->minPartitionsCount,
    AUTO_PARTITIONING_MAX_PARTITIONS_COUNT = $config->maxPartitionsCount,
    AUTO_PARTITIONING_PARTITION_SIZE_MB = $config->partitionSize
);
";

        $table->retrySession(function (Session $session) use ($yql) {
            $session->schemeQuery($yql);
        }, true, new RetryParams($config->writeTimeout));

        $logger->info("Table created");

        $dataGenerator = new DataGenerator(0);
        $query = sprintf(Defaults::WRITE_QUERY, $config->tableName);

        $table->retryTransaction(function (Session $session) use ($dataGenerator, $query, $config) {
            $prepared = $session->prepare($query);
            while ($dataGenerator->getMaxId() < $config->prefillCount) {
                $prepared->execute($dataGenerator->getUpsertData());
            }
        }, false, new RetryParams($config->writeTimeout));

        $logger->info("Data filled", ["rows" => $config->prefillCount]);
    }
}
