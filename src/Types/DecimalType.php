<?php

namespace YdbPlatform\Ydb\Types;

use Ydb\Type;
use Ydb\Value;
use Ydb\DecimalType as YdbDecimalType;

use Google\Protobuf\NullValue;

class DecimalType extends AbstractType
{
    // 2^64, for converting between a fixed64's signed PHP-int bit pattern and its unsigned decimal value.
    private const TWO_POW_64 = '18446744073709551616';

    /**
     * @var int
     */
    protected $digits = 22;

    /**
     * @var int
     */
    protected $scale = 9;

    /**
     * @param int $digits
     * @return $this
     */
    public function digits($digits)
    {
        $this->digits = $digits;
        return $this;
    }

    /**
     * @param int $scale
     * @return $this
     */
    public function scale($scale)
    {
        $this->scale = $scale;
        return $this;
    }

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        // Keeps full precision - (float) would already lose it for values with
        // more significant digits than a double can represent exactly.
        return (string) $value;
    }

    /**
     * @inherit
     */
    public function toYdbValue()
    {
        if ($this->value === null)
        {
            return new Value(['null_flag_value' => NullValue::NULL_VALUE]);
        }

        [$low, $high] = self::toParts($this->value, $this->scale);

        return new Value(['low_128' => $low, 'high_128' => $high]);
    }

    /**
     * @inherit
     */
    public function getYdbType()
    {
        return new Type([
            'decimal_type' => new YdbDecimalType([
                'precision' => $this->digits,
                'scale' => $this->scale,
            ]),
        ]);
    }

    /**
     * @inherit
     */
    public function toYdbType()
    {
        return $this->getYdbType();
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Decimal(' . $this->quoteString($this->value) . ', ' . $this->digits . ', ' . $this->scale . ')';
    }

    /**
     * Splits a decimal string into YDB's [low_128, high_128] wire format: one
     * signed 128-bit unscaled integer (value * 10^scale) across two fixed64
     * fields - verified live against a real server response.
     *
     * @param string $decimal
     * @param int $scale
     * @return array{0: int, 1: int}
     */
    public static function toParts($decimal, $scale)
    {
        $unscaled = self::toUnscaled((string) $decimal, $scale);

        // Split into low/high 64-bit halves (unscaled = high * 2^64 + low) - explicit scale, bcmod()/bcdiv() affect the actual division under ambient bcscale(), not just formatting.
        $low = bcmod($unscaled, self::TWO_POW_64, 0);
        if (bccomp($low, '0') < 0)
        {
            $low = bcadd($low, self::TWO_POW_64, 0);
        }
        $high = bcdiv(bcsub($unscaled, $low, 0), self::TWO_POW_64, 0);

        return [self::toSigned64($low), self::toSigned64($high)];
    }

    /**
     * The inverse of self::toParts(). $low/$high may be unsigned decimal
     * strings past PHP_INT_MAX, e.g. straight out of a JSON-decoded protobuf
     * response.
     *
     * @param int|string $low
     * @param int|string $high
     * @param int $scale
     * @return string
     */
    public static function fromParts($low, $high, $scale)
    {
        // Reassemble: unscaled = high * 2^64 + low.
        $int128 = bcadd(self::toUnsigned64($low), bcmul(self::toSigned64($high), self::TWO_POW_64));

        if (bccomp($int128, bcadd(bcpow('10', '35'), '1')) === 0)
        {
            return 'NaN';
        }
        if (bccomp($int128, bcpow('10', '35')) === 0)
        {
            return 'Inf';
        }
        if (bccomp($int128, bcmul('-1', bcpow('10', '35'))) === 0)
        {
            return '-Inf';
        }

        return bcdiv($int128, bcpow('10', (string) $scale), (int) $scale);
    }

    private static function toUnscaled($decimal, $scale)
    {
        switch (strtolower($decimal))
        {
            case 'nan':
                return bcadd(bcpow('10', '35'), '1');
            case 'inf':
            case '+inf':
                return bcpow('10', '35');
            case '-inf':
                return bcmul('-1', bcpow('10', '35'));
        }

        // Explicit high intermediate scale keeps the shift itself exact regardless
        // of the ambient bcscale() setting, then round half away from zero to the
        // target scale (bcmath's scale reduction truncates, so +-0.5 first).
        $shifted = bcmul($decimal, bcpow('10', (string) $scale), 40);

        return bccomp($shifted, '0') >= 0
            ? bcadd($shifted, '0.5', 0)
            : bcsub($shifted, '0.5', 0);
    }

    /**
     * @param int|string $value an unsigned 64-bit int, or a decimal string of one
     * @return int the same bit pattern as a signed PHP int
     */
    private static function toSigned64($value)
    {
        if (is_int($value) || bccomp((string) $value, (string) PHP_INT_MAX) <= 0)
        {
            // Bit pattern already fits an unsigned value below 2^63 - signed and unsigned agree here.
            return (int) $value;
        }

        // Above PHP_INT_MAX (bit 63 set): subtract 2^64 (at an explicit scale, ignoring ambient bcscale()).
        return (int) bcsub((string) $value, self::TWO_POW_64, 0);
    }

    /**
     * The inverse of self::toSigned64().
     *
     * @param int|string $value
     * @return string
     */
    private static function toUnsigned64($value)
    {
        if (bccomp((string) $value, '0') < 0)
        {
            // Negative (bit 63 set): add 2^64 back (at an explicit scale, ignoring ambient bcscale()).
            return bcadd((string) $value, self::TWO_POW_64, 0);
        }

        return (string) $value;
    }
}
