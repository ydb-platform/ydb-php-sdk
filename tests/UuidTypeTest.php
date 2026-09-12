<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Types\UuidType;
use YdbPlatform\Ydb\Ydb;

// Uuid used to write/read via bytes_value/dechex() of one 64-bit half only. See ydb-platform/ydb-php-sdk#146.
class UuidTypeTest extends TestCase
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

    // low128/high128 confirmed live against a real YDB server for this exact UUID.
    public function testToPartsMatchesTheServerConfirmedWireValues(): void
    {
        [$low, $high] = UuidType::toParts('6e73b41c-4ede-4d08-9cfb-b7462d9e498b');

        self::assertSame('5550773257976919068', (string) $low);
        self::assertSame('-8410016911840511076', (string) $high);
    }

    public function testFromPartsIsTheInverseOfToParts(): void
    {
        $uuid = '6e73b41c-4ede-4d08-9cfb-b7462d9e498b';
        [$low, $high] = UuidType::toParts($uuid);

        self::assertSame($uuid, UuidType::fromParts($low, $high));
    }

    // high128 commonly exceeds PHP_INT_MAX and arrives as a decimal string.
    public function testFromPartsAcceptsUnsignedDecimalStringsPastPhpIntMax(): void
    {
        self::assertSame(
            '6e73b41c-4ede-4d08-9cfb-b7462d9e498b',
            UuidType::fromParts('5550773257976919068', '10036727161869040540')
        );
    }

    public function testUuidRoundTripsThroughTheSdk(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/uuid_type_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE uuid_type_test (id Int32 NOT NULL, u Uuid, PRIMARY KEY (id))');

        $uuid = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';
        $session->prepare('DECLARE $id AS Int32; DECLARE $u AS Uuid; UPSERT INTO uuid_type_test (id, u) VALUES ($id, $u);')
            ->execute(['id' => 1, 'u' => $uuid]);

        $result = $session->query('SELECT u FROM uuid_type_test WHERE id = 1');

        self::assertSame(strtolower($uuid), strtolower($result->rows()[0]['u']));

        $session->schemeQuery('DROP TABLE `/local/uuid_type_test`');
    }

    public function testNullUuidRoundTrips(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/uuid_type_null_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE uuid_type_null_test (id Int32 NOT NULL, u Uuid, PRIMARY KEY (id))');

        $session->prepare('DECLARE $id AS Int32; DECLARE $u AS Optional<Uuid>; UPSERT INTO uuid_type_null_test (id, u) VALUES ($id, $u);')
            ->execute(['id' => 1, 'u' => null]);

        $result = $session->query('SELECT u FROM uuid_type_null_test WHERE id = 1');

        self::assertNull($result->rows()[0]['u']);

        $session->schemeQuery('DROP TABLE `/local/uuid_type_null_test`');
    }
}
