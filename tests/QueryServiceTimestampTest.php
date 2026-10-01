<?php

namespace YdbPlatform\Ydb\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ydb\Query\BeginTransactionResponse;
use Ydb\Query\CommitTransactionResponse;
use Ydb\Query\CreateSessionResponse;
use Ydb\Query\DeleteSessionResponse;
use Ydb\Query\ExecuteQueryResponsePart;
use Ydb\Query\RollbackTransactionResponse;
use Ydb\Query\TransactionMeta;
use Ydb\Issue\IssueMessage;
use Ydb\StatusIds\StatusCode;
use Ydb\VirtualTimestamp;
use YdbPlatform\Ydb\CommitTimestamp;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\QueryService;
use YdbPlatform\Ydb\Ydb;

class QueryServiceTimestampTest extends TestCase
{
    private function ydb(string $database = '/local', ?int $timeout = null): Ydb
    {
        return new Ydb([
            'endpoint' => 'localhost:2136',
            'database' => $database,
            'discovery' => false,
            'iam_config' => ['insecure' => true],
            'credentials' => new AnonymousAuthentication(),
            'grpc' => ['timeout' => $timeout],
        ]);
    }

    private function service(FakeQueryClient $client, string $database = '/local'): QueryService
    {
        return new QueryService($this->ydb($database), $client);
    }

    private function timestamp(int $planStep, int $txId): VirtualTimestamp
    {
        return new VirtualTimestamp(['plan_step' => $planStep, 'tx_id' => $txId]);
    }

    public function testStrictModeUsesQueryFieldSevenAndExplicitCommitReturnsTimestamp(): void
    {
        $client = new FakeQueryClient();
        $client->createResponse = new CreateSessionResponse(['status' => StatusCode::SUCCESS, 'session_id' => 'session']);
        $client->beginResponse = new BeginTransactionResponse([
            'status' => StatusCode::SUCCESS,
            'tx_meta' => new TransactionMeta(['id' => 'tx']),
        ]);
        $client->commitResponse = new CommitTransactionResponse([
            'status' => StatusCode::SUCCESS,
            'commit_timestamp' => $this->timestamp(10, 20),
        ]);
        $service = $this->service($client);

        $sessionId = $service->createSession();
        $txId = $service->beginTransaction($sessionId);
        $stamp = $service->commitTransaction($sessionId, $txId);

        self::assertSame('session', $sessionId);
        self::assertSame('tx', $txId);
        self::assertTrue($client->beginRequest->getTxSettings()->hasStrictSerializableReadWrite());
        self::assertSame('3a00', bin2hex($client->beginRequest->getTxSettings()->serializeToString()));
        self::assertSame('session', $client->commitRequest->getSessionId());
        self::assertSame('tx', $client->commitRequest->getTxId());
        self::assertSame('10', $stamp->planStep());
        self::assertSame('20', $stamp->txId());
        self::assertInstanceOf(VirtualTimestamp::class, $stamp->proto());
        self::assertNotSame($stamp->proto(), $stamp->proto());
    }

    public function testCommitTimestampIsOptional(): void
    {
        $client = new FakeQueryClient();
        $client->commitResponse = new CommitTransactionResponse(['status' => StatusCode::SUCCESS]);
        self::assertNull($this->service($client)->commitTransaction('session', 'tx'));
    }

    public function testFactoryKeepsOneQueryServicePerConnection(): void
    {
        $ydb = $this->ydb();
        self::assertSame($ydb->queryService(), $ydb->queryService());
    }

    public function testTransactionModesAndExistingTransactionControl(): void
    {
        self::assertTrue(QueryService::transactionSettings('strict_serializable_read_write')
            ->hasStrictSerializableReadWrite());
        self::assertTrue(QueryService::transactionSettings('SerializableRW')
            ->hasSerializableReadWrite());
        self::assertTrue(QueryService::transactionSettings('serializable_read_write')
            ->hasSerializableReadWrite());
        $control = QueryService::inTransaction('existing-tx');
        self::assertSame('existing-tx', $control->getTxId());
        self::assertFalse($control->getCommitTx());

        $this->expectException(InvalidArgumentException::class);
        QueryService::transactionSettings('unknown-mode');
    }

