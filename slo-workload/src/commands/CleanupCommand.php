<?php

namespace YdbPlatform\Ydb\Slo\commands;

use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\DataGenerator;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;
use YdbPlatform\Ydb\YdbTable;

class CleanupCommand extends \YdbPlatform\Ydb\Slo\Command
{

    public $name = "cleanup";
    public $description = "drops table in database";
    public $options = [
        [
            "alias"         => ["t", "table-name"],
            "type"          => "string",
            "description"   =>  "table name to create"
        ],
        [
            "alias"         => ["c", "initial-data-count"],
            "type"          => "int",
            "description"   =>  "table name to create"
        ]
    ];

    public function execute(string $endpoint, string $path, array $options)
    {
        $tableName = $options["table-name"] ?? Defaults::TABLE_NAME;

        $ydb = Utils::initDriver($endpoint, $path);

        $table = $ydb->table();

        $ydb->table()->getLogger()->info("Drop table", [
            "tableName"    => $tableName
        ]);

        $table->retrySession(function (Session $session) use ($tableName) {
            $session->dropTable($tableName);
        }, true);

        $ydb->table()->getLogger()->info("Dropped table", [
            "tableName"    => $tableName
        ]);
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
