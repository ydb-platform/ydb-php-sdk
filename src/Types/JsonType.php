<?php

namespace YdbPlatform\Ydb\Types;

class JsonType extends AbstractType
{
    /**
     * @inherit
     */
    protected $ydb_key_name = 'text_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'JSON';

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        if (is_object($value) || is_array($value))
        {
            $value = json_encode($value);
        }
        return $value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Json(@@' . $this->value . '@@)';
    }
}
