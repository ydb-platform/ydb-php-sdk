<?php

namespace YdbPlatform\Ydb;

use Closure;
use Exception;
use Ydb\Table\Query;
use Psr\Log\LoggerInterface;
use Ydb\Table\V1\TableServiceClient as ServiceClient;
use YdbPlatform\Ydb\Contracts\SessionPoolContract;

class Table
{
    use Traits\RequestTrait;
    use Traits\ParseResultTrait;
    use Traits\TypeHelpersTrait;
    use Traits\TableHelpersTrait;
    use Traits\LoggerTrait;

    /**
     * @var ServiceClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $meta;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var SessionPoolContract
     */
    protected static $session_pool;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Ydb $ydb
     * @param LoggerInterface|null $logger
     */
    public function __construct(Ydb $ydb, LoggerInterface $logger = null)
    {
        $this->client = new ServiceClient($ydb->endpoint(), [
            'credentials' => $ydb->iam()->getCredentials(),
        ]);

        $this->meta = $ydb->meta();

        $this->path = $ydb->database();

        $this->logger = $logger;

        if (empty(static::$session_pool))
        {
            static::$session_pool = new Sessions\MemorySessionPool;
        }
    }

    /**
     * @param SessionPoolContract $manager
     */
    public function sessionPool(SessionPoolContract $manager)
    {
        static::$session_pool = $manager;
    }

    /**
     * @return ServiceClient
     */
    public function client()
    {
        return $this->client;
    }

    /**
     * @return array
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * @return string|null
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return Session
     */
    public function session()
    {
        $session = $this->takeSession();

        if (!$session)
        {
            $session = $this->createSession();
        }

        return $session;
    }

    /**
     * @return Session|null
     */
    public function takeSession()
    {
        $session = static::$session_pool->getIdleSession();

        if ($session)
        {
            return $session->take();
        }

        return null;
    }

    /**
     * @return Session
     */
    public function createSession()
    {
        $result = $this->request('CreateSession');
        $session_id = $result->getSessionId();
        $this->logger()->info('YDB: New session created [...' . substr($session_id, -6) . '].');

        $session = new Session($this, $session_id);
        static::$session_pool->addSession($session);
        return $session->take();
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function dropSession($session_id)
    {
        static::$session_pool->dropSession($session_id);
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function syncSession($session_id)
    {
        static::$session_pool->syncSession($session_id);
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionTaken($session)
    {
        static::$session_pool->sessionTaken($session);
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionReleased($session)
    {
        static::$session_pool->sessionReleased($session);
    }

    /**
     * @param Closure $closure
     * @return mixed
     * @throws Exception
     */
    public function transaction(Closure $closure)
    {
        return $this->session()->transaction($closure);
    }

    /**
     * Proxy to Session::query.
     *
     * @param string|\Ydb\Table\Query $yql
     * @return bool|QueryResult
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function query($yql)
    {
        return $this->session()->query($yql);
    }

    /**
     * Proxy to Session::exec.
     *
     * @param string|\Ydb\Table\Query $yql
     * @return bool
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function exec($yql)
    {
        $this->query($yql);

        return true;
    }

    /**
     * Proxy to Session::schemeQuery.
     *
     * @param string $yql
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function schemeQuery($yql)
    {
        return $this->session()->schemeQuery($yql);
    }

    /**
     * Proxy to Session::explainQuery.
     *
     * @param string $yql
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function explainQuery($yql)
    {
        return $this->session()->explainQuery($yql);
    }

    /**
     * Proxy to Session::prepare.
     *
     * @param string $yql
     * @return Statement
     * @throws Exception
     */
    public function prepare($yql)
    {
        return $this->session()->prepare($yql);
    }

    /**
     * Proxy to Session::readTable.
     *
     * @param string $path
     * @param array $columns
     * @param array $options
     * @return \Generator
     */
    public function readTable($path, $columns = [], $options = [])
    {
        return $this->session()->readTable($path, $columns, $options);
    }

    /**
     * Proxy to Session::createTable.
     *
     * @param string $table
     * @param mixed $columns
     * @param string|array $primary_key
     * @param array $indexes
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function createTable($table, $columns, $primary_key = 'id', $indexes = [])
    {
        return $this->session()->createTable($table, $columns, $primary_key, $indexes);
    }

    /**
     * Proxy to Session::copyTable.
     *
     * @param string $source_table
     * @param string $destination_table
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function copyTable($source_table, $destination_table)
    {
        return $this->session()->copyTable($source_table, $destination_table);
    }

    /**
     * Proxy to Session::copyTables.
     *
     * @param array $tables
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function copyTables($tables)
    {
        return $this->session()->copyTables($tables);
    }

    /**
     * Proxy to Session::dropTable.
     *
     * @param string $table
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function dropTable($table)
    {
        return $this->session()->dropTable($table);
    }

    /**
     * Proxy to Session::alterTable.
     *
     * @param string $table
     * @param array $columns
     * @param array $indexes
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function alterTable($table, $columns = [], $indexes = [])
    {
        return $this->session()->alterTable($table, $columns, $indexes);
    }

    /**
     * Proxy to Session::describeTable.
     *
     * @param string $table
     * @return array|mixed|null
     * @throws Exception
     */
    public function describeTable($table)
    {
        return $this->session()->describeTable($table);
    }

    /**
     * @param string $yql
     * @return \Generator
     */
    public function scanQuery($yql)
    {
        $q = new Query(['yql_text' => $yql]);

        return $this->streamRequest('StreamExecuteScanQuery', [
            'query' => $q,
        ]);
    }

    /**
     * @param string $table
     * @param array $rows
     * @param array $columns_types
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function bulkUpsert($table, $rows, $columns_types = [])
    {
        return $this->request('BulkUpsert', [
            'table' => $this->pathPrefix($table),
            'rows' => $this->convertBulkRows($rows, $columns_types),
        ]);
    }

    /**
     * @return array|mixed|null
     */
    public function describeTableOptions()
    {
        $result = $this->request('DescribeTableOptions');

        return $this->parseResult($result);
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function request($method, array $data = [])
    {
        return $this->doRequest('Table', $method, $data);
    }

    /**
     * @param string $method
     * @param array $data
     * @return \Generator
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function streamRequest($method, array $data = [])
    {
        return $this->doStreamRequest('Table', $method, $data);
    }

}