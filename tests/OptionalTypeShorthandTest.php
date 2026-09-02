<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Ydb;

// "T?" is YQL shorthand for "Optional<T>" - valueOfType() only understood the
// "Optional<T>" prefix form before this fix. See ydb-platform/ydb-php-sdk#17.
class OptionalTypeShorthandTest extends TestCase
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

        return (new Ydb($config))->table()->session();
    }

    public function testQuestionMarkShorthandAcceptsNull(): void
    {
        $session = $this->makeSession();

        $result = $session->prepare('DECLARE $v AS Utf8?; SELECT $v AS val;')
            ->execute(['v' => null]);

        self::assertNull($result->rows()[0]['val']);
    }

    public function testQuestionMarkShorthandAcceptsANonNullValue(): void
    {
        $session = $this->makeSession();

        $result = $session->prepare('DECLARE $v AS Utf8?; SELECT $v AS val;')
            ->execute(['v' => 'hello']);

        self::assertEquals('hello', $result->rows()[0]['val']);
    }

    public function testQuestionMarkShorthandWorksForOtherPrimitiveTypesToo(): void
    {
        $session = $this->makeSession();

        $result = $session->prepare('DECLARE $v AS Int32?; SELECT $v AS val;')
            ->execute(['v' => null]);

        self::assertNull($result->rows()[0]['val']);
    }

    // Regression guard: a bare (non-Optional) type must still reject null.
    public function testBareTypeStillRejectsNull(): void
    {
        $session = $this->makeSession();

        $this->expectException(\Throwable::class);

        $session->prepare('DECLARE $v AS Utf8; SELECT $v AS val;')
            ->execute(['v' => null]);
    }
}
