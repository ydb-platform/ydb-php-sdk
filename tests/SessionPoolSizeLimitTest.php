<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Contracts\SessionPoolContract;
use YdbPlatform\Ydb\Exceptions\Ydb\ClientResourceExhaustedException;
use YdbPlatform\Ydb\Logger\NullLogger;
use YdbPlatform\Ydb\Retry\Retry;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Sessions\MemorySessionPool;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Ydb;

class SessionPoolCreateSessionResult
{
    private $sessionId;

    public function __construct($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }
}

class SessionPoolLimitTable extends Table
{
    private static $nextSessionId = 1;
    private $failNextCreate = false;

    public function failNextCreate()
    {
        $this->failNextCreate = true;
    }

    protected function request($method, array $data = [])
    {
        if ($method === 'CreateSession') {
            if ($this->failNextCreate) {
                $this->failNextCreate = false;
                throw new \RuntimeException('CreateSession failed');
            }

            return new SessionPoolCreateSessionResult('session-' . self::$nextSessionId++);
        }

        if ($method === 'DeleteSession') {
            return true;
        }

        throw new \RuntimeException('Unexpected request: ' . $method);
    }
}

class SessionPoolSessionManager extends Session
{
    public static function markDead(Session $session)
    {
        $session->is_alive = false;
    }
}

class SessionPoolWithoutCapacity implements SessionPoolContract
{
    public $sessions = [];
    public $synced = [];
    public $taken = 0;

    public function getIdleSession()
    {
        return null;
    }

    public function addSession(Session $session)
    {
        $this->sessions[$session->id()] = $session;
    }

    public function dropSession($sessionId)
    {
        unset($this->sessions[$sessionId]);
    }

    public function syncSession($sessionId)
    {
        $this->synced[] = $sessionId;
    }

    public function sessionTaken(Session $session)
    {
        $this->taken++;
    }

    public function sessionReleased(Session $session)
    {
    }
}

class SessionPoolSizeLimitTest extends TestCase
{
    public function testConfiguredLimitStopsCreatingSessions()
    {
        $table = $this->createTable(1);
        $session = $table->createSession();

        try {
            $table->createSession();
            self::fail('Expected the session pool limit to be enforced');
        } catch (ClientResourceExhaustedException $exception) {
            self::assertSame(
                'YDB session pool size limit of 1 has been reached',
                $exception->getMessage()
            );
        } finally {
            $this->removeSession($table, $session);
        }
    }

    public function testReleasedSessionIsReusedAtTheLimit()
    {
        $table = $this->createTable(1);
        $session = $table->createSession();
        $session->release();

        $reusedSession = $table->session();

        self::assertSame($session, $reusedSession);
        $this->removeSession($table, $reusedSession);
    }

    public function testCreationReservationIsReleasedAfterFailure()
    {
        $table = $this->createTable(1);
        $table->failNextCreate();

        try {
            $table->createSession();
            self::fail('Expected CreateSession to fail');
        } catch (\RuntimeException $exception) {
            self::assertSame('CreateSession failed', $exception->getMessage());
        }

        $session = $table->createSession();
        self::assertNotNull($session);
        $this->removeSession($table, $session);
    }

    public function testPoolsAreIsolatedBetweenClients()
    {
        $firstTable = $this->createTable(1);
        $secondTable = $this->createTable(1);

        $firstSession = $firstTable->createSession();
        $secondSession = $secondTable->createSession();

        self::assertNotSame($firstSession, $secondSession);
        $this->removeSession($firstTable, $firstSession);
        $this->removeSession($secondTable, $secondSession);
    }

    public function testLimitIsSharedBetweenTablesOfSameClient()
    {
        $ydb = $this->createYdb(1);
        $firstTable = $this->createTableForYdb($ydb);
        $secondTable = $this->createTableForYdb($ydb);
        $session = $firstTable->createSession();

        try {
            $secondTable->createSession();
            self::fail('Expected the client session pool limit to be enforced');
        } catch (ClientResourceExhaustedException $exception) {
            self::assertSame(
                'YDB session pool size limit of 1 has been reached',
                $exception->getMessage()
            );
        } finally {
            $this->removeSession($firstTable, $session);
        }
    }

    public function testPoolIsUnlimitedByDefault()
    {
        $table = $this->createTable(null);

        $firstSession = $table->createSession();
        $secondSession = $table->createSession();

        self::assertNotSame($firstSession, $secondSession);
        $this->removeSession($table, $firstSession);
        $this->removeSession($table, $secondSession);
    }

    public function testMemoryPoolRejectsInvalidLimit()
    {
        $logger = new NullLogger();
        $retry = new Retry($logger);

        $this->expectException(\InvalidArgumentException::class);
        new MemorySessionPool($retry, 0);
    }

    public function testMemoryPoolRejectsAddingPastLimitWithoutReservation()
    {
        $table = $this->createTable(null);
        $logger = new NullLogger();
        $retry = new Retry($logger);
        $pool = new MemorySessionPool($retry, 1);
        $firstSession = new Session($table, 'direct-session-1');
        $secondSession = new Session($table, 'direct-session-2');

        $pool->addSession($firstSession);

        try {
            $pool->addSession($secondSession);
            self::fail('Expected the session pool limit to be enforced');
        } catch (ClientResourceExhaustedException $exception) {
            self::assertSame(
                'YDB session pool size limit of 1 has been reached',
                $exception->getMessage()
            );
        } finally {
            SessionPoolSessionManager::markDead($firstSession);
            SessionPoolSessionManager::markDead($secondSession);
        }
    }

    public function testCustomPoolWithoutCapacityContractIsSharedByClientTables()
    {
        $ydb = $this->createYdb(1);
        $firstTable = $this->createTableForYdb($ydb);
        $secondTable = $this->createTableForYdb($ydb);
        $pool = new SessionPoolWithoutCapacity();
        $firstTable->sessionPool($pool);

        $session = $secondTable->createSession();

        self::assertSame($session, $pool->sessions[$session->id()]);
        self::assertSame(1, $pool->taken);

        $secondTable->syncSession($session->id());
        self::assertSame([$session->id()], $pool->synced);

        $this->removeSession($secondTable, $session);
        self::assertArrayNotHasKey($session->id(), $pool->sessions);
    }

    /**
     * @dataProvider invalidLimitProvider
     */
    public function testInvalidLimitIsRejected($limit)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createYdb($limit);
    }

    public function invalidLimitProvider()
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'boolean' => [true],
            'fraction' => [1.5],
            'non-numeric string' => ['ten'],
        ];
    }

    private function createTable($maxSize)
    {
        return $this->createTableForYdb($this->createYdb($maxSize));
    }

    private function createTableForYdb(Ydb $ydb)
    {
        $logger = new NullLogger();
        $retry = new Retry($logger);

        return new SessionPoolLimitTable($ydb, $logger, $retry);
    }

    private function createYdb($maxSize)
    {
        $config = [
            'database' => '/local',
            'endpoint' => 'localhost:2136',
            'discovery' => false,
            'iam_config' => [
                'insecure' => true,
            ],
            'credentials' => new AnonymousAuthentication(),
        ];

        if (!is_null($maxSize)) {
            $config['sessionPoolMaxSize'] = $maxSize;
        }

        return new Ydb($config);
    }

    private function removeSession(Table $table, Session $session)
    {
        SessionPoolSessionManager::markDead($session);
        $table->dropSession($session->id());
    }
}
