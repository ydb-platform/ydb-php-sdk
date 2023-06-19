<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

class OverloadedException extends \YdbPlatform\Ydb\Exceptions\RetryableException
{
    public function isFastBackoff(): bool
    {
        return false;
    }
}
