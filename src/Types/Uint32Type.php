<?php

namespace YandexCloud\Ydb\Types;

class Uint32Type extends UintType
{
    /**
     * @inherit
     */
    protected $ydb_type = 'UINT32';
}
