<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Ydb;

// Two related bugs, both from PHP_INT_MAX overflow handling. See ydb-platform/ydb-php-sdk#148.
class Uint64PrecisionTest extends TestCase
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

    // Every value here used to be clamped to 9223372036854775807 on write.
    public function testToYdbValuePreservesFullPrecisionPastPhpIntMax(): void
    {
        $cases = [
            '9223372036854775807' => '9223372036854775807',
            '9223372036854775808' => '9223372036854775808',
            '18446744073709551615' => '18446744073709551615',
            '12345678901234567890' => '12345678901234567890',
        ];

        foreach ($cases as $input => $expectedWireValue) {
            $wire = (new Uint64Type($input))->toYdbValue();
            $json = json_decode($wire->serializeToJsonString(), true);
            self::assertSame($expectedWireValue, $json['uint64Value'], "input $input");
        }
    }

    public function testUint64RoundTripsThroughTheSdk(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/uint64_precision_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE uint64_precision_test (id Int32 NOT NULL, v Uint64, PRIMARY KEY (id))');

        // Above PHP_INT_MAX, the signed 64-bit wrap is expected (see IntType).
        $cases = [
            '0' => '0',
            '1' => '1',
            '9223372036854775806' => '9223372036854775806',
            '9223372036854775807' => '9223372036854775807', // the documented bug: this must NOT come back as PHP_INT_MIN
            '9223372036854775808' => '-9223372036854775808',
            '18446744073709551615' => '-1',
            '12345678901234567890' => '-6101065172474983726',
        ];

        $id = 0;
        foreach ($cases as $written => $_) {
            $session->prepare('DECLARE $id AS Int32; DECLARE $v AS Uint64; UPSERT INTO uint64_precision_test (id, v) VALUES ($id, $v);')
                ->execute(['id' => $id, 'v' => $written]);
            $id++;
        }

        $result = $session->query('SELECT id, v FROM uint64_precision_test ORDER BY id');
        $expectedValues = array_values($cases);
        foreach ($result->rows() as $row) {
            self::assertSame((string) $expectedValues[$row['id']], (string) $row['v'], "row id {$row['id']}");
        }

        $session->schemeQuery('DROP TABLE `/local/uint64_precision_test`');
    }

    public function testToUnsignedRecoversTheOriginalValue(): void
    {
        $cases = [
            '0' => '0',
            '1' => '1',
            '9223372036854775806' => '9223372036854775806',
            '9223372036854775807' => '9223372036854775807',
            '-9223372036854775808' => '9223372036854775808',
            '-1' => '18446744073709551615',
            '-6101065172474983726' => '12345678901234567890',
        ];

        foreach ($cases as $wrapped => $expected) {
            self::assertSame($expected, Uint64Type::toUnsigned($wrapped), "wrapped value $wrapped");
        }
    }
}