    public function testSessionCleanupRollbackAndGrpcTimeout(): void
    {
        $client = new FakeQueryClient();
        $client->deleteResponse = new DeleteSessionResponse(['status' => StatusCode::SUCCESS]);
        $client->rollbackResponse = new RollbackTransactionResponse(['status' => StatusCode::SUCCESS]);
        $service = new QueryService($this->ydb('/db', 5000), $client);

        $service->rollbackTransaction('session', 'tx');
        $service->deleteSession('session');

        self::assertSame('session', $client->rollbackRequest->getSessionId());
        self::assertSame('tx', $client->rollbackRequest->getTxId());
        self::assertSame('session', $client->deleteRequest->getSessionId());
        self::assertSame(['timeout' => 5000], $client->lastOptions);
    }

    public function testMissingTransactionMetadataFails(): void
    {
        $client = new FakeQueryClient();
        $client->beginResponse = new BeginTransactionResponse(['status' => StatusCode::SUCCESS]);
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->expectExceptionMessage('no transaction metadata');
        $this->service($client)->beginTransaction('session');
    }

    public function testExecuteReadsTimestampOnlyFromFinalTrailingPart(): void
    {
        $client = new FakeQueryClient();
        $client->parts = [
            new ExecuteQueryResponsePart([
                'status' => StatusCode::SUCCESS,
                'commit_timestamp' => $this->timestamp(1, 2),
            ]),
            new ExecuteQueryResponsePart([
                'status' => StatusCode::SUCCESS,
                'commit_timestamp' => $this->timestamp(3, 4),
            ]),
        ];
        $result = $this->service($client)->executeQuery('session', 'UPSERT INTO t ...', QueryService::autocommit());
        self::assertCount(2, $result->parts());
        self::assertSame('3', $result->commitTimestamp()->planStep());
        self::assertSame('4', $result->commitTimestamp()->txId());
        self::assertTrue($client->executeRequest->getTxControl()->getCommitTx());
        self::assertTrue($client->executeRequest->getTxControl()->getBeginTx()->hasStrictSerializableReadWrite());
        self::assertSame('UPSERT INTO t ...', $client->executeRequest->getQueryContent()->getText());
        self::assertSame(\Ydb\Query\Syntax::SYNTAX_YQL_V1, $client->executeRequest->getQueryContent()->getSyntax());

        $client->parts[1] = new ExecuteQueryResponsePart(['status' => StatusCode::SUCCESS]);
        self::assertNull($this->service($client)->executeQuery('session', 'SELECT 1', QueryService::autocommit())->commitTimestamp());
    }

    public function testFailedCommitDoesNotExposeTimestamp(): void
    {
        $client = new FakeQueryClient();
        $client->commitResponse = new CommitTransactionResponse([
            'status' => StatusCode::BAD_REQUEST,
            'commit_timestamp' => $this->timestamp(1, 2),
        ]);
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->service($client)->commitTransaction('session', 'tx');
    }

    public function testFailedTrailingPartDoesNotExposeTimestamp(): void
    {
        $client = new FakeQueryClient();
        $client->parts = [
            new ExecuteQueryResponsePart(['status' => StatusCode::SUCCESS]),
            new ExecuteQueryResponsePart([
                'status' => StatusCode::BAD_REQUEST,
                'commit_timestamp' => $this->timestamp(1, 2),
            ]),
        ];
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->service($client)->executeQuery('session', 'SELECT 1', QueryService::autocommit());
    }

    public function testEmptyExecuteStreamFails(): void
    {
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->expectExceptionMessage('no response parts');
        $this->service(new FakeQueryClient())->executeQuery('session', 'SELECT 1', QueryService::autocommit());
    }

    public function testMissingUnaryResponseFails(): void
    {
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->expectExceptionMessage('no response');
        $this->service(new FakeQueryClient())->commitTransaction('session', 'tx');
    }

    public function testServerIssuesAreIncludedInFailure(): void
    {
        $client = new FakeQueryClient();
        $client->commitResponse = new CommitTransactionResponse([
            'status' => StatusCode::BAD_REQUEST,
            'issues' => [new IssueMessage(['message' => 'write conflict'])],
        ]);
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->expectExceptionMessage('write conflict');
        $this->service($client)->commitTransaction('session', 'tx');
    }

