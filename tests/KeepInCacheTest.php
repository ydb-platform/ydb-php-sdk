<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Types\Int64Type;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbQuery;

class KeepInCacheTest extends TestCase
{
    /**
     * @var Ydb
     */
    protected $ydb;
    /**
     * @var Table
     */
    protected $table;
    protected $yqlPrepare = '
declare $pk as Int64;
select $pk;';
    protected $yql = 'select 1;';

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
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
            'credentials' => new AnonymousAuthentication()
        ];
        $this->ydb = new Ydb($config, new SimpleStdLogger(SimpleStdLogger::DEBUG));
        $this->table = $this->ydb->table();
        $this->sessionId = $this->table->createSession()->id();
    }

    public function testPreparedQueryWithParamsWithoutConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $prepared_query = $this->table->session()->prepare($this->yqlPrepare);
        $x = 2;
        $result = $prepared_query->execute([
            'pk' => $x,
        ]);
        self::assertEquals(true, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testPreparedQueryWithParamsWithConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $session->keepInCache(false);
        $prepared_query = $this->table->session()->prepare($this->yqlPrepare);
        $x = 2;
        $result = $prepared_query->execute([
            'pk' => $x,
        ]);
        self::assertEquals(false, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testPreparedQueryWithoutParamsWithoutConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $prepared_query = $this->table->session()->prepare($this->yql);
        $result = $prepared_query->execute();
        self::assertEquals(false, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testPreparedQueryWithoutParamsWithConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $session->keepInCache(true);
        $prepared_query = $this->table->session()->prepare($this->yql);
        $result = $prepared_query->execute();
        self::assertEquals(true, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testNewQueryWithParamsWithoutConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $query = $session->newQuery($this->yqlPrepare);
        $query->parameters([
            '$pk' => (new Int64Type(2))->toTypedValue(),
        ]);
        $query->beginTx('stale');
        $result = $query->execute();
        self::assertEquals(true, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testNewQueryWithoutParamsWithoutConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $query = $session->newQuery($this->yql);
        $query->parameters();
        $query->beginTx('stale');
        $result = $query->execute();
        self::assertEquals(false, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testNewQueryWithParamsWithConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $query = $session->newQuery($this->yqlPrepare);
        $query->parameters([
            '$pk' => (new Int64Type(2))->toTypedValue(),
        ]);
        $query->beginTx('stale');
        $query->keepInCache(false);
        $result = $query->execute();
        self::assertEquals(false, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }

    public function testNewQueryWithoutParamsWithConfig()
    {
        $session = new EditablePreparedSession($this->table, "");
        SessionWithEditable::setId($session, $this->sessionId);
        SessionWithEditable::setIdle($session);
        TableWithEditableSessions::addSession($this->table, $session);
        $query = $session->newQuery($this->yql);
        $query->parameters();
        $query->beginTx('stale');
        $query->keepInCache(true);
        $result = $query->execute();
        self::assertEquals(true, $result);
        TableWithEditableSessions::removeSession($this->table, $session);
    }
}

class TableWithEditableSessions extends Table
{
    public static function addSession(Table $table, Session $session)
    {
        $table::$session_pool->addSession($session);
    }
    public static function removeSession(Table $table, Session $session)
    {
        $table::$session_pool->dropSession($session->id());
    }
}

class SessionWithEditable extends Session
{
    public static function setId(Session $session, string $id)
    {
        $session->session_id = $id;
    }

    public static function setIdle(Session $session)
    {
        $session->is_busy = false;
    }

    public function executeQuery(YdbQuery $query)
    {
        return $query->getRequestData()['query_cache_policy']->getKeepInCache();
    }

}

class EditablePreparedSession extends Session
{
    public static function setId(Session $session, string $id)
    {
        $session->session_id = $id;
    }

    public static function setIdle(Session $session)
    {
        $session->is_busy = false;
    }


    public function executeQuery(YdbQuery $query)
    {
        return $query->getRequestData()['query_cache_policy']->getKeepInCache();
    }

}
