<?php

namespace YdbPlatform\Ydb\Test\Internal;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Exceptions\Grpc\InvalidArgumentException;
use YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException;
use YdbPlatform\Ydb\Iam;
use YdbPlatform\Ydb\Internal\Discovery;
use YdbPlatform\Ydb\Ydb;

class DiscoveryTest extends TestCase
{
    private const BOOTSTRAP_ENDPOINT = 'bootstrap-endpoint:2135';

    /**
     * A DIFFERENT value returned by $ydb->endpoint() to prove createClient ignores
     * the mutated Ydb endpoint and always targets the bootstrap one.
     */
    private const MUTATED_ENDPOINT = 'node-after-discovery:2136';

    /**
     * @return Ydb&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeYdb()
    {
        $iam = $this->getMockBuilder(Iam::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['token'])
            ->getMock();
        $iam->method('token')->willReturn('fake-token');

        $ydb = $this->getMockBuilder(Ydb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['grpcOpts', 'meta', 'iam', 'database', 'endpoint'])
            ->getMock();
        $ydb->method('grpcOpts')->willReturn([]);
        $ydb->method('meta')->willReturn(['x-ydb-database' => ['/local']]);
        $ydb->method('database')->willReturn('/local');
        $ydb->method('endpoint')->willReturn(self::MUTATED_ENDPOINT);
        $ydb->method('iam')->willReturn($iam);

        return $ydb;
    }

    /**
     * No-op client factory: returns a fake client with close() and ListEndpoints() so
     * the constructor's initClient() call never builds a real gRPC client.
     */
    private function noopClientFactory(): callable
    {
        return function ($endpoint, $opts) {
            return new class {
                public function close()
                {
                }

                public function ListEndpoints($r, $m, $o)
                {
                    return null;
                }
            };
        };
    }

    // ------------------------------------------------------------------
    // buildClientOpts: pass-through + force_new flag.
    // ------------------------------------------------------------------

    public function testBuildClientOptsWithoutForceNewIsPassThrough(): void
    {
        $ydb = $this->makeYdb();
        $disc = new Discovery($ydb, self::BOOTSTRAP_ENDPOINT, 300, 100, PHP_INT_MAX, null, $this->noopClientFactory());

        $opts = $disc->buildClientOpts(false);

        $this->assertIsArray($opts);
        $this->assertArrayNotHasKey('force_new', $opts);
        // round_robin is now Ydb::grpcOpts()'s responsibility; Discovery doesn't add it.
        $this->assertArrayNotHasKey('grpc.lb_policy_name', $opts);
    }

    public function testBuildClientOptsPreservesGrpcOptsContent(): void
    {
        $iam = $this->getMockBuilder(Iam::class)->disableOriginalConstructor()->onlyMethods(['token'])->getMock();
        $iam->method('token')->willReturn('fake-token');

        $sentinelCreds = new \stdClass();
        $ydb = $this->getMockBuilder(Ydb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['grpcOpts', 'meta', 'iam', 'database', 'endpoint'])
            ->getMock();
        $ydb->method('grpcOpts')->willReturn([
            'credentials'         => $sentinelCreds,
            'grpc.lb_policy_name' => 'round_robin',
            'custom.key'          => 'custom-value',
        ]);
        $ydb->method('meta')->willReturn(['x-ydb-database' => ['/local']]);
        $ydb->method('database')->willReturn('/local');
        $ydb->method('endpoint')->willReturn(self::MUTATED_ENDPOINT);
        $ydb->method('iam')->willReturn($iam);

        $disc = new Discovery($ydb, self::BOOTSTRAP_ENDPOINT, 300, 100, PHP_INT_MAX, null, $this->noopClientFactory());

        $optsNoForce = $disc->buildClientOpts(false);
        $this->assertSame($sentinelCreds, $optsNoForce['credentials']);
        $this->assertSame('round_robin', $optsNoForce['grpc.lb_policy_name']);
        $this->assertSame('custom-value', $optsNoForce['custom.key']);
        $this->assertArrayNotHasKey('force_new', $optsNoForce);

        $optsForce = $disc->buildClientOpts(true);
        $this->assertSame($sentinelCreds, $optsForce['credentials']);
        $this->assertSame('round_robin', $optsForce['grpc.lb_policy_name']);
        $this->assertSame('custom-value', $optsForce['custom.key']);
        $this->assertTrue($optsForce['force_new']);
    }

