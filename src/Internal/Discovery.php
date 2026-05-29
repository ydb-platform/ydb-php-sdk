<?php

namespace YdbPlatform\Ydb\Internal;

use Psr\Log\LoggerInterface;
use Ydb\Discovery\ListEndpointsRequest;
use Ydb\Discovery\V1\DiscoveryServiceClient as ServiceClient;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use YdbPlatform\Ydb\Traits\LoggerTrait;
use YdbPlatform\Ydb\Traits\ParseResultTrait;
use YdbPlatform\Ydb\Traits\RequestTrait;
use YdbPlatform\Ydb\Ydb;

/**
 * @internal
 */
class Discovery
{
    use RequestTrait;
    use ParseResultTrait;
    use LoggerTrait;

    const DEFAULT_TIMEOUT_MS         = 5000;
    const DEFAULT_ATTEMPT_TIMEOUT_MS = 1000;
    const DEFAULT_INITIAL_TIMEOUT_MS = 5000;

    const BACKOFF_SLOT_MS = 20;
    const BACKOFF_CEILING = 5;

    /**
     * @var ServiceClient|null Null until the first listEndpoints() call.
     */
    protected $client;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var string|null
     */
    protected $bootstrapEndpoint;

    /**
     * @var int
     */
    protected $timeoutMs;

    /**
     * @var int
     */
    protected $attemptTimeoutMs;

    /**
     * @var int
     */
    protected $initialTimeoutMs;

    /**
     * @var callable|null Null in production — the built-in ServiceClient ctor is used
     *                    inline (see makeClient). Tests may inject a callable; that
     *                    callable may be a Closure but only the test object holds it,
     *                    and tests aren't serialized. Keeping null in production avoids
     *                    putting a Closure into the iam_config graph of the inner Ydb
     *                    used by StaticAuthentication, which Iam::parseConfig serializes
     *                    for token-cache filename hashing.
     */
    protected $clientFactory;

    /**
     * Ctor only stores configuration. It does NOT touch credentials, build a gRPC stub,
     * or fetch a token — all of that happens on the first listEndpoints() call. This
     * lets Ydb construct the object unconditionally without side effects when discovery
     * isn't enabled.
     */
    public function __construct(
        Ydb $ydb,
        ?string $bootstrapEndpoint,
        int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        int $attemptTimeoutMs = self::DEFAULT_ATTEMPT_TIMEOUT_MS,
        int $initialTimeoutMs = self::DEFAULT_INITIAL_TIMEOUT_MS,
        LoggerInterface $logger = null,
        callable $clientFactory = null
    ) {
        $this->ydb = $ydb;
        $this->logger = $logger;
        $this->bootstrapEndpoint = $bootstrapEndpoint;
        $this->timeoutMs = $timeoutMs;
        $this->attemptTimeoutMs = $attemptTimeoutMs;
        $this->initialTimeoutMs = $initialTimeoutMs;
        $this->clientFactory = $clientFactory;
    }

    /**
     * Background discovery: budget = timeoutMs, retries any \Throwable from the RPC
     * itself until the budget is exhausted. Used by checkDiscovery / handleGrpcStatus
     * from the request path.
     *
     * Note: client-construction errors (e.g., misconfigured iam such as a missing
     * service file or key) propagate immediately and are NOT retried — they happen
     * outside the per-attempt try/catch by design.
     *
     * @return array
     * @throws \Throwable
     */
    public function listEndpoints(): array
    {
        return $this->runRetryLoop($this->timeoutMs, true);
    }

    /**
     * Startup discovery: budget = initialTimeoutMs, fail-fast on non-retryable errors.
     * Used only from the Ydb constructor.
     *
     * Note: client-construction errors (e.g., misconfigured iam such as a missing
     * service file or key) propagate immediately and are NOT retried — they happen
     * outside the per-attempt try/catch by design.
     *
     * @return array
     * @throws \Throwable
     */
    public function initialListEndpoints(): array
    {
        return $this->runRetryLoop($this->initialTimeoutMs, false);
    }

