<?php

namespace YdbPlatform\Ydb\Types;

class Int8Type extends IntType
{
    /**
     * @inherit
     */
    protected $bits = 8;

    /**
     * @inherit
     */
    protected $ydb_type = 'INT8';
}
