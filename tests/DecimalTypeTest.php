<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Types\DecimalType;
use YdbPlatform\Ydb\Ydb;

// DecimalType wrote as STRING/bytes_value (wrong wire type entirely) and
// QueryResult never recognized a decimalType column at all. See
// ydb-platform/ydb-php-sdk#262.
class DecimalTypeTest extends TestCase
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

    // low128/high128 confirmed live against a real YDB server for these exact values.
    public function testToPartsMatchesTheServerConfirmedWireValues(): void
    {
        [$low, $high] = DecimalType::toParts('123.45', 9);
        self::assertSame('123450000000', (string) $low);
        self::assertSame('0', (string) $high);

        [$low, $high] = DecimalType::toParts('-123.45', 9);
        self::assertSame('-1', (string) $high);
        self::assertSame(
            '-123.450000000',
            DecimalType::fromParts('18446743950259551616', '18446744073709551615', 9),
            'fromParts() must match toParts() on the raw unsigned strings the server actually returns.',
        );
        self::assertSame('-123.450000000', DecimalType::fromParts($low, $high, 9), 'fromParts() must be the inverse of toParts().');
    }

    public function testFromPartsIsTheInverseOfToPartsAcrossSeveralValues(): void
    {
        $cases = [
            '123.450000000' => '123.45',
            '-123.450000000' => '-123.45',
            '0.000000010' => '0.00000001',
            '0.000000000' => '0',
        ];

        foreach ($cases as $expected => $decimal) {
            [$low, $high] = DecimalType::toParts($decimal, 9);
            self::assertSame($expected, DecimalType::fromParts($low, $high, 9));
        }
    }

    // toUnscaled() must round half away from zero to $scale on its own, not
    // rely on the ambient bcscale() (default 0, mutable process-wide), which
    // previously caused silent truncation instead of rounding.
    public function testToPartsRoundsExcessPrecisionInsteadOfTruncating(): void
    {
        [$low, $high] = DecimalType::toParts('123.4567891235', 9);
        self::assertSame('123.456789124', DecimalType::fromParts($low, $high, 9));

        [$low, $high] = DecimalType::toParts('-123.4567891235', 9);
        self::assertSame('-123.456789124', DecimalType::fromParts($low, $high, 9));

        [$low, $high] = DecimalType::toParts('0.15', 1);
        self::assertSame('0.2', DecimalType::fromParts($low, $high, 1));

        [$low, $high] = DecimalType::toParts('-0.15', 1);
        self::assertSame('-0.2', DecimalType::fromParts($low, $high, 1));
    }

    // bcmod()/bcdiv() take scale into account in the actual division, not just formatting - confirmed
    // live this previously gave a low off by 1 under a nonzero ambient bcscale() for some inputs.
    public function testToPartsIsImmuneToAmbientBcscale(): void
    {
        $decimal = '99999999999999.999999999';

        bcscale(0);
        $expected = DecimalType::toParts($decimal, 9);

        bcscale(4);
        try {
            $actual = DecimalType::toParts($decimal, 9);
        } finally {
            bcscale(0);
        }

        self::assertSame($expected, $actual);
    }

    public function testDecimalRoundTripsThroughTheSdkViaDirectConstruction(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/decimal_type_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE decimal_type_test (id Int32 NOT NULL, d Decimal(22,9), PRIMARY KEY (id))');

        $value = (new DecimalType('123.45'))->digits(22)->scale(9);
        $session->prepare('DECLARE $id AS Int32; DECLARE $d AS Decimal(22,9); UPSERT INTO decimal_type_test (id, d) VALUES ($id, $d);')
            ->execute(['id' => 1, 'd' => $value->toTypedValue()]);

        $result = $session->query('SELECT d FROM decimal_type_test WHERE id = 1');
        self::assertSame('123.450000000', $result->rows()[0]['d']);

        $session->schemeQuery('DROP TABLE `/local/decimal_type_test`');
    }

    public function testDecimalRoundTripsThroughTheStringDeclarePath(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/decimal_type_test2`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE decimal_type_test2 (id Int32 NOT NULL, d Decimal(22,9), PRIMARY KEY (id))');

        $session->prepare('DECLARE $id AS Int32; DECLARE $d AS Decimal(22,9); UPSERT INTO decimal_type_test2 (id, d) VALUES ($id, $d);')
            ->execute(['id' => 1, 'd' => '-987.654321']);

        $result = $session->query('SELECT d FROM decimal_type_test2 WHERE id = 1');
        self::assertSame('-987.654321000', $result->rows()[0]['d']);

        $session->schemeQuery('DROP TABLE `/local/decimal_type_test2`');
    }

    public function testNullDecimalRoundTrips(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/decimal_type_null_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE decimal_type_null_test (id Int32 NOT NULL, d Decimal(22,9), PRIMARY KEY (id))');

        $session->prepare('DECLARE $id AS Int32; DECLARE $d AS Optional<Decimal(22,9)>; UPSERT INTO decimal_type_null_test (id, d) VALUES ($id, $d);')
            ->execute(['id' => 1, 'd' => null]);

        $result = $session->query('SELECT d FROM decimal_type_null_test WHERE id = 1');
        self::assertNull($result->rows()[0]['d']);

        $session->schemeQuery('DROP TABLE `/local/decimal_type_null_test`');
    }

    // high128 is omitted from the server response when it's 0 (true for any
    // value under 2^64) - the read path must not mistake that for null.
    public function testZeroDecimalIsNotMistakenForNull(): void
    {
        $session = $this->makeSession();

        try {
            $session->schemeQuery('DROP TABLE `/local/decimal_type_zero_test`');
        } catch (\Throwable $e) {
        }
        $session->schemeQuery('CREATE TABLE decimal_type_zero_test (id Int32 NOT NULL, d Decimal(22,9), PRIMARY KEY (id))');

        $session->prepare('DECLARE $id AS Int32; DECLARE $d AS Decimal(22,9); UPSERT INTO decimal_type_zero_test (id, d) VALUES ($id, $d);')
            ->execute(['id' => 1, 'd' => '0']);

        $result = $session->query('SELECT d FROM decimal_type_zero_test WHERE id = 1');
        self::assertSame('0.000000000', $result->rows()[0]['d']);

        $session->schemeQuery('DROP TABLE `/local/decimal_type_zero_test`');
    }
}
