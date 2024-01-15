<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Enums\ScanQueryMode;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class ScanQueryTest extends TestCase
{

    function testScanQuery()
    {

        $config = [

            // Database path
            'database' => '/local',

            // Database endpoint
            'endpoint' => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery' => true,

            // IAM config
            'iam_config' => [
                'insecure' => true,
            ],

            'credentials' => new AnonymousAuthentication()
        ];
        $ydb = new Ydb($config, new \YdbPlatform\Ydb\Logger\SimpleStdLogger(7));
        $table = $ydb->table();

        $table->retrySession(function (\YdbPlatform\Ydb\Session $session){
            $session->createTable(
                'scan_table',
                YdbTable::make()
                    ->addColumn('id', 'UINT64')
                    ->primaryKey('id')
            );
        }, true);

        $yql = 'SELECT id
FROM scan_table
WHERE id = 1;';

        $scanWithOutMode = $table->scanQuery($yql);
        $scanWithExplainMode = $table->scanQuery($yql, [], ScanQueryMode::MODE_EXPLAIN);
        $scanWithExecMode = $table->scanQuery($yql, [], ScanQueryMode::MODE_EXEC);

        // These `foreach` needs for requests
        foreach ($scanWithOutMode as $value){}
        foreach ($scanWithExplainMode as $value){}
        foreach ($scanWithExecMode as $value){}

        self::expectExceptionMessage("Not implemented");
        $scanWithParams = $table->scanQuery($yql, ["value"=>"some"]);
        foreach ($scanWithParams as $value){}
    }
}
