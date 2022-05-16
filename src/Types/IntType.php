<?php

namespace YdbPlatform\Ydb\Types;

class IntType extends AbstractType
{
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
        return $this->bits === 64 ? (string)$this->value : $this->value;
    }

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        if ($value < 0)
        {
            $this->unsigned = true;
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
