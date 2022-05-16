<?php

namespace YdbPlatform\Ydb\Types;

class Uint16Type extends Int16Type
{
    /**
     * @inherit
     */
    protected $unsigned = true;

    /**
     * @inherit
     */
    protected $ydb_type = 'UINT16';
}
