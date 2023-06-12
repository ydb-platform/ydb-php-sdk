<?php

namespace YdbPlatform\Ydb\Exceptions;

class RetryableException extends \Exception
{
    /**
     * @var bool
     */
    protected $fastBackoff;

    /**
     * @return bool
     */
    public function isFastBackoff(): bool
    {
        return $this->fastBackoff;
    }

}
