<?php
namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\ReadFromTextCredentials;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class CheckReadTokenFileTest extends TestCase{
    public function testReadTokenFile(){
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            'credentials'  => new ReadFromTextCredentials("./tests/token.txt")
        ];

        $ydb = new Ydb($config);
        self::assertEquals('test-example-token',
            $ydb->iam()->token()
        );
    }
}