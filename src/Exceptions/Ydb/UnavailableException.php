<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

class UnavailableException extends \YdbPlatform\Ydb\Exceptions\RetryableException
{
    public function isFastBackoff(): bool
    {
        return true;
    }

}