    public function testBuildClientOptsWithForceNewAddsFlag(): void
    {
        $ydb = $this->makeYdb();
        $disc = new Discovery($ydb, self::BOOTSTRAP_ENDPOINT, 300, 100, PHP_INT_MAX, null, $this->noopClientFactory());

        $opts = $disc->buildClientOpts(true);

        $this->assertIsArray($opts);
        $this->assertTrue($opts['force_new']);
    }

    // ------------------------------------------------------------------
    // initClient (lazy): NOT called in ctor; invoked on first listEndpoints (or here,
    // via reflection). Targets bootstrap endpoint, no force_new.
    // ------------------------------------------------------------------

    public function testCtorIsCheapAndDoesNotBuildClient(): void
    {
        $ydb = $this->makeYdb();

        $calls = 0;
        $factory = function ($endpoint, $opts) use (&$calls) {
            $calls++;
            return new class {
                public function close() {}
                public function ListEndpoints($r, $m, $o) { return null; }
            };
        };

        new Discovery($ydb, self::BOOTSTRAP_ENDPOINT, 300, 100, PHP_INT_MAX, null, $factory);

        $this->assertSame(0, $calls, 'ctor must NOT build the client — initClient is lazy');
    }

    public function testInitClientBuildsAgainstBootstrapWithoutForceNew(): void
    {
        $ydb = $this->makeYdb();

        $calls = [];
        $factory = function ($endpoint, $opts) use (&$calls) {
            $client = new class {
                public $closed = false;
                public function close() { $this->closed = true; }
                public function ListEndpoints($r, $m, $o) { return null; }
            };
            $calls[] = ['endpoint' => $endpoint, 'opts' => $opts, 'client' => $client];
            return $client;
        };

        $disc = new Discovery($ydb, self::BOOTSTRAP_ENDPOINT, 300, 100, PHP_INT_MAX, null, $factory);

        $initClient = new \ReflectionMethod(Discovery::class, 'initClient');
        $initClient->setAccessible(true);
        $initClient->invoke($disc);

        $this->assertCount(1, $calls);
        $this->assertSame(self::BOOTSTRAP_ENDPOINT, $calls[0]['endpoint']);
        $this->assertArrayNotHasKey('force_new', $calls[0]['opts'], 'initClient must NOT set force_new');
    }

    // ------------------------------------------------------------------
    // recreateClient: targets bootstrap, sets force_new, closes prev client.
    // ------------------------------------------------------------------

    public function testRecreateClientTargetsBootstrapWithForceNewAndClosesPrevious(): void
    {
        $ydb = $this->makeYdb();

        $calls = [];
        $factory = function ($endpoint, $opts) use (&$calls) {
            $client = new class {
                public $closed = false;
                public function close() { $this->closed = true; }
                public function ListEndpoints($r, $m, $o) { return null; }
            };
            $calls[] = ['endpoint' => $endpoint, 'opts' => $opts, 'client' => $client];
            return $client;
        };

        $disc = new Discovery($ydb, self::BOOTSTRAP_ENDPOINT, 300, 100, PHP_INT_MAX, null, $factory);

        // initClient is lazy; trigger it manually so we have a "previous" client to close.
        $initClient = new \ReflectionMethod(Discovery::class, 'initClient');
        $initClient->setAccessible(true);
        $initClient->invoke($disc);
        $firstClient = $calls[0]['client'];

        $recreate = new \ReflectionMethod(Discovery::class, 'recreateClient');
        $recreate->setAccessible(true);
        $recreate->invoke($disc);

        $this->assertCount(2, $calls);
        $this->assertSame(self::BOOTSTRAP_ENDPOINT, $calls[1]['endpoint'], 'recreateClient must keep targeting the bootstrap endpoint');
        $this->assertTrue($calls[1]['opts']['force_new'] ?? false, 'recreateClient must request force_new');
        $this->assertTrue($firstClient->closed, 'recreateClient must close the previously-built client');
    }

    // ------------------------------------------------------------------
    // listEndpoints (background): bounded by discoveryTimeoutMs, retries any
    // \Throwable, throws last on exhaustion.
    // ------------------------------------------------------------------

    public function testBackgroundRetriesUntilBudgetExhaustedThenThrows(): void
    {
        $ydb = $this->makeYdb();

        $recreates = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 30, 5, PHP_INT_MAX, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->willThrowException(new \RuntimeException('always fails'));
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        try {
            $disc->listEndpoints();
            $this->fail('listEndpoints() should throw once the time budget is exhausted');
        } catch (\Throwable $e) {
            $this->assertSame('always fails', $e->getMessage());
        }

        $this->assertGreaterThanOrEqual(1, $recreates, 'background must retry at least once');
    }