    public function testAbortedTransactionUsesRetryableSdkException(): void
    {
        $client = new FakeQueryClient();
        $client->commitResponse = new CommitTransactionResponse(['status' => StatusCode::ABORTED]);
        $this->expectException(\YdbPlatform\Ydb\Exceptions\Ydb\AbortedException::class);
        $this->service($client)->commitTransaction('session', 'tx');
    }

    public function testGrpcFailureIsReportedBeforeResponse(): void
    {
        $client = new FakeQueryClient();
        $client->grpcStatus = (object)['code' => 14, 'details' => 'transport closed'];
        $this->expectException(\YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException::class);
        $this->expectExceptionMessage('gRPC status 14: transport closed');
        $this->service($client)->commitTransaction('session', 'tx');
    }

    public function testTransportFailureRefreshesClientForNextRequest(): void
    {
        $ydb = new DiscoveringYdb([
            'endpoint' => 'localhost:2136',
            'database' => '/local',
            'iam_config' => ['insecure' => true],
            'credentials' => new AnonymousAuthentication(),
        ]);
        $ydb->cluster()->insert(['address' => 'node.example', 'port' => 2136]);
        $client = new FakeQueryClient();
        $client->grpcStatus = (object)['code' => 14, 'details' => 'node unavailable'];
        $service = new RecordingQueryService($ydb, $client);
        $replacement = new FakeQueryClient();
        $replacement->commitResponse = new CommitTransactionResponse(['status' => StatusCode::SUCCESS]);
        $service->replacementClient = $replacement;

        try {
            $service->commitTransaction('session', 'tx');
            self::fail('Expected a retryable transport error');
        } catch (\YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException $error) {
            self::assertSame(1, $ydb->discoverCalls);
            self::assertSame(1, $client->closeCalls);
            self::assertSame('node.example:2136', $service->createdEndpoint);
            self::assertTrue($service->createdOptions['force_new']);
        }
        self::assertNull($service->commitTransaction('new-session', 'new-tx'));
        self::assertSame('new-session', $replacement->commitRequest->getSessionId());
    }

    public function testFailedRediscoveryPreservesOriginalTransportError(): void
    {
        $ydb = new DiscoveringYdb([
            'endpoint' => 'localhost:2136',
            'database' => '/local',
            'iam_config' => ['insecure' => true],
            'credentials' => new AnonymousAuthentication(),
        ]);
        $ydb->failDiscovery = true;
        $client = new FakeQueryClient();
        $client->grpcStatus = (object)['code' => 14, 'details' => 'node unavailable'];
        $service = new RecordingQueryService($ydb, $client);

        try {
            $service->commitTransaction('session', 'tx');
            self::fail('Expected the original transport error');
        } catch (\YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException $error) {
            self::assertSame(1, $ydb->discoverCalls);
            self::assertSame('localhost:2136', $service->createdEndpoint);
            self::assertTrue($service->createdOptions['force_new']);
        }
    }

    public function testMissingGrpcStatusIsReported(): void
    {
        $client = new FakeQueryClient();
        $client->grpcStatus = (object)[];
        $this->expectException(\YdbPlatform\Ydb\Exception::class);
        $this->expectExceptionMessage('gRPC status unknown');
        $this->service($client)->commitTransaction('session', 'tx');
    }

