<?php

namespace YdbPlatform\Ydb\Types;

class Int16Type extends IntType
{
    /**
     * @inherit
     */
    protected $bits = 16;

    /**
     * @inherit
     */
    protected $ydb_type = 'INT16';
}
