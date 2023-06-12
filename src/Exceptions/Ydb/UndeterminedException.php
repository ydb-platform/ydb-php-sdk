<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

use YdbPlatform\Ydb\Exceptions\RetryableException;

class UndeterminedException extends RetryableException
{
    public function isFastBackoff(): bool
    {
        return true;
    }
}
