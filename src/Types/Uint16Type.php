<?php

namespace YandexCloud\Ydb\Types;

class Uint16Type extends Int16Type
{
    /**
     * @inherit
     */
    protected $unsigned = true;
}
