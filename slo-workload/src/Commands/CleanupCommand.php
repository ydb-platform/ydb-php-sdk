<?php

namespace YdbPlatform\Ydb\Slo\Commands;

use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Slo\Command;
use YdbPlatform\Ydb\Slo\Config;
use YdbPlatform\Ydb\Slo\Utils;

class CleanupCommand extends Command
{
    public $name = "cleanup";
    public $description = "drops the table";

    public function execute(Config $config)
    {
        $ydb = Utils::initDriver($config, "cleanup");
        $table = $ydb->table();
        $logger = $table->getLogger();

        $logger->info("Drop table", ["tableName" => $config->tableName]);

        $table->retrySession(function (Session $session) use ($config) {
            $session->dropTable($config->tableName);
        }, true, new RetryParams($config->writeTimeout));

        $logger->info("Dropped table", ["tableName" => $config->tableName]);
    }
}
