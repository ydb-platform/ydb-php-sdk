<?php

namespace YdbPlatform\Ydb\Retry;

use Closure;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use YdbPlatform\Ydb\Logger\LoggerInterface;

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
        while (microtime(true) < $startTime + $this->timeoutMs / 1000) {
            $this->logger->debug("YDB: Run user function. Retry count: $retryCount. s: ".(microtime(true) - $startTime));
            try {
                $this->logger->debug("YDB DEBUG run \$closure in retry");
                return $closure();
            } catch (Exception $e) {
                $this->logger->warning("YDB: Received exception: ".$e->getMessage());
                $this->logger->debug("YDB DEBUG retryable: ".$this->canRetry($e, $idempotent));
                if (!$this->canRetry($e, $idempotent)){
                    $lastException = $e;
                    break;
                }
                $retryCount++;
                $lastException = $e;
                $delay = $this->retryDelay($retryCount, $this->backoffType(get_class($e)))*1000;
                $this->logger->debug("YDB DEBUG sleep".$delay);
                usleep($delay);
            }
        }
        $this->logger->debug("YDB DEBUG end while");
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

    protected function canRetry(Exception $e, bool $idempotent)
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
