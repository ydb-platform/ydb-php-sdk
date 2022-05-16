<?php

namespace YdbPlatform\Ydb\Types;

class UintType extends IntType
{
    /**
     * @inherit
     */
    protected $unsigned = true;
}
