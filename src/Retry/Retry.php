<?php

namespace YdbPlatform\Ydb\Retry;

use Closure;
use YdbPlatform\Ydb\Exceptions\NonRetryableException;
use YdbPlatform\Ydb\Exceptions\RetryableException;

class Retry
{

    protected $timeoutMs;

    protected $slowBackOff;

    protected $fastBackOff;

    public function __construct()
    {
        $this->timeoutMs = 2000;
        $this->fastBackOff = new Backoff(6, 5);
        $this->slowBackOff = new Backoff(6, 1000);
    }

    protected function retryDelay(int $retryCount, Backoff $backoff)
    {
        return $backoff->getBackoffSlotMillis()*(1<<min($retryCount, $backoff->getBackoffCeiling()));
    }

    /**
     * @param int $timeoutMs
     */
    protected function setTimeoutMs(int $timeoutMs): void
    {
        $this->timeoutMs = $timeoutMs;
    }

    /**
     * @param Backoff $slowBackOff
     */
    protected function setSlowBackOff(Backoff $slowBackOff): void
    {
        $this->slowBackOff = $slowBackOff;
    }

    /**
     * @param Backoff $fastBackOff
     */
    protected function setFastBackOff(Backoff $fastBackOff): void
    {
        $this->fastBackOff = $fastBackOff;
    }

    public function withParams(?RetryParams $params): Retry
    {
        if (is_null($params)) return $this;
        $retry = clone $this;
        if ($params->getTimeoutMs()) $retry->setTimeoutMs($params->getTimeoutMs());
        if ($params->getFastBackOff()) $retry->setFastBackOff($params->getFastBackOff());
        if ($params->getSlowBackOff()) $retry->setSlowBackOff($params->getSlowBackOff());
        return $retry;
    }

    /**
     * @throws NonRetryableException
     * @throws RetryableException
     */
    public function retry(Closure $closure, bool $idempotent){
        $startTime = microtime(true);
        $retryCount = 0;
        $lastException = null;
        while (microtime(true) < $startTime+$this->timeoutMs/1000){
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
