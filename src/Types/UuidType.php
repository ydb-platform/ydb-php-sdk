<?php

namespace YdbPlatform\Ydb\Types;

use Ydb\Type;
use Ydb\Value;
use Ydb\Type\PrimitiveTypeId;

use YdbPlatform\Ydb\Exception;

class UuidType extends AbstractType
{
    // 2^64, for folding an unsigned 64-bit half into its signed bit pattern.
    private const TWO_POW_64 = '18446744073709551616';

    /**
     * @var string
     */
    protected $ydb_type = 'UUID';

    /**
     * @inherit
     */
    public function toYdbValue()
    {
        if ($this->value === null)
        {
            return new Value(['null_flag_value' => \Google\Protobuf\NullValue::NULL_VALUE]);
        }

        [$low, $high] = self::toParts($this->value);

        return new Value(['low_128' => $low, 'high_128' => $high]);
    }

    /**
     * @inherit
     */
    public function getYdbType()
    {
        return new Type(['type_id' => PrimitiveTypeId::UUID]);
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
        return 'Uuid("' . $this->value . '")';
    }

    /**
     * Splits a UUID string into YDB's [low_128, high_128] wire format - the
     * "bytes_le" convention (Python's `uuid.UUID(bytes_le=...)`), verified live.
     *
     * @param string $uuid
     * @return array{0: int, 1: int}
     * @throws Exception
     */
    public static function toParts($uuid)
    {
        $bytes = hex2bin(str_replace('-', '', $uuid));

        if ($bytes === false || strlen($bytes) !== 16)
        {
            throw new Exception('YDB: Invalid UUID [' . $uuid . '].');
        }

        $lowBytes = strrev(substr($bytes, 0, 4)) . strrev(substr($bytes, 4, 2)) . strrev(substr($bytes, 6, 2));
        $highBytes = substr($bytes, 8, 8);

        return [unpack('P', $lowBytes)[1], unpack('P', $highBytes)[1]];
    }

    /**
     * The inverse of self::toParts(). Accepts an unsigned decimal string past
     * PHP_INT_MAX too, e.g. straight out of a JSON-decoded protobuf response.
     *
     * @param int|string $low
     * @param int|string $high
     * @return string
     */
    public static function fromParts($low, $high)
    {
        $lowBytes = pack('P', self::toSigned64($low));
        $highBytes = pack('P', self::toSigned64($high));

        $bytes = strrev(substr($lowBytes, 0, 4))
            . strrev(substr($lowBytes, 4, 2))
            . strrev(substr($lowBytes, 6, 2))
            . $highBytes;

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * @param int|string $value an unsigned 64-bit int, or a decimal string of one
     * @return int the same bit pattern as a signed PHP int
     */
    private static function toSigned64($value)
    {
        if (is_int($value) || bccomp((string) $value, (string) PHP_INT_MAX) <= 0)
        {
            return (int) $value;
        }

        return (int) bcsub((string) $value, self::TWO_POW_64, 0);
    }
}
