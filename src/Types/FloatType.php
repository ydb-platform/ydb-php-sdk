<?php

namespace YdbPlatform\Ydb\Types;

class FloatType extends AbstractType
{
    /**
     * @inherit
     */
    protected $ydb_key_name = 'float_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'FLOAT';

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        return (float)$value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Float(' . $this->quoteString($this->value) . ')';
    }
}
