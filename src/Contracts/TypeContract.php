<?php

namespace YdbPlatform\Ydb\Contracts;

use Ydb\Type;
use Ydb\Value;
use Ydb\TypedValue;

interface TypeContract
{
    /**
     * @return string
     */
    public function __toString();

    /**
     * @return string
     */
    public function toYqlString();

    /**
     * @return mixed
     */
    public function toYdbValue();

    /**
     * @return Value
     */
    public function getYdbType();

    /**
     * @return Type
     */
    public function toYdbType();

    /**
     * @return TypedValue
     */
    public function toTypedValue();
}
