<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Ydb;

// beginTx() used to hard-code commit_tx=true. See ydb-platform/ydb-php-sdk#123.
class YdbQueryBeginTxCommitControlTest extends TestCase
{
    private function makeSession(): Session
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

    private function txId(Session $session): ?string
    {
        $property = new \ReflectionProperty($session, 'tx_id');
        $property->setAccessible(true);

        return $property->getValue($session);
    }

    public function testDefaultBeginTxStillAutoCommits(): void
    {
        $session = $this->makeSession();

        $session->newQuery('SELECT 1 AS one')->beginTx('serializable')->execute();

        self::assertNull($this->txId($session), 'Default beginTx($mode) must keep auto-committing.');
    }

    public function testBeginTxWithCommitFalseLeavesTheTransactionOpen(): void
    {
        $session = $this->makeSession();

        $session->newQuery('SELECT 1 AS one')->beginTx('serializable', false)->execute();

        self::assertNotNull(
            $this->txId($session),
            'beginTx($mode, false) must leave a transaction open and expose its id.',
        );
    }

    public function testTransactionOpenedByBeginTxCanBeContinuedAndRolledBack(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/ydbquery_begintx_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE ydbquery_begintx_test (id Int32 NOT NULL, PRIMARY KEY (id))');

        $session->newQuery('INSERT INTO ydbquery_begintx_test (id) VALUES (1)')
            ->beginTx('serializable', false)
            ->execute();
        $openedTxId = $this->txId($session);

        $session->query('INSERT INTO ydbquery_begintx_test (id) VALUES (2)');
        self::assertSame($openedTxId, $this->txId($session), 'query() must continue the same transaction, not start a new one.');

        $session->rollBack();

        $result = $session->query('SELECT COUNT(*) AS c FROM ydbquery_begintx_test');
        self::assertSame(0, (int) $result->rows()[0]['c'], 'Both inserts must be rolled back together.');

        $session->schemeQuery('DROP TABLE `/local/ydbquery_begintx_test`');
    }
}
