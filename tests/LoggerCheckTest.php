<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Logger\NullLogger;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Ydb;

class LoggerCheckTest extends TestCase{

    public function testExceptExceptionInConfigs(){
        $config = [
            'logger' => new SimpleStdLogger(7)
        ];
        $this->expectException('Exception');
        new Ydb($config, new NullLogger());
    }
    public function testCheckUseLoggerFromConfig(){
        $logger = new SimpleStdLogger(7);
        $config = [
            'logger' => $logger
        ];
        $ydb = new Ydb($config);
        $this->assertEquals($logger, $ydb->getLogger());
    }
    public function testCheckUseLoggerFromParam(){
        $logger = new SimpleStdLogger(7);
        $config = [];
        $ydb = new Ydb($config, $logger);
        $this->assertEquals($logger, $ydb->getLogger());
    }
    public function testCheckUseNullLogger(){
        $config = [];
        $ydb = new Ydb($config);
        $this->assertInstanceOf(NullLogger::class, $ydb->getLogger());
    }

    public function testThrowExceptionOnNonLoggerObject(){
        $config = [
            'logger'    => new AnonymousAuthentication()
        ];
        $this->expectException('TypeError');
        $ydb = new Ydb($config);
        $this->assertInstanceOf(NullLogger::class, $ydb->getLogger());
    }
}
