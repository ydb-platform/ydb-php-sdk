<?php

namespace YdbPlatform\Ydb;

use Closure;
use Psr\Log\LoggerInterface;
use YdbPlatform\Ydb\Auth\UseConfigInterface;
use YdbPlatform\Ydb\Exceptions\NonRetryableException;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionExpiredException;
use YdbPlatform\Ydb\Logger\NullLogger;
use YdbPlatform\Ydb\Retry\Retry;
use YdbPlatform\Ydb\Retry\RetryParams;

require "Version.php";

class Ydb
{
    use Traits\LoggerTrait;

    const VERSION = MAJOR.".".MINOR.".".PATCH;

    /**
     * Default gRPC load-balancing policy applied to every channel built via grpcOpts().
     * Distributes RPCs across all resolved A-records of the target DNS name. Users can
     * override per-channel via `grpc.opts.grpc.lb_policy_name` in their Ydb config.
     */
    const DEFAULT_LB_POLICY_NAME = 'round_robin';

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var string
     */
    protected $database;

    /**
     * @var array
     */
    protected $iam_config;

    /**
     * @var array
     */
    protected $grpc_config;

    /**
     * @var int|null
     */
    protected $grpcTimeout;

    /**
     * @var Iam
     */
    protected $iam;

    /**
     * @var AuthService
     */
    protected $auth;

    /**
     * @var Discovery
     */
    protected $discovery;

    /**
     * @var Scheme
     */
    protected $scheme;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var Operations
     */
    protected $operations;

    /**
     * @var Scripting
     */
    protected $scripting;

    /**
     * @var Cluster
     */
    protected $cluster;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Retry
     */
    protected $retry;

    protected $discover = false;

    /**
     * @var int
     */
    protected $discoveryInterval = 60;

    /**
     * @var \YdbPlatform\Ydb\Internal\Discovery
     */
    protected $internalDiscovery;

    /**
     * @param array $config
     * @param LoggerInterface|null $logger
     * @throws Exception
     */
    public function __construct($config = [], LoggerInterface $logger = null)
    {
        $this->endpoint = $config['endpoint'];
        $this->database = $config['database'];
        $this->iam_config = $config['iam_config'] ?? [];
        $this->grpc_config = (array) ($config['grpc'] ?? []);
        $this->grpcTimeout = $config['grpc']['timeout'] ?? null;

        if (!is_null($logger) && isset($config['logger'])){
            throw new \Exception('Logger set in 2 places');
        } else if (isset($config['logger'])) {
            $this->setLogger($config['logger']);
        } else if ($logger) {
            $this->setLogger($logger);
        } else {
            $this->setLogger(new NullLogger());
        }

        $this->retry = new Retry($this->logger);

        if(isset($config['credentials'])){
            $this->iam_config['credentials'] = $config['credentials'];
            $this->iam_config['credentials']->setLogger($this->logger());
            if ($this->iam_config['credentials'] instanceof UseConfigInterface){
                $this->iam_config['credentials']->setYdbConnectionConfig($config);
            }
        }

        $this->internalDiscovery = new Internal\Discovery(
            $this,
            $config['endpoint'],
            isset($config['discoveryTimeoutMs'])        ? (int)$config['discoveryTimeoutMs']        : Internal\Discovery::DEFAULT_TIMEOUT_MS,
            isset($config['discoveryAttemptTimeoutMs']) ? (int)$config['discoveryAttemptTimeoutMs'] : Internal\Discovery::DEFAULT_ATTEMPT_TIMEOUT_MS,
            isset($config['discoveryInitialTimeoutMs']) ? (int)$config['discoveryInitialTimeoutMs'] : Internal\Discovery::DEFAULT_INITIAL_TIMEOUT_MS,
            $this->logger
        );

        if (!empty($config['discovery']))
        {
            $this->discover = true;
            if (isset($config['discoveryInterval'])){
                $this->discoveryInterval = $config['discoveryInterval'];
            }

            $this->applyDiscoveryResult($this->internalDiscovery->initialListEndpoints());
        }

        $this->logger()->info('YDB: Initialized');
    }

    /**
     * @return string|null
     */
    public function endpoint()
    {
        return $this->endpoint;
    }

    /**
     * @return string|null
     */
    public function database()
    {
        return $this->database;
    }

    public function meta(): array
    {
        $meta = [
            'x-ydb-database' => [$this->database],
            'x-ydb-sdk-build-info' => ['ydb-php-sdk/' . self::VERSION],
        ];

        if (!$this->iam()->config('anonymous'))
        {
            $meta['x-ydb-auth-ticket'] = [$this->iam()->token()];
        }

        return $meta;
    }

