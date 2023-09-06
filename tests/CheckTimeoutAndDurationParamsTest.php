<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Ydb;

class CheckTimeoutAndDurationParamsTest extends TestCase
{
    public function testTimeoutAndDurationParams(){
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            // IAM config
            'iam_config'  => [
                'insecure' => true,
            ],
            'credentials' => new AnonymousAuthentication()
        ];

        $ydb = new Ydb($config, new SimpleStdLogger(7));
        $table = $ydb->table();

        $this->expectException('YdbPlatform\Ydb\Exceptions\Ydb\TimeoutException');
        $table->retrySession(function (Session $session){
            $session->query('SELECT 1;', null, [
                'operation_timeout_ms' => 1e-5
            ]);
        });

        $this->expectException('YdbPlatform\Ydb\Exceptions\Ydb\CancelledException');
        $table->retrySession(function (Session $session){
            $session->query('SELECT 1;', null, [
                'cancel_after_ms' => 1e-5
            ]);
        });
    }
}
