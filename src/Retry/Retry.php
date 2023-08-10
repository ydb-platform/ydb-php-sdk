<?php

namespace YdbPlatform\Ydb\Retry;

use Closure;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use Psr\Log\LoggerInterface;

class Retry
{

    protected $timeoutMs;

    protected $slowBackOff;

    protected $fastBackOff;
    protected $noBackOff;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->timeoutMs = 2000;
        $this->fastBackOff = new Backoff(6, 5);
        $this->slowBackOff = new Backoff(6, 1000);
        $this->noBackOff = new Backoff(0, 0);
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
     * @throws Exception
     */
    public function retry(Closure $closure, bool $idempotent)
    {
        $startTime = microtime(true);
        $retryCount = 0;
        $lastException = null;
        $deadline = is_null($this->timeoutMs) ? PHP_INT_MAX : $startTime + $this->timeoutMs / 1000;
        $this->logger->debug("YDB: begin retry function. Deadline: $deadline");
        do {
            $this->logger->debug("YDB: Run user function. Retry count: $retryCount. s: ".(microtime(true) - $startTime));
            try {
                return $closure();
            } catch (\Exception $e) {
                $this->logger->debug("YDB: Received exception: ".$e->getMessage());
                $lastException = $e;
                if (!$this->canRetry($e, $idempotent)){
                    break;
                }
                $retryCount++;
                $delay = $this->retryDelay($retryCount, $this->backoffType(get_class($e)))*1000;
                $this->logger->debug("YDB: Sleep $delay microseconds before retry");
                usleep($delay);
            }
        } while (microtime(true) < $deadline);
        $this->logger->error("YDB: Timeout retry function. ms: "
            .((microtime(true)-$startTime)*1000). "Retry count: $retryCount");
        throw $lastException;
    }

    /**
     * @param string $e
     * @return Backoff
     */
    protected function backoffType(string $e): Backoff
    {
        return in_array($e, self::$immediatelyBackoff) ? $this->noBackOff :
            (in_array($e, self::$fastBackoff) ? $this->fastBackOff : $this->slowBackOff);
    }

    protected function alwaysRetry(string $exception)
    {
        return in_array($exception, self::$alwaysRetry);
    }

    protected function canRetry(\Exception $e, bool $idempotent)
    {
        return is_a($e, RetryableException::class) && ($this->alwaysRetry(get_class($e)) || $idempotent);
    }

    private static $immediatelyBackoff = [
        \YdbPlatform\Ydb\Exceptions\Grpc\AbortedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException::class,
    ];

    private static $fastBackoff = [
        \YdbPlatform\Ydb\Exceptions\Grpc\CanceledException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\InternalException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\AbortedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\UnavailableException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\CancelledException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\UndeterminedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException::class,
    ];

    private static $alwaysRetry = [
        \YdbPlatform\Ydb\Exceptions\Grpc\ResourceExhaustedException::class,
        \YdbPlatform\Ydb\Exceptions\Grpc\AbortedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\AbortedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\UnavailableException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\OverloadedException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException::class,
        \YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException::class,
    ];

}
