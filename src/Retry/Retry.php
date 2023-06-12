<?php

namespace YdbPlatform\Ydb\Retry;

use Closure;
use YdbPlatform\Ydb\Exceptions\NonRetryableException;
use YdbPlatform\Ydb\Exceptions\RetryableException;

class Retry
{

    protected function retryDelay(int $retryCount, Backoff $backoff)
    {
        return $backoff->getBackoffSlotMillis()*(1<<min($retryCount, $backoff->getBackoffCeiling()));
    }

    /**
     * @throws NonRetryableException
     * @throws RetryableException
     */
    public function retry(Closure $closure, RetryParams $params, bool $idempotent){
        $startTime = microtime(true);
        $retryCount = 0;
        $lastException = null;
        while (microtime(true) < $startTime+$params->getTimeoutMs()/1000){
            try {
                return $closure();
            } catch (RetryableException $e){
                $retryCount++;
                $this->retryDelay($retryCount,
                    $e->isFastBackoff() ? $params->getFastBackOff() : $params->getSlowBackOff());
                $lastException = $e;
            } catch (NonRetryableException $e){
                throw $e;
            }
        }
        throw $lastException;
    }


}
