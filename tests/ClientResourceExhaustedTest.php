<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Exceptions\Grpc\ResourceExhaustedException;
use YdbPlatform\Ydb\Exceptions\Ydb\ClientResourceExhaustedException;
use YdbPlatform\Ydb\Logger\NullLogger;
use YdbPlatform\Ydb\Traits\RequestTrait;
use YdbPlatform\Ydb\Ydb;

// grpc-core rejects an oversized outgoing message locally with the same RESOURCE_EXHAUSTED
// code as a retryable server-side quota error - see ydb-platform/ydb-php-sdk#258.
class ClientResourceExhaustedTest extends TestCase
{
    use RequestTrait;

    private $client;
    private $logger;

    public function testOversizedMessageIsNotRetryable(): void
    {
        $this->configureForTest();

        $status = (object) [
            'code' => 8,
            'details' => 'Sent message larger than max (67133440 vs. 67108864)',
        ];

        $this->expectException(ClientResourceExhaustedException::class);
        $this->handleGrpcStatus('Table', 'ExecuteDataQuery', $status);
    }

    public function testOtherResourceExhaustedStaysRetryable(): void
    {
        $this->configureForTest();

        $status = (object) [
            'code' => 8,
            'details' => 'Bandwidth quota exceeded',
        ];

        $this->expectException(ResourceExhaustedException::class);
        $this->handleGrpcStatus('Table', 'ExecuteDataQuery', $status);
    }

    private function configureForTest(): void
    {
        $ydb = $this->getMockBuilder(Ydb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['needDiscovery', 'endpoint', 'grpcOpts'])
            ->getMock();
        $ydb->method('needDiscovery')->willReturn(false);
        $ydb->method('endpoint')->willReturn('localhost:2136');
        $ydb->method('grpcOpts')->willReturn([]);

        $this->ydb = $ydb;
        $this->logger = new NullLogger();
        $this->client = new class {
            public function __construct($endpoint = null, $opts = null)
            {
            }
        };
    }
}
