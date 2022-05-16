<?php

namespace YdbPlatform\Ydb\Traits;

use  YdbPlatform\Ydb\Logger\NullLogger;

trait LoggerTrait
{
    /**
     * @return NullLogger
     */
    protected function logger()
    {
        if ($this->logger)
        {
            return $this->logger;
        }
        else
        {
            return new NullLogger;
        }
    }
}