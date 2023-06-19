<?php

namespace YdbPlatform\Ydb\Retry;

use Closure;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException;
use YdbPlatform\Ydb\Exceptions\NonRetryableException;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use YdbPlatform\Ydb\Exceptions\Ydb\AbortedException;
use YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException;
use YdbPlatform\Ydb\Exceptions\Ydb\UnavailableException;
use YdbPlatform\Ydb\Exceptions\Ydb\UndeterminedException;

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
        return $backoff->getBackoffSlotMillis() * (1 << min($retryCount, $backoff->getBackoffCeiling()));
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
    public function retry(Closure $closure, bool $idempotent)
    {
        $startTime = microtime(true);
        $retryCount = 0;
        $lastException = null;
        while (microtime(true) < $startTime + $this->timeoutMs / 1000) {
            try {
                return $closure();
            } catch (RetryableException $e) {
                if (isset(self::$idempotentOnly[get_class($e)]) && $idempotent) {
                    throw $e;
                }
                $retryCount++;
                $this->retryDelay($retryCount, $this->backoffType($e));
                $lastException = $e;
            } catch (Exception $e) {
                throw $e;
            }
        }
        throw $lastException;
    }

    /**
     * @param RetryableException $e
     * @return Backoff
     */
    protected function backoffType(RetryableException $e): Backoff
    {
        return $this->fastBackOff;
//        if ($e instanceof AbortedException) {
//            return $this->fastBackOff;
//        } elseif ($e instanceof BadSessionException) {
//            return $this->fastBackOff;
//        } elseif ($e instanceof SessionBusyException) {
//            return $this->fastBackOff;
//        } elseif ($e instanceof UndeterminedException) {
//            return $this->fastBackOff;
//        } elseif ($e instanceof UnavailableException) {
//            return $this->fastBackOff;
//        } elseif ($e instanceof UndeterminedException) {
//            return $this->fastBackOff;
//        } elseif ($e instanceof DeadlineExceededException) {
//            return $this->fastBackOff;
//        } else {
//            return $this->slowBackOff;
//        }
    }

    private static $idempotentOnly = [
        \YdbPlatform\Ydb\Exceptions\Grpc\CanceledException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\InternalException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\UndeterminedException::class
    ];

    private static $fastBackoff = [

    ];

}
