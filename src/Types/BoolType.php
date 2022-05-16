<?php

namespace YdbPlatform\Ydb\Types;

class BoolType extends AbstractType
{
    /**
     * @inherit
     */
    protected $ydb_key_name = 'bool_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'BOOL';

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        return (bool)$value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return $this->value ? 'true' : 'false';
    }

    /**
     * @inherit
     */
    protected function getYdbValue()
    {
        return (int)$this->value;
    }
}
