<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class CheckParamsInRetryTransactionTest extends TestCase
{
    public function testRun()
    {
        $config = [

            // Database path
            'database' => '/local',

            // Database endpoint
            'endpoint' => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery' => false,

            // IAM config
            'iam_config' => [
                'anonymous' => true,
                'insecure' => true
            ],
        ];

        $ydb = new Ydb($config);

        $table = $ydb->table();

        try {
            $table->retryTransaction(function (Session $session){}, true, null, ['idempotent'=>true]);
            throw new \Exception('retryTransaction does not throw exception');
        } catch (\YdbPlatform\Ydb\Exception $e){
            self::assertEquals(1,1);
        }

        try {
            $table->retryTransaction(function (Session $session){}, null, new RetryParams(), ['retryParams'=>new RetryParams()]);
            throw new \Exception('retryTransaction does not throw exception');
        } catch (\YdbPlatform\Ydb\Exception $e){
            self::assertEquals(1,1);
        }

    }
}
