<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

use YdbPlatform\Ydb\Exceptions\RetryableException;

class AbortedException extends RetryableException
{
    public function isFastBackoff(): bool
    {
        return true;
    }
}
