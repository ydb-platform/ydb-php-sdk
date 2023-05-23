<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Auth;
use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Ydb;

class FakeCredentials extends Auth {

    /**
     * @var int
     */
    protected $counter;

    protected $tokenLiveTime;

    public function __construct(&$counter, &$tokenLiveTime)
    {
        $this->counter = &$counter;
        $this->tokenLiveTime = &$tokenLiveTime;
    }

    public function getTokenInfo(): TokenInfo
    {
        $this->counter++;
        if ($this->counter==2){
            throw new \Exception("Some error");
        }
        return new TokenInfo(time()+$this->tokenLiveTime,time()+$this->tokenLiveTime);
    }

    public function getName(): string
    {
        return "FakeCredentials";
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

        $counter = 0;

        $TOKEN_LIVE_TIME = 10;

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
            'credentials' => new FakeCredentials($counter, $TOKEN_LIVE_TIME)
        ];
        $ydb = new Ydb($config);
        $table = $ydb->table();
        $session = $table->createSession();
        $token = MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0];
        self::assertEquals(
            1,
            $counter
        );

        // Check that the token will not be updated until a refresh time
        $session->query('select 1 as res');
        self::assertEquals(
            1,
            $counter
        );
        self::assertEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0]
        );
        // Check that sdk used old token when failed refreshing
        usleep(TokenInfo::_PRIVATE_REFRESH_RATIO*$TOKEN_LIVE_TIME*1000*1000); // waiting 10% from token live time
        $session->query('select 1 as res');
        self::assertEquals(
            2,
            $counter
        );
        self::assertEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0]
        );

        // Check that token refreshed
        $session->query('select 1 as res');
        self::assertEquals(
            3,
            $counter
        );
        self::assertNotEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0]
        );
    }
}
