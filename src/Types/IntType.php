<?php

namespace YdbPlatform\Ydb\Types;

class IntType extends AbstractType
{
    // 2^64, for folding an overflowed Uint64 into its signed 64-bit bit pattern.
    private const TWO_POW_64 = '18446744073709551616';

    /**
     * @var bool
     */
    protected $unsigned = false;

    /**
     * @var int
     */
    protected $bits = 32;

    /**
     * @inherit
     */
    protected $ydb_key_name = 'int32_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'INT32';

    /**
     * @param bool $unsigned
     * @return $this
     */
    public function unsigned($unsigned = true)
    {
        $this->unsigned = (bool)$unsigned;
        return $this;
    }

    /**
     * @param int $bits
     * @return $this
     */
    public function bits($bits)
    {
        $this->bits = (int)$bits;
        return $this;
    }

    /**
     * @inherit
     */
    public function getYdbType()
    {
        if ($this->bits === 8)
        {
            $this->ydb_type = $this->unsigned ? 'UINT8' : 'INT8';
        }
        else if ($this->bits === 16)
        {
            $this->ydb_type = $this->unsigned ? 'UINT16' : 'INT16';
        }
        else if ($this->bits === 32)
        {
            $this->ydb_type = $this->unsigned ? 'UINT32' : 'INT32';
        }
        else if ($this->bits === 64)
        {
            $this->ydb_type = $this->unsigned ? 'UINT64' : 'INT64';
        }

        return parent::getYdbType();
    }

    /**
     * @inherit
     */
    protected function getYdbKeyName()
    {
        if ($this->bits === 64)
        {
            return $this->unsigned ? 'uint64_value' : 'int64_value';
        }
        return $this->unsigned ? 'uint32_value' : 'int32_value';
    }

    /**
     * @inherit
     */
    protected function getYdbValue()
    {
        if ($this->bits !== 64)
        {
            return $this->value;
        }

        // Fold into the signed-64 bit pattern (wire setter clamps otherwise) at an explicit scale.
        if (is_string($this->value))
        {
            return bccomp($this->value, (string)PHP_INT_MAX) > 0
                ? bcsub($this->value, self::TWO_POW_64, 0)
                : (string)(int)$this->value;
        }

        return (string)$this->value;
    }

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        // Keep an overflowed Uint64 as a string - (int) would silently clamp it.
        if ($this->bits === 64 && $this->unsigned && bccomp((string)$value, (string)PHP_INT_MAX) > 0)
        {
            return (string)$value;
        }

        return (int)$value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return (string)$this->value;
    }
}
