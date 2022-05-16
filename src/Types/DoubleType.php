<?php

namespace YdbPlatform\Ydb\Types;

class DoubleType extends AbstractType
{
    /**
     * @inherit
     */
    protected $ydb_key_name = 'double_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'DOUBLE';

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
        return 'Double(' . $this->quoteString($this->value) . ')';
    }
}
