<?php

namespace YandexCloud\Ydb\Traits;

use  YandexCloud\Ydb\Logger\NullLogger;

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