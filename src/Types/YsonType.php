<?php

namespace YdbPlatform\Ydb\Types;

class YsonType extends AbstractType
{
    /**
     * @inherit
     */
    protected $ydb_key_name = 'text_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'Yson';

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Yson(@@' . $this->value . '@@)';
    }
}
