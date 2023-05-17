<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Auth;
use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Ydb;

class TestCredentials extends Auth{

    public function getTokenInfo(): TokenInfo
    {
        return new TokenInfo(time()+1,time()+1);
    }

    public function getName(): string
    {
        return "TestCredentials";
    }
}

class MetaGetter extends \YdbPlatform\Ydb\Session{
    public static function getMeta(\YdbPlatform\Ydb\Session $session){
        return $session->meta;
    }
}

class RefreshTokenTest extends TestCase
{
    public function test(){

        $i = 0;

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
            'credentials' => new TestCredentials()
        ];
        $ydb = new Ydb($config);
        $table = $ydb->table();
        $session = $table->session();
        $token = MetaGetter::getMeta($session)["x-ydb-auth-ticket"];
        usleep(1e6);
        $session->query('select 1 as res');
        self::assertNotEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"]
        );
    }
}