    public function testCompareUnsignedComponentsAndRejectDifferentDatabases(): void
    {
        $max = new VirtualTimestamp();
        $max->mergeFromString(hex2bin('08ffffffffffffffffff011001'));
        $high = new VirtualTimestamp();
        $high->mergeFromString(hex2bin('08808080808080808080011001'));
        $highTx = new VirtualTimestamp();
        $highTx->mergeFromString(hex2bin('080110ffffffffffffffffff01'));

        $origin = $this->ydb('/db');
        $a = new CommitTimestamp($this->timestamp(9, 100), $origin);
        $b = new CommitTimestamp($this->timestamp(10, 1), $origin);
        $c = new CommitTimestamp($this->timestamp(10, 2), $origin);
        $upper = new CommitTimestamp($high, $origin);
        $highest = new CommitTimestamp($max, $origin);

        self::assertLessThan(0, $a->compareTo($b));
        self::assertLessThan(0, $b->compareTo($c));
        self::assertSame(0, $a->compareTo(new CommitTimestamp($this->timestamp(9, 100), $origin)));
        self::assertSame('9223372036854775808', $upper->planStep());
        self::assertSame('18446744073709551615', $highest->planStep());
        self::assertSame('/db', $highest->database());
        self::assertSame('18446744073709551615', (new CommitTimestamp($highTx, $origin))->txId());
        self::assertLessThan(0, $upper->compareTo($highest));
        self::assertGreaterThan(0, $highest->compareTo($c));

        $this->expectException(InvalidArgumentException::class);
        $a->compareTo(new CommitTimestamp($this->timestamp(9, 100), $this->ydb('/db')));
    }

    public function testMalformedTimestampComponentIsRejected(): void
    {
        $proto = $this->timestamp(1, 2);
        $component = new \ReflectionProperty(VirtualTimestamp::class, 'plan_step');
        $component->setAccessible(true);
        $component->setValue($proto, '18446744073709551616');
        $stamp = new CommitTimestamp($proto, $this->ydb());
        $this->expectException(InvalidArgumentException::class);
        $stamp->planStep();
    }
}

class FakeQueryClient
{
    public $createResponse;
    public $beginResponse;
    public $commitResponse;
    public $deleteResponse;
    public $rollbackResponse;
    public $parts = [];
    public $grpcStatus;
    public $beginRequest;
    public $commitRequest;
    public $executeRequest;
    public $deleteRequest;
    public $rollbackRequest;
    public $lastOptions;
    public $closeCalls = 0;

    public function close(): void
    {
        $this->closeCalls++;
    }

    public function CreateSession($request, $meta, $options)
    {
        return new FakeUnaryCall($this->createResponse);
    }

    public function BeginTransaction($request, $meta, $options)
    {
        $this->beginRequest = $request;
        return new FakeUnaryCall($this->beginResponse);
    }

    public function CommitTransaction($request, $meta, $options)
    {
        $this->commitRequest = $request;
        return new FakeUnaryCall($this->commitResponse, $this->grpcStatus);
    }

    public function DeleteSession($request, $meta, $options)
    {
        $this->deleteRequest = $request;
        $this->lastOptions = $options;
        return new FakeUnaryCall($this->deleteResponse);
    }

    public function RollbackTransaction($request, $meta, $options)
    {
        $this->rollbackRequest = $request;
        $this->lastOptions = $options;
        return new FakeUnaryCall($this->rollbackResponse);
    }

    public function ExecuteQuery($request, $meta, $options)
    {
        $this->executeRequest = $request;
        return new FakeStreamCall($this->parts);
    }
}

class DiscoveringYdb extends Ydb
{
    public $discoverCalls = 0;
    public $failDiscovery = false;

    public function needDiscovery(): bool
    {
        return true;
    }

    public function discover()
    {
        $this->discoverCalls++;
        if ($this->failDiscovery) {
            throw new \RuntimeException('discovery unavailable');
        }
        $this->endpoint = 'recovered:2136';
    }
}

class RecordingQueryService extends QueryService
{
    public $createdEndpoint;
    public $createdOptions;
    public $replacementClient;

    protected function createClient(string $endpoint, array $options)
    {
        $this->createdEndpoint = $endpoint;
        $this->createdOptions = $options;
        return $this->replacementClient ?: new FakeQueryClient();
    }
}

class FakeUnaryCall
{
    private $response;
    private $status;

    public function __construct($response, $status = null)
    {
        $this->response = $response;
        $this->status = $status === null ? (object)['code' => 0] : $status;
    }

    public function wait(): array
    {
        return [$this->response, $this->status];
    }
}

class FakeStreamCall
{
    private $parts;

    public function __construct(array $parts)
    {
        $this->parts = $parts;
    }

    public function responses(): array
    {
        return $this->parts;
    }

    public function getStatus()
    {
        return (object)['code' => 0];
    }
}
