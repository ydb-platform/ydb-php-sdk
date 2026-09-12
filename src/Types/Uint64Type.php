<?php

namespace YdbPlatform\Ydb\Types;

class Uint64Type extends Int64Type
{
    // 2^64, for recovering the true unsigned value from a signed-wrapped one - see toUnsigned().
    private const TWO_POW_64 = '18446744073709551616';

    /**
     * @inherit
     */
    protected $unsigned = true;

    /**
     * @inherit
     */
    protected $ydb_type = 'UINT64';

    /**
     * QueryResult returns a Uint64 column above PHP_INT_MAX as its signed 64-bit
     * bit-pattern wrap (e.g. 18446744073709551615 comes back as -1), since PHP
     * has no native type for the full range - this recovers the original value.
     *
     * @param int|string $value as returned by QueryResult for a Uint64 column
     * @return string
     */
    public static function toUnsigned($value): string
    {
        return bccomp((string)$value, '0') < 0
            ? bcadd((string)$value, self::TWO_POW_64)
            : (string)$value;
    }
}
