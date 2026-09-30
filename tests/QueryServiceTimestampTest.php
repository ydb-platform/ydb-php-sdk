<?php

namespace YdbPlatform\Ydb\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ydb\Query\BeginTransactionResponse;
use Ydb\Query\CommitTransactionResponse;
use Ydb\Query\CreateSessionResponse;
use Ydb\Query\ExecuteQueryResponsePart;
use Ydb\Query\TransactionMeta;
use Ydb\StatusIds\StatusCode;
use Ydb\VirtualTimestamp;
use YdbPlatform\Ydb\CommitTimestamp;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\QueryService;
use YdbPlatform\Ydb\Ydb;

class QueryServiceTimestampTest extends TestCase
{
    private function ydb(string $database = '/local'): Ydb
    {
        return new Ydb([
            'endpoint' => 'localhost:2136',
            'database' => $database,
            'discovery' => false,
            'iam_config' => ['insecure' => true],
            'credentials' => new AnonymousAuthentication(),
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
        self::assertSame('18446744073709551615', (new CommitTimestamp($highTx, $origin))->txId());
        self::assertLessThan(0, $upper->compareTo($highest));
        self::assertGreaterThan(0, $highest->compareTo($c));

        $this->expectException(InvalidArgumentException::class);
        $a->compareTo(new CommitTimestamp($this->timestamp(9, 100), $this->ydb('/db')));
    }
}

class FakeQueryClient
{
    public $createResponse;
    public $beginResponse;
    public $commitResponse;
    public $parts = [];
    public $beginRequest;
    public $commitRequest;
    public $executeRequest;

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
        return new FakeUnaryCall($this->commitResponse);
    }

    public function ExecuteQuery($request, $meta, $options)
    {
        $this->executeRequest = $request;
        return new FakeStreamCall($this->parts);
    }
}

class FakeUnaryCall
{
    private $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function wait(): array
    {
        return [$this->response, (object)['code' => 0]];
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
