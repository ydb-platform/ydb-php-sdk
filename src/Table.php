<?php

namespace YdbPlatform\Ydb;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;
use Ydb\Table\Query;
use Ydb\Table\V1\TableServiceClient as ServiceClient;
use YdbPlatform\Ydb\Contracts\SessionPoolContract;
use YdbPlatform\Ydb\Enums\ScanQueryMode;
use YdbPlatform\Ydb\Exceptions\Grpc\InvalidArgumentException;
use YdbPlatform\Ydb\Exceptions\Grpc\UnknownException;
use YdbPlatform\Ydb\Exceptions\NonRetryableException;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionExpiredException;
use YdbPlatform\Ydb\Retry\Backoff;
use YdbPlatform\Ydb\Retry\Retry;
use YdbPlatform\Ydb\Retry\RetryParams;

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
     * @var Iam
     */
    protected $credentials;

    /**
     * @var Retry
     */
    private $retry;

    /**
     * @var Ydb
     */
    protected $ydb;

    /**
     * @param Ydb $ydb
     * @param LoggerInterface|null $logger
     */
    public function __construct(Ydb $ydb, LoggerInterface $logger = null, Retry &$retry)
    {
        $this->ydb = $ydb;

        $this->client = new ServiceClient($ydb->endpoint(), $ydb->grpcOpts());

        $this->meta = $ydb->meta();

        $this->credentials = $ydb->iam();

        $this->path = $ydb->database();

        $this->logger = $logger;

        $this->retry = $retry;

        if (empty(static::$session_pool))
        {
            static::$session_pool = new Sessions\MemorySessionPool($retry);
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
     * @return Ydb
     */
    public function ydb()
    {
        return $this->ydb;
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
    public function transaction(Closure $closure, string $mode = 'serializable_read_write')
    {
        return $this->session()->transaction($closure, $mode);
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
     * @return Iam
     */
    public function credentials(): Iam
    {
        return $this->credentials;
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
     * @param array $parameters
     * @param int $mode
     * @return \Generator
     * @throws Exception
     */
    public function scanQuery($yql, $parameters = [], $mode = ScanQueryMode::MODE_EXEC)
    {
        if($parameters != []){
            throw new Exception("Not implemented");
        }

        $q = new Query(['yql_text' => $yql]);

        return $this->streamRequest('StreamExecuteScanQuery', [
            'query' => $q,
            'mode'  => $mode
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

    /**
     * @param RetryParams $params
     */
    public function setRetryParams(RetryParams $params): void
    {
        $this->retry = $this->retry->withParams($params);
    }

    /**
     * @throws NonRetryableException
     * @throws RetryableException
     */
    public function retrySession(Closure $userFunc, bool $idempotent = false, RetryParams $params = null){
        return $this->retry->withParams($params)->retry(function () use ($userFunc){
            $session = null;
            try {
                $session = $this->session();
                return $userFunc($session);
            } catch (Exception $exception){
                if ($session != null && $this->deleteSession(get_class($exception))){
                    $this->dropSession($session->id());
                }
                throw $exception;
            }
        }, $idempotent);

    }

    public function retryTransaction(Closure $userFunc, bool $idempotent = null, RetryParams $params = null, array $options = []){
        if ($options == null) {
            $options = [];
        }

        if (isset($options['idempotent']) && !is_null($idempotent)){
            throw new \YdbPlatform\Ydb\Exception('Idempotent flag set in 2 params');
        }
        else if (!is_null($idempotent)) {
            $options['idempotent'] = $idempotent;
        } else {
            $options['idempotent'] = false;
        }

        if (isset($options['retryParams']) && !is_null($params)){
            throw new \YdbPlatform\Ydb\Exception('RetryParams set in 2 params');
        }
        else if (!isset($options['retryParams'])) {
            $options['retryParams'] = $params;
        }

        if (!isset($options['callback_on_error'])) {
            $options['callback_on_error'] = function (\Exception $exception) {};
        }

        $txMode = $options['tx_mode'] ?? 'serializable_read_write';

        return $this->retrySession(function (Session $session) use ($txMode, $options, $userFunc) {
            try {
                $session->beginTransaction($txMode);
                $result = $userFunc($session);
                $session->commitTransaction();
                return $result;
            } catch (\Exception $exception) {
                $options['callback_on_error']($exception);
                try {
                    $session->rollbackTransaction();
                } catch (Exception $e) {
                }
                throw $exception;
            }
        }, $options['idempotent'], $options['retryParams']);
    }

    protected function deleteSession(string $exception): bool
    {
        return in_array($exception, self::$deleteSession);
    }

    private static $deleteSession = [
        \YdbPlatform\Ydb\Exceptions\Grpc\CanceledException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\UnknownException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\InvalidArgumentException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\NotFoundException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\AlreadyExistsException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\PermissionDeniedException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\FailedPreconditionException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\AbortedException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\UnimplementedException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\InternalException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\DataLossException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\UnauthenticatedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\StatusCodeUnspecified::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\SessionExpiredException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException::class
    ];

}
