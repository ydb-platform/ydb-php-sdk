<?php

namespace YandexCloud\Ydb\Types;

class UintType extends IntType
{
    /**
     * @inherit
     */
    protected $unsigned = true;
}
