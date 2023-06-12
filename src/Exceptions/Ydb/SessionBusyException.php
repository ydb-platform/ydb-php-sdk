<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

use YdbPlatform\Ydb\Exceptions\RetryableException;

class SessionBusyException extends RetryableException
{
    public function isFastBackoff(): bool
    {
        return true;
    }
}