    public function grpcOpts(): array
    {
        $grpcOpts = (array) ($this->grpc_config['opts'] ?? []);
        $grpcOpts['credentials'] = $this->iam()->getCredentials();
        if (!isset($grpcOpts['grpc.lb_policy_name'])) {
            $grpcOpts['grpc.lb_policy_name'] = self::DEFAULT_LB_POLICY_NAME;
        }

        return $grpcOpts;
    }

    /**
     * Get gRPC timeout in microseconds
     *
     * @return int|null
     */
    public function getGrpcTimeout()
    {
        return $this->grpcTimeout;
    }

    /**
     * Background discovery (discoveryTimeoutMs budget, retries any error). Used from
     * checkDiscovery / handleGrpcStatus on the request path. Startup discovery runs
     * from the constructor via Internal\Discovery::initialListEndpoints().
     *
     * @return void
     * @throws Exception
     */
    public function discover()
    {
        $this->applyDiscoveryResult($this->internalDiscovery->listEndpoints());
    }

    /**
     * Apply a ListEndpoints result to the cluster and the current endpoint. Shared
     * between the startup path (constructor) and the background path (discover()).
     *
     * @param array $endpoints
     * @return void
     */
    protected function applyDiscoveryResult(array $endpoints)
    {
        if (empty($endpoints)) {
            return;
        }
        $this->cluster()->sync($endpoints);
        $clusterEndpoints = array_map(function ($e) {
            return $e["address"] . ":" . $e["port"];
        }, $endpoints);
        if (!in_array($this->endpoint, $clusterEndpoints, true)) {
            $this->endpoint = $clusterEndpoints[array_rand($clusterEndpoints)];
        }
    }

    /**
     * @return Cluster
     */
    public function cluster()
    {
        if (!isset($this->cluster))
        {
            $this->cluster = new Cluster($this);
        }

        return $this->cluster;
    }

    /**
     * @return string
     */
    public function token()
    {
        return $this->iam()->token();
    }

    /**
     * @return Iam
     */
    public function iam()
    {
        if (!isset($this->iam))
        {
            $this->iam = new Iam($this->iam_config, $this->logger);
        }

        return $this->iam;
    }

    /**
     * @return Discovery
     */
    public function discovery()
    {
        if (!isset($this->discovery))
        {
            $this->discovery = new Discovery($this, $this->logger);
        }

        return $this->discovery;
    }

    /**
     * @return AuthService
     */
    public function auth()
    {
        if (!isset($this->auth))
        {
            $this->auth = new AuthService($this, $this->logger);
        }

        return $this->auth;
    }

    /**
     * @return Table
     */
    public function table()
    {
        if (!isset($this->table))
        {
            $this->table = new Table($this, $this->logger, $this->retry);
        }

        return $this->table;
    }

    /**
     * @return Scheme
     */
    public function scheme()
    {
        if (!isset($this->scheme))
        {
            $this->scheme = new Scheme($this, $this->logger);
        }

        return $this->scheme;
    }

    /**
     * @return Operations
     */
    public function operations()
    {
        if (!isset($this->operations))
        {
            $this->operations = new Operations($this, $this->logger);
        }

        return $this->operations;
    }

    /**
     * @return Scripting
     */
    public function scripting()
    {
        if (!isset($this->scripting))
        {
            $this->scripting = new Scripting($this, $this->logger);
        }

        return $this->scripting;
    }

    /**
     * @param RetryParams $params
     */
    public function setRetryParams(RetryParams $params): void
    {
        $this->retry = $this->retry->withParams($params);
    }

    /**
     * @param Closure $userFunc
     * @param bool $idempotent
     * @param RetryParams|null $params
     * @return mixed
     * @throws Exception
     */
    public function retry(Closure $userFunc, bool $idempotent = false, RetryParams $params = null){
        return $this->retry->withParams($params)->retry(function () use ($userFunc){
            try{
                $result = $userFunc($this);
                return $result;
            } catch (Exception $exception) {
                throw $exception;
            }
        }, $idempotent);
    }

    /**
     * @return bool
     */
    public function needDiscovery(): bool
    {
        return $this->discover;
    }

    /**
     * @return int
     */
    public function discoveryInterval()
    {
        return $this->discoveryInterval;
    }

    /**
     * @return LoggerInterface|NullLogger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return void
     */
    protected function setLogger(LoggerInterface $logger){
        $this->logger = $logger;
    }

}
