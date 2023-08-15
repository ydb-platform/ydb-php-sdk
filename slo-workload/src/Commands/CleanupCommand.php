<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\Defaults;
use YdbPlatform\Ydb\Slo\Utils;

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
    public $help = "cleanup <endpoint> <db> [options]
Arguments:
  endpoint                        YDB endpoint to connect to
  db                              YDB database to connect to

Options:
  -t -table-name         <string> table name to create

  -write-timeout         <int>    write timeout milliseconds";

    public function execute(string $endpoint, string $path, array $options)
    {
        $tableName = $options["table-name"] ?? Defaults::TABLE_NAME;

        $ydb = Utils::initDriver($endpoint, $path, "cleanup");

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
