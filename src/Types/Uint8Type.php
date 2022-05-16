<?php

namespace YdbPlatform\Ydb\Types;

class Uint8Type extends Int8Type
{
    /**
     * @inherit
     */
    protected $unsigned = true;

    /**
     * @inherit
     */
    protected $ydb_type = 'UINT8';
}
