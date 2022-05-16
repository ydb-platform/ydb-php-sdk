<?php

namespace YdbPlatform\Ydb\Types;

class Utf8Type extends AbstractType
{
    /**
     * @inherit
     */
    protected $ydb_key_name = 'text_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'UTF8';
}
