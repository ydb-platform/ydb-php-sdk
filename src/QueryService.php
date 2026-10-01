<?php

namespace YdbPlatform\Ydb;

use InvalidArgumentException;
use Ydb\Query\BeginTransactionRequest;
use Ydb\Query\CommitTransactionRequest;
use Ydb\Query\CreateSessionRequest;
use Ydb\Query\DeleteSessionRequest;
use Ydb\Query\ExecMode;
use Ydb\Query\ExecuteQueryRequest;
use Ydb\Query\QueryContent;
use Ydb\Query\RollbackTransactionRequest;
use Ydb\Query\SerializableModeSettings;
use Ydb\Query\StrictSerializableRWModeSettings;
use Ydb\Query\Syntax;
use Ydb\Query\TransactionControl;
use Ydb\Query\TransactionSettings;
use Ydb\Query\V1\QueryServiceClient;
use Ydb\StatusIds\StatusCode;

/** Query Service API. Session and transaction IDs are explicit to avoid hidden state. */
class QueryService
{
    private $ydb;
    private $client;

    /**
     * @param QueryServiceClient|null $client Optional client for custom transports.
     */
    public function __construct(Ydb $ydb, $client = null)
    {
        $this->ydb = $ydb;
        $this->client = $client ?: $this->createClient($ydb->endpoint(), $ydb->grpcOpts());
    }

    protected function createClient(string $endpoint, array $options)
    {
        return new QueryServiceClient($endpoint, $options);
    }

    public static function transactionSettings(string $mode = 'StrictSerializableRW'): TransactionSettings
    {
        if ($mode === 'StrictSerializableRW' || $mode === 'strict_serializable_read_write') {
            return new TransactionSettings([
                'strict_serializable_read_write' => new StrictSerializableRWModeSettings(),
            ]);
        }
        if ($mode === 'SerializableRW' || $mode === 'serializable_read_write') {
            return new TransactionSettings([
                'serializable_read_write' => new SerializableModeSettings(),
            ]);
        }
        throw new InvalidArgumentException("Unsupported Query transaction mode: {$mode}");
    }

    public static function autocommit(string $mode = 'StrictSerializableRW'): TransactionControl
    {
        return new TransactionControl([
            'begin_tx' => self::transactionSettings($mode),
            'commit_tx' => true,
        ]);
    }

    public static function inTransaction(string $txId): TransactionControl
    {
        return new TransactionControl(['tx_id' => $txId]);
    }

    public function createSession(): string
    {
        $response = $this->unary('CreateSession', new CreateSessionRequest());
        return $response->getSessionId();
    }

    public function deleteSession(string $sessionId): void
    {
        $this->unary('DeleteSession', new DeleteSessionRequest(['session_id' => $sessionId]));
    }

    public function beginTransaction(string $sessionId, string $mode = 'StrictSerializableRW'): string
    {
        $response = $this->unary('BeginTransaction', new BeginTransactionRequest([
            'session_id' => $sessionId,
            'tx_settings' => self::transactionSettings($mode),
        ]));
        if (!$response->hasTxMeta()) {
            throw new Exception('Query BeginTransaction returned no transaction metadata');
        }
        return $response->getTxMeta()->getId();
    }

    /** Return null when the server did not send commit_timestamp. */
    public function commitTransaction(string $sessionId, string $txId): ?CommitTimestamp
    {
        $response = $this->unary('CommitTransaction', new CommitTransactionRequest([
            'session_id' => $sessionId,
            'tx_id' => $txId,
        ]));
        return $response->hasCommitTimestamp()
            ? new CommitTimestamp($response->getCommitTimestamp(), $this->ydb)
            : null;
    }

    public function rollbackTransaction(string $sessionId, string $txId): void
    {
        $this->unary('RollbackTransaction', new RollbackTransactionRequest([
            'session_id' => $sessionId,
            'tx_id' => $txId,
        ]));
    }

    /**
     * Consume the entire response stream. commit_timestamp is read only from its final part.
     * Results remain available as original ExecuteQueryResponsePart messages.
     *
     * @param array $parameters Map of parameter names to Ydb.TypedValue messages.
     */
    public function executeQuery(
        string $sessionId,
        string $text,
        TransactionControl $txControl,
        array $parameters = []
    ): QueryExecutionResult {
        $request = new ExecuteQueryRequest([
            'session_id' => $sessionId,
            'exec_mode' => ExecMode::EXEC_MODE_EXECUTE,
            'query_content' => new QueryContent([
                'syntax' => Syntax::SYNTAX_YQL_V1,
                'text' => $text,
            ]),
            'tx_control' => $txControl,
            'parameters' => $parameters,
        ]);
        $call = $this->client->ExecuteQuery($request, $this->ydb->meta(), $this->options());
        $parts = [];
        foreach ($call->responses() as $part) {
            $this->checkResponse($part, 'ExecuteQuery');
            $parts[] = $part;
        }
        $this->checkGrpcStatus($call->getStatus(), 'ExecuteQuery');
        if (!$parts) {
            throw new Exception('Query ExecuteQuery returned no response parts');
        }
        return new QueryExecutionResult($parts, $this->ydb);
    }

    private function unary(string $method, $request)
    {
        $call = $this->client->$method($request, $this->ydb->meta(), $this->options());
        list($response, $status) = $call->wait();
        $this->checkGrpcStatus($status, $method);
        if ($response === null) {
            throw new Exception("Query {$method} returned no response");
        }
        $this->checkResponse($response, $method);
        return $response;
    }

    private function checkResponse($response, string $method): void
    {
        $statusCode = $response->getStatus();
        if ($statusCode !== StatusCode::SUCCESS) {
            $issues = [];
            foreach ($response->getIssues() as $issue) {
                $issues[] = (new Issue($issue))->toString();
            }
            $exceptionClass = Table::$ydbExceptions[$statusCode] ?? Exception::class;
            throw new $exceptionClass("Query {$method} failed with status {$statusCode}: " . implode('; ', $issues));
        }
    }

    private function checkGrpcStatus($status, string $method): void
    {
        if (!$status || !isset($status->code) || $status->code !== 0) {
            $code = isset($status->code) ? $status->code : 'unknown';
            $details = isset($status->details) ? $status->details : '';
            $this->refreshClientAfterTransportFailure();
            $exceptionClass = Table::$grpcExceptions[$code] ?? Exception::class;
            throw new $exceptionClass("Query {$method} gRPC status {$code}: {$details}");
        }
    }

    /** Reconnect for the next request; the failed transaction must be retried by the caller. */
    protected function refreshClientAfterTransportFailure(): void
    {
        if ($this->ydb->needDiscovery()) {
            try {
                $this->ydb->discover();
            } catch (\Exception $ignored) {
                // Keep the original transport error and reconnect to the last known endpoint.
            }
        }

        $endpoint = $this->ydb->endpoint();
        if ($this->ydb->needDiscovery()) {
            $nodes = array_values($this->ydb->cluster()->all());
            if ($nodes) {
                $endpoint = $nodes[array_rand($nodes)]->endpoint();
            }
        }
        if (method_exists($this->client, 'close')) {
            $this->client->close();
        }
        $options = $this->ydb->grpcOpts();
        $options['force_new'] = true;
        $this->client = $this->createClient($endpoint, $options);
    }

    private function options(): array
    {
        $timeout = $this->ydb->getGrpcTimeout();
        return $timeout === null ? [] : ['timeout' => $timeout];
    }
}