    /**
     * @param int  $budgetMs        Total time budget for the loop (ms).
     * @param bool $retryAllErrors  true → retry any \Throwable; false → rethrow
     *                              non-RetryableException immediately.
     * @return array
     * @throws \Throwable
     */
    private function runRetryLoop(int $budgetMs, bool $retryAllErrors): array
    {
        if ($this->client === null) {
            $this->initClient();
        }

        $deadline = microtime(true) + $budgetMs / 1000;
        $attempt = 0;
        $last = null;
        while (microtime(true) < $deadline) {
            if ($attempt > 0) {
                $this->backoff($attempt);
                $this->recreateClient();
            }
            try {
                return $this->doListEndpoints();
            } catch (\Throwable $e) {
                $last = $e;
                if (!$retryAllErrors && !($e instanceof RetryableException)) {
                    throw $e;
                }
                $this->logger()->warning('internal discovery attempt ' . ($attempt + 1) . ' failed: ' . $e->getMessage());
            }
            $attempt++;
        }
        throw $last !== null ? $last : new \Exception('internal discovery failed');
    }

    /**
     * Pass-through of Ydb::grpcOpts() plus 'force_new' when requested.
     */
    public function buildClientOpts(bool $forceNew): array
    {
        $opts = $this->ydb->grpcOpts();
        if ($forceNew) {
            $opts['force_new'] = true;
        }
        return $opts;
    }

    /**
     * One-shot client construction called lazily on the first listEndpoints() call. No
     * force_new — gRPC may reuse a cached channel for the bootstrap endpoint if one
     * happens to exist.
     */
    protected function initClient(): void
    {
        $this->client = $this->makeClient(false);
    }

    /**
     * Called from the retry loop after an error. Closes the previous client and builds
     * a new one with `force_new` so gRPC c-core does a fresh DNS resolution rather than
     * reusing the cached persistent channel.
     */
    protected function recreateClient(): void
    {
        if (isset($this->client) && $this->client !== null) {
            $this->client->close();
        }
        $this->client = $this->makeClient(true);
    }

    private function makeClient(bool $forceNew)
    {
        $opts = $this->buildClientOpts($forceNew);
        if ($this->clientFactory !== null) {
            $factory = $this->clientFactory;
            return $factory($this->bootstrapEndpoint, $opts);
        }
        return new ServiceClient($this->bootstrapEndpoint, $opts);
    }

    /**
     * @return array
     * @throws \Throwable
     */
    protected function doListEndpoints(): array
    {
        // Fresh meta per call: Ydb::meta() includes 'x-ydb-auth-ticket' for non-anonymous
        // auth via Iam::token() (cached/refreshed by Iam itself).
        $meta = $this->ydb->meta();

        $request = new ListEndpointsRequest(['database' => $this->ydb->database()]);

        $call = $this->client->ListEndpoints($request, $meta, ['timeout' => $this->attemptTimeoutMs * 1000]);
        list($response, $status) = $call->wait();

        if (isset($status->code) && $status->code !== 0) {
            $class = isset(self::$grpcExceptions[$status->code]) ? self::$grpcExceptions[$status->code] : \Exception::class;
            $name = isset(self::$grpcNames[$status->code]) ? self::$grpcNames[$status->code] : (string)$status->code;
            $details = isset($status->details) ? $status->details : '';
            throw new $class('Discovery ListEndpoints (GRPC_' . $name . '): ' . $details);
        }

        $result = $this->processResponse('Discovery', 'ListEndpoints', $response, '\\Ydb\\Discovery\\ListEndpointsResult');
        return $this->parseResult($result, 'endpoints', []);
    }

    /**
     * @param int $attempt 1-based retry index.
     */
    protected function backoff(int $attempt): void
    {
        $shift = $attempt - 1;
        if ($shift > self::BACKOFF_CEILING) {
            $shift = self::BACKOFF_CEILING;
        }
        if ($shift < 0) {
            $shift = 0;
        }
        $delayMs = self::BACKOFF_SLOT_MS * (1 << $shift);
        usleep($delayMs * 1000);
    }
}
