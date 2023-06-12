<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

class OverloadException extends \YdbPlatform\Ydb\Exceptions\RetryableException
{
    public function isFastBackoff(): bool
    {
        return false;
    }
}
