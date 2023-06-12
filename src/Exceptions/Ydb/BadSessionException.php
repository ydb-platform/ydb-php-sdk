<?php

namespace YdbPlatform\Ydb\Exceptions\Ydb;

use Ydb\StatusIds\StatusCode;

class BadSessionException extends \YdbPlatform\Ydb\Exceptions\RetryableException
{
    public function isFastBackoff(): bool
    {
        return true;
    }
}
