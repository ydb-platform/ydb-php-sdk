<?php

namespace YandexCloud\Ydb\Types;

class Int64Type extends IntType
{
    /**
     * @inherit
     */
    protected $bits = 64;

    /**
     * @inherit
     */
    protected $ydb_key_name = 'int64_value';
}
