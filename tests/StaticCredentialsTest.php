<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AccessTokenAuthentication;
use YdbPlatform\Ydb\Auth\Implement\StaticAuthentication;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Ydb;

class StaticCredentialsTest extends TestCase
{
    public function testGetAuthToken()
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
                'insecure' => true,
            ],
            'credentials' => new StaticAuthentication('testuser', 'test_password')
        ];
        $ydb = new Ydb($config, new SimpleStdLogger(7));
        $ydb->table()->query("SELECT 1;");
        self::assertNotEquals("", $ydb->token());
    }
}
