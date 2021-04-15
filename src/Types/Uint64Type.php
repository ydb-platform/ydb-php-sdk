<?php

namespace YandexCloud\Ydb\Types;

class Uint64Type extends Int64Type
{
    /**
     * @inherit
     */
    protected $unsigned = true;
}
