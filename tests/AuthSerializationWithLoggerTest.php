<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Auth\Implement\StaticAuthentication;
use YdbPlatform\Ydb\Ydb;

// Iam::token_temp_file() serializes the whole config, including credentials -
// which Ydb::__construct() attaches the user's logger to. Many real PSR
// loggers hold a Closure somewhere, which PHP's serialize() rejects
// outright. See ydb-platform/ydb-php-sdk#143.
class AuthSerializationWithLoggerTest extends TestCase
{
    private function loggerWithClosure(): AbstractLogger
    {
        return new class extends AbstractLogger {
            private $formatter;

            public function __construct()
            {
                $this->formatter = function ($message) {
                    return (string) $message;
                };
            }

            public function log($level, $message, array $context = []): void
            {
            }
        };
    }

    public function testQueryingWithAnonymousAuthAndALoggerHoldingAClosureDoesNotThrow(): void
    {
        $config = [
            'database' => '/local',
            'endpoint' => 'localhost:2136',
            'discovery' => false,
            'iam_config' => ['insecure' => true],
            'credentials' => new AnonymousAuthentication(),
            'logger' => $this->loggerWithClosure(),
        ];

        $ydb = new Ydb($config);

        self::assertNotNull($ydb->table()->query('SELECT 1;'));
    }

    public function testCredentialsWithALoggerHoldingAClosureAreSerializable(): void
    {
        $auth = new AnonymousAuthentication();
        $auth->setLogger($this->loggerWithClosure());

        self::assertIsString(serialize($auth));
    }

    // StaticAuthentication::setYdbConnectionConfig() stashes a whole nested
    // Ydb instance in $ydb - a second, independent path to the same bug.
    public function testStaticAuthenticationWithANestedYdbAndLoggerIsSerializable(): void
    {
        $auth = new StaticAuthentication('testuser', 'testpassword');
        $auth->setLogger($this->loggerWithClosure());
        $auth->setYdbConnectionConfig([
            'database' => '/local',
            'endpoint' => 'localhost:2136',
            'discovery' => false,
            'iam_config' => ['insecure' => true],
        ]);

        self::assertIsString(serialize($auth));
    }
}
