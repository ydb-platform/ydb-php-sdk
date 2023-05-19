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

    public function __construct(&$counter)
    {
        $this->counter = &$counter;
    }

    public function getTokenInfo(): TokenInfo
    {
        $this->counter++;
        if ($this->counter==2){
            throw new \Exception("Some error");
        }
        return new TokenInfo(time()+10,time()+10);
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
            'credentials' => new FakeCredentials($counter)
        ];
        $ydb = new Ydb($config);
        $table = $ydb->table();
        $session = $table->session();
        $token = MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0];
        self::assertEquals(
            1,
            $counter
        );

        $session->query('select 1 as res');
        self::assertEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0]
        );
        self::assertEquals(
            1,
            $counter
        );

        usleep(1e6);
        $session->query('select 1 as res');
        self::assertEquals(
            2,
            $counter
        );
        self::assertEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0]
        );
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
