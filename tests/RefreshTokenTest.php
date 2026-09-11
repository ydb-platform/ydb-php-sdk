<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Auth;
use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Iam;
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
        // Token value is unique per call, so a refreshed token is distinguishable from the previous one.
        return new TokenInfo("token-" . $this->counter, time()+$this->tokenLiveTime);
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

class IamRefreshForcer extends Iam {
    /**
     * Move the refresh deadline of the current token into the past, so the next
     * request triggers a token refresh without waiting for wall-clock time.
     */
    public static function forceRefresh(Iam $iam)
    {
        $iam->refresh_at = time() - 1;
    }
}

class RefreshTokenTest extends TestCase
{
    public function testRefreshToken(){

        $counter = 0;

        // Long enough that the token never becomes due for refresh on its own
        // during the test, so the test does not depend on runner speed.
        $TOKEN_LIVE_TIME = 3600;

        // Isolate the on-disk token cache from other tests and previous runs:
        // the cache key is derived from the config, so a token left by an earlier
        // run of this very test would otherwise be reused and skip the first fetch.
        $tempDir = sys_get_temp_dir() . '/ydb-refresh-token-test-' . bin2hex(random_bytes(4));
        mkdir($tempDir, 0700, true);

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
                'temp_dir' => $tempDir,
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
        IamRefreshForcer::forceRefresh($ydb->iam());
        $session->query('select 1 as res');
        self::assertEquals(
            2,
            $counter
        );
        self::assertEquals(
            $token,
            MetaGetter::getMeta($session)["x-ydb-auth-ticket"][0]
        );

        // Check that token refreshed on the next attempt
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
