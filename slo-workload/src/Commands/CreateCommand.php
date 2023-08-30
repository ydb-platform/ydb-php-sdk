<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;

class CreateCommand extends \YdbPlatform\Ydb\Slo\Command
{

    public $name = "create";
    public $description = "creates table in database";
    public $options = [
        [
            "alias" => ["t", "-table-name"],
            "type" => "string",
            "description" => "table name to create"
        ],
        [
            "alias" => ["c", "-initial-data-count"],
            "type" => "int",
            "description" => "table name to create"
        ],
        [
            "alias" => ["-min-partitions-count"],
            "type" => "int",
            "description" => "table name to create"
        ],
        [
            "alias" => ["-partition-size"],
            "type" => "int",
            "description" => "table name to create"
        ],
        [
            "alias" => ["-write-timeout"],
            "type" => "int",
            "description" => "table name to create"
        ],
    ];
    public $help = "create <endpoint> <db> [options]

Arguments:
  endpoint                        YDB endpoint to connect to
  db                              YDB database to connect to

Options:
  -t -table-name         <string> table name to create

  -min-partitions-count  <int>    minimum amount of partitions in table
  -max-partitions-count  <int>    maximum amount of partitions in table
  -partition-size        <int>    partition size in mb

  -c -initial-data-count <int>    amount of initially created rows

  -write-timeout         <int>    write timeout milliseconds
";

    public function execute(string $endpoint, string $path, array $options)
    {
        $tableName = $options["-table-name"] ?? Defaults::TABLE_NAME;
        $minPartitionsCount = (int)($options["-min-partitions-count"] ?? Defaults::TABLE_MIN_PARTITION_COUNT);
        $maxPartitionsCount = (int)($options["-max-partitions-count"] ?? Defaults::TABLE_MAX_PARTITION_COUNT);
        $partitionSize = (int)($options["-partition-size"] ?? Defaults::TABLE_PARTITION_SIZE);
        $initialDataCount = (int)($options["-initial-data-count"] ?? Defaults::GENERATOR_DATA_COUNT);
        $writeTimeout = (int)($options["-write-timeout"] ?? Defaults::WRITE_TIMEOUT);

        $ydb = Utils::initDriver($endpoint, $path, "create");

        $dataGenerator = new DataGenerator(0);

        $table = $ydb->table();

        $ydb->table()->getLogger()->info("Create table", [
            "tableName" => $tableName,
            "minPartitionsCount" => $minPartitionsCount,
            "maxPartitionsCount" => $maxPartitionsCount,
            "partitionSize" => $partitionSize,
        ]);

        /*$table = new YdbTable();
        $partitionSettings = [];
        $partitionSettings['partitioning_by_size'] = $partitionSize;
        $partitionSettings['min_partitions_count'] = $minPartitionsCount;
        $partitionSettings['max_partitions_count'] = $maxPartitionsCount;
        $table->partitionSettings($partitionSettings);
        $table->addColumn('hash', 'UINT64');
        $table->addColumn('id', 'UINT64');
        $table->addColumn('payload_str', 'UTF8');
        $table->addColumn('payload_double', 'DOUBLE');
        $table->addColumn('payload_timestamp', 'TIMESTAMP');
        $table->addColumn('payload_hash', 'UINT64');
        $table->primaryKey(['hash', 'id']);
        $table->compactionPolicy('small_table');

        $ydb->table()->retrySession(function (Session $session) use ($table, $tableName) {
            $session->createTable($tableName, $table, ['hash', 'id']);
        });*/

        $yql = "CREATE TABLE `$tableName`
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
    AUTO_PARTITIONING_MIN_PARTITIONS_COUNT = $minPartitionsCount,
    AUTO_PARTITIONING_MIN_PARTITIONS_COUNT = $maxPartitionsCount,
    AUTO_PARTITIONING_PARTITION_SIZE_MB = $partitionSize
);
";

        $table->retrySession(function (Session $session) use ($yql) {
            $session->schemeQuery($yql);
        }, true, new RetryParams($writeTimeout));

        $ydb->table()->getLogger()->info("Table created");

        $ydb->table()->retryTransaction(function (Session $session) use ($dataGenerator, $tableName, $initialDataCount) {
            $prepared = $session->prepare(sprintf(Defaults::WRITE_QUERY, $tableName));
            for ($i = 0; $i < $initialDataCount; $i++) {
                $prepared->execute($dataGenerator->getUpsertData());
            }
        }, false, new RetryParams($writeTimeout));

        $ydb->table()->getLogger()->info("Data filled");
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

}
