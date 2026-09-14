<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Ydb;

// The server reports Request Units consumed per operation (Ydb.CostInfo,
// used for Serverless YDB billing), but nothing surfaced it - the SDK read
// Operation.cost_info off the response envelope and then discarded it while
// unwrapping to the typed result. See ydb-platform/ydb-php-sdk#23.
class RequestUnitsTest extends TestCase
{
    private function makeSession()
    {
        $config = [
            'database' => '/local',
            'endpoint' => 'localhost:2136',
            'discovery' => false,
            'iam_config' => [
                'insecure' => true,
            ],
            'credentials' => new AnonymousAuthentication(),
        ];

        return (new Ydb($config))->table()->createSession();
    }

    public function testConsumedRuIsNullByDefault(): void
    {
        $session = $this->makeSession();

        $result = $session->query('SELECT 1');

        self::assertNull($result->getConsumedRu());
    }

    public function testConsumedRuIsReportedWhenRequested(): void
    {
        $session = $this->makeSession();

        $result = $session->query('SELECT 1', null, ['reportCostInfo' => true]);

        self::assertIsFloat($result->getConsumedRu());
        self::assertGreaterThan(0.0, $result->getConsumedRu());
    }

    public function testConsumedRuIsReportedThroughPreparedStatements(): void
    {
        $session = $this->makeSession();

        $result = $session->prepare('SELECT 1')->execute([], ['reportCostInfo' => true]);

        self::assertIsFloat($result->getConsumedRu());
        self::assertGreaterThan(0.0, $result->getConsumedRu());
    }
}
