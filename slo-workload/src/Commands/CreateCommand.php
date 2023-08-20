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
    public $help = "cleanup <endpoint> <db> [options]
Arguments:
  endpoint                        YDB endpoint to connect to
  db                              YDB database to connect to

Options:
  -t -table-name         <string> table name to create

  -write-timeout         <int>    write timeout milliseconds";

    public function execute(string $endpoint, string $path, array $options)
    {
        $tableName = $options["-table-name"] ?? Defaults::TABLE_NAME;
        $minPartitionsCount = (int)($options["-min-partitions-count"] ?? Defaults::TABLE_MIN_PARTITION_COUNT);
        $maxPartitionsCount = (int)($options["-max-partitions-count"] ?? Defaults::TABLE_MAX_PARTITION_COUNT);
        $partitionSize = (int)($options["-partition-size"] ?? Defaults::TABLE_PARTITION_SIZE);
        $initialDataCount = (int)($options["-initial-data-count"] ?? Defaults::GENERATOR_DATA_COUNT);

        $ydb = Utils::initDriver($endpoint, $path, "create");

        $dataGenerator = new DataGenerator();
        $dataGenerator::setMaxId(0);

        $table = $ydb->table();

        $ydb->table()->getLogger()->info("Create table", [
            "tableName" => $tableName,
            "minPartitionsCount" => $minPartitionsCount,
            "maxPartitionsCount" => $maxPartitionsCount,
            "partitionSize" => $partitionSize,
        ]);

//        $table = new YdbTable();
//        $partitionSettings = [];
//        $partitionSettings['partitioning_by_size'] = $partitionSize;
//        $partitionSettings['min_partitions_count'] = $minPartitionsCount;
//        $partitionSettings['max_partitions_count'] = $maxPartitionsCount;
//        $table->partitionSettings($partitionSettings);
//        $table->addColumn('hash', 'UINT64');
//        $table->addColumn('id', 'UINT64');
//        $table->addColumn('payload_str', 'UTF8');
//        $table->addColumn('payload_double', 'DOUBLE');
//        $table->addColumn('payload_timestamp', 'TIMESTAMP');
//        $table->addColumn('payload_hash', 'UINT64');
//        $table->primaryKey(['hash', 'id']);
//        $table->compactionPolicy('small_table');
//
//        $ydb->table()->retrySession(function (Session $session) use ($table, $tableName) {
//            $session->createTable($tableName, $table, ['hash', 'id']);
//        });

        try {
            $table->dropTable($tableName);
        }catch (\Exception $e){

        }

        $table->retrySession(function (Session $session) use ($tableName) {
            $q = $session->schemeQuery("CREATE TABLE `$tableName`
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
    AUTO_PARTITIONING_MIN_PARTITIONS_COUNT = 6,
    AUTO_PARTITIONING_MIN_PARTITIONS_COUNT = 1000,
    AUTO_PARTITIONING_PARTITION_SIZE_MB = 1
);
");
        }, true, new RetryParams(40000));

        $ydb->table()->getLogger()->info("Table created");

        $ydb->table()->retryTransaction(function (Session $session) use ($tableName, $initialDataCount) {
            $prepared = $session->prepare(sprintf(Defaults::WRITE_QUERY, $tableName));
            for ($i = 0; $i < $initialDataCount; $i++) {
                $prepared->execute(DataGenerator::getUpsertData());
            }
        }, false, new RetryParams(40e3));

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