    public function testBackgroundRetriesOnPlainRuntimeException(): void
    {
        // Distinguishes Discovery's background loop from the generic Retry, which only
        // retries RetryableException. Here a plain \RuntimeException must still retry.
        $ydb = $this->makeYdb();

        $recreates = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 30, 5, PHP_INT_MAX, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->willThrowException(new \RuntimeException('non-retryable but background retries anyway'));
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        $this->expectException(\RuntimeException::class);
        try {
            $disc->listEndpoints();
        } finally {
            $this->assertGreaterThanOrEqual(1, $recreates);
        }
    }

    // ------------------------------------------------------------------
    // Success on the k-th attempt: k failures then a returned array;
    // recreateClient must be called exactly k times.
    // ------------------------------------------------------------------

    public function testSucceedsOnThirdAttemptAndRecreatesOnce(): void
    {
        $ydb = $this->makeYdb();

        $recreates = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 1000, 5, PHP_INT_MAX, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->will($this->onConsecutiveCalls(
            $this->throwException(new \RuntimeException('fail 1')),
            $this->throwException(new \RuntimeException('fail 2')),
            $this->throwException(new \RuntimeException('fail 3')),
            $this->returnValue(['ok'])
        ));
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        $result = $disc->listEndpoints();

        $this->assertSame(['ok'], $result);
        $this->assertSame(3, $recreates, 'recreateClient must be called exactly once per retry');
    }

    // ------------------------------------------------------------------
    // initialListEndpoints: fail-fast vs retryable.
    // ------------------------------------------------------------------

    public function testInitialModeFailsFastOnNonRetryableRuntimeException(): void
    {
        $ydb = $this->makeYdb();

        $recreates = 0;
        $doCalls = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 1000, 5, 1000, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->willReturnCallback(function () use (&$doCalls) {
            $doCalls++;
            throw new \RuntimeException('non-retryable');
        });
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        try {
            $disc->initialListEndpoints();
            $this->fail('initial mode must rethrow a non-retryable exception immediately');
        } catch (\RuntimeException $e) {
            $this->assertSame('non-retryable', $e->getMessage());
        }

        $this->assertSame(1, $doCalls, 'initial mode must NOT retry on a non-retryable exception');
        $this->assertSame(0, $recreates, 'initial mode must NOT recreate the client on a non-retryable exception');
    }

    public function testInitialModeFailsFastOnNonRetryableGrpcException(): void
    {
        $ydb = $this->makeYdb();

        $recreates = 0;
        $doCalls = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 1000, 5, 1000, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->willReturnCallback(function () use (&$doCalls) {
            $doCalls++;
            throw new InvalidArgumentException('bad argument');
        });
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        $this->expectException(InvalidArgumentException::class);
        try {
            $disc->initialListEndpoints();
        } finally {
            $this->assertSame(1, $doCalls);
            $this->assertSame(0, $recreates);
        }
    }

    public function testInitialModeRetriesOnRetryableExceptionWithinBudget(): void
    {
        $ydb = $this->makeYdb();

        $recreates = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            // small initialTimeoutMs so the loop terminates
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 1000, 5, 30, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->willThrowException(new UnavailableException('unavailable'));
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        try {
            $disc->initialListEndpoints();
            $this->fail('initial mode should eventually throw once its budget is exhausted');
        } catch (UnavailableException $e) {
            $this->assertSame('unavailable', $e->getMessage());
        }

        $this->assertGreaterThanOrEqual(1, $recreates, 'initial mode must retry/recreate on a retryable exception');
    }

    public function testInitialModeSucceedsOnSecondAttemptWhenRetryable(): void
    {
        $ydb = $this->makeYdb();

        $recreates = 0;
        $disc = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$ydb, self::BOOTSTRAP_ENDPOINT, 1000, 5, 1000, null, $this->noopClientFactory()])
            ->onlyMethods(['doListEndpoints', 'recreateClient'])
            ->getMock();

        $disc->method('doListEndpoints')->will($this->onConsecutiveCalls(
            $this->throwException(new UnavailableException('unavailable')),
            $this->returnValue(['ok'])
        ));
        $disc->method('recreateClient')->willReturnCallback(function () use (&$recreates) {
            $recreates++;
        });

        $result = $disc->initialListEndpoints();

        $this->assertSame(['ok'], $result);
        $this->assertSame(1, $recreates, 'one retryable failure -> exactly one recreate');
    }
}
