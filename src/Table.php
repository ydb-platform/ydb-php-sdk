<?php

namespace YdbPlatform\Ydb;

use Closure;
use Exception;
use YdbPlatform\Ydb\Logger\LoggerInterface;
use Ydb\Table\Query;
use Ydb\Table\V1\TableServiceClient as ServiceClient;
use YdbPlatform\Ydb\Contracts\SessionPoolContract;
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

        $this->client = new ServiceClient($ydb->endpoint(), [
            'credentials' => $ydb->iam()->getCredentials(),
        ]);

        $this->meta = $ydb->meta();

        $this->credentials = $ydb->iam();

        $this->path = $ydb->database();

        $this->logger = $logger;

        $this->retry = $retry;

        if (empty(static::$session_pool)) {
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
        $this->logger->debug("YDB DEBUG run takeSession");
        $session = $this->takeSession();

        if (!$session) {
            $this->logger->debug("YDB DEBUG run createSession");
            $session = $this->createSession();
        }

        $this->logger->debug("YDB DEBUG return session");
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

        if ($session) {
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
    public function retrySession(Closure $userFunc, bool $idempotent = false, RetryParams $params = null)
    {
        $this->logger->debug("YDB DEBUG start retrySession");
        return $this->retry->withParams($params)->retry(function () use ($userFunc) {
            $session = null;
            try {
                $this->logger->debug("YDB DEBUG run session()");
                $session = $this->session();
                $this->logger->debug("YDB DEBUG run \$userFunc(\$session) in retrySession");
                return $userFunc($session);
            } catch (Exception $exception) {
                $this->logger->debug("YDB DEBUG catch ". get_class($exception) ." in retrySession. Can delete session: "
                    .($session != null && $this->deleteSession(get_class($exception))));
                if ($session != null && $this->deleteSession(get_class($exception))) {
                    try {
                        $this->logger->debug("YDB DEBUG run dropSession in retrySession in catch");
                        $this->dropSession($session->id());
                    } catch (\YdbPlatform\Ydb\Exception $except) {
                        $this->logger->error("YDB: Failed to fetch session");
                    }
                }
                throw $exception;
            }
        }, $idempotent);

    }

    public function retryTransaction(Closure $userFunc, bool $idempotent = false, RetryParams $params = null)
    {
        $this->logger->prefix = bin2hex(random_bytes(round(lcg_value() * 20)));
        $this->logger->debug("YDB DEBUG start retryTransaction");
        return $this->retrySession(function (Session $session) use ($userFunc) {
            try {
                $this->logger->debug("YDB DEBUG run beginTransaction in retryTransaction");
                $session->beginTransaction();
                $this->logger->debug("YDB DEBUG run \$userFunc(\$session) in retryTransaction");
                $result = $userFunc($session);
                $this->logger->debug("YDB DEBUG run commitTransaction in retryTransaction");
                $session->commitTransaction();
                return $result;
            } catch (Exception $exception) {
                $this->logger->debug("YDB DEBUG catch ". get_class($exception) ." in retryTransaction");
                try {
                    $this->logger->debug("YDB DEBUG run rollbackTransaction in retryTransaction");
                    $session->rollbackTransaction();
                } catch (Exception $e) {
                    $this->logger->debug("YDB DEBUG catch ". get_class($e) ." in rollbackTransaction");
                }
                throw $exception;
            }
        }, $idempotent, $params);

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
