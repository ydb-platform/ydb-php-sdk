<?php

namespace YandexCloud\Ydb\Types;

class Uint8Type extends Int8Type
{
    /**
     * @inherit
     */
    protected $unsigned = true;
}
