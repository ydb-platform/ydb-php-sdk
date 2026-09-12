<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Ydb;

// Session had no public way to check for an active transaction - tx_id was a protected field with no getter.
class SessionIsInTransactionTest extends TestCase
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

    public function testTracksTheTransactionLifecycle(): void
    {
        $session = $this->makeSession();

        self::assertFalse($session->isInTransaction());

        $session->beginTransaction();
        self::assertTrue($session->isInTransaction());

        $session->commitTransaction();
        self::assertFalse($session->isInTransaction());

        $session->beginTransaction();
        self::assertTrue($session->isInTransaction());

        $session->rollbackTransaction();
        self::assertFalse($session->isInTransaction());
    }
}
