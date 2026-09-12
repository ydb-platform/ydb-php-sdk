<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Ydb;

/**
 * Two related fixes, both for tx_id getting stranded on an aborted
 * transaction: commitTransaction()/rollbackTransaction() only reset it after
 * their own RPC succeeded, and query() didn't reset it on failure at all.
 * Cross-checked against Python's reset_tx_id_handler/TxState.dead and Java's
 * setNewId(currentId, null), which do the same at the equivalent points.
 */
class TransactionResetAfterFailedCommitOrRollbackTest extends TestCase
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

        $ydb = new Ydb($config);

        return $ydb->table()->session();
    }

    private function txIdProperty(): \ReflectionProperty
    {
        $property = new \ReflectionProperty(Session::class, 'tx_id');
        $property->setAccessible(true);

        return $property;
    }

    public function testRollbackTransactionResetsTxIdEvenWhenItsOwnRpcFails(): void
    {
        $session = $this->makeSession();
        $txId = $this->txIdProperty();
        // A tx_id the server has never heard of - RollbackTransaction on it
        // fails exactly like it would on one the server has aborted.
        $txId->setValue($session, 'not-a-real-transaction-id');

        try {
            $session->rollBack();
        } catch (\Throwable $e) {
        }

        $this->assertNull(
            $txId->getValue($session),
            'rollBack() must reset tx_id even when its own RPC call fails.'
        );
    }

    public function testCommitTransactionResetsTxIdEvenWhenItsOwnRpcFails(): void
    {
        $session = $this->makeSession();
        $txId = $this->txIdProperty();
        $txId->setValue($session, 'not-a-real-transaction-id');

        try {
            $session->commit();
        } catch (\Throwable $e) {
        }

        $this->assertNull(
            $txId->getValue($session),
            'commit() must reset tx_id even when its own RPC call fails.'
        );
    }

    /**
     * The realistic, end-to-end reproduction: nothing here calls commit() or
     * rollBack() explicitly at all. query() itself must notice its own
     * failure and clear tx_id, or this hangs a session for anyone who
     * doesn't already know to roll back after every failed statement.
     */
    public function testQueryResetsTxIdOnFailureWithoutAnExplicitRollback(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/tx_reset_test_query`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE tx_reset_test_query (id Int32 NOT NULL, PRIMARY KEY (id))');

        $session->query('INSERT INTO tx_reset_test_query (id) VALUES (1)');

        try {
            // Conflicts on the PRIMARY KEY - the server aborts the
            // transaction this call reused.
            $session->query('INSERT INTO tx_reset_test_query (id) VALUES (1)');
            $this->fail('Expected a duplicate PRIMARY KEY insert to fail.');
        } catch (\Throwable $e) {
        }

        $this->assertNull(
            $this->txIdProperty()->getValue($session),
            'A failed query() must reset tx_id on its own, without requiring an explicit rollBack().'
        );

        // An unrelated follow-up statement must succeed on a fresh
        // transaction instead of reusing the dead one.
        $session->query('INSERT INTO tx_reset_test_query (id) VALUES (2)');

        $session->schemeQuery('DROP TABLE `/local/tx_reset_test_query`');
    }
}
