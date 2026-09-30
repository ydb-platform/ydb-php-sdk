<?php

namespace YdbPlatform\Ydb;

use InvalidArgumentException;
use Ydb\VirtualTimestamp;

/**
 * A Ydb.VirtualTimestamp scoped to the Ydb connection that produced it.
 */
class CommitTimestamp
{
    private $timestamp;
    private $origin;

    public function __construct(VirtualTimestamp $timestamp, Ydb $origin)
    {
        $this->timestamp = clone $timestamp;
        $this->origin = $origin;
    }

    /** @return string Decimal uint64 value */
    public function planStep(): string
    {
        return self::uint64($this->timestamp->getPlanStep());
    }

    /** @return string Decimal uint64 value */
    public function txId(): string
    {
        return self::uint64($this->timestamp->getTxId());
    }

    public function database(): string
    {
        return (string)$this->origin->database();
    }

    /** Return a copy of the original Ydb.VirtualTimestamp protobuf message. */
    public function proto(): VirtualTimestamp
    {
        return clone $this->timestamp;
    }

    /**
     * Compare timestamps lexicographically by unsigned plan_step, then tx_id.
     * Only timestamps from the same Ydb connection can be compared.
     */
    public function compareTo(self $other): int
    {
        if ($this->origin !== $other->origin) {
            throw new InvalidArgumentException('Cannot compare timestamps from different Ydb connections');
        }

        $planStep = self::compareUint64($this->planStep(), $other->planStep());
        return $planStep ?: self::compareUint64($this->txId(), $other->txId());
    }

    private static function compareUint64(string $left, string $right): int
    {
        $lengthOrder = strlen($left) <=> strlen($right);
        return $lengthOrder ?: (strcmp($left, $right) <=> 0);
    }

    private static function uint64($value): string
    {
        if ((is_int($value) && $value < 0) ||
            (is_string($value) && preg_match('/^-[0-9]+$/', $value))) {
            // The protobuf runtime can expose a uint64's bits as a signed value.
            $value = bcadd('18446744073709551616', (string)$value, 0);
        }

        $value = ltrim((string)$value, '0');
        $value = $value === '' ? '0' : $value;
        if (!ctype_digit($value) || self::compareUint64($value, '18446744073709551615') > 0) {
            throw new InvalidArgumentException('Invalid uint64 timestamp component');
        }
        return $value;
    }
}
