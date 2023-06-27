<?php

namespace YdbPlatform\Ydb\Retry;

class RetryParams
{


    protected $timeoutMs;

    protected $slowBackOff;
    protected $fastBackOff;

    public function __construct($timeoutMs = null, Backoff $fastBackOff = null, Backoff $slowBackOff = null)
    {
        $this->timeoutMs = $timeoutMs;
        $this->fastBackOff = $fastBackOff;
        $this->slowBackOff = $slowBackOff;
    }

    /**
     * @return int|null
     */
    public function getTimeoutMs()
    {
        return $this->timeoutMs;
    }

    /**
     * @return Backoff|null
     */
    public function getSlowBackOff(): ?Backoff
    {
        return $this->slowBackOff;
    }

    /**
     * @return Backoff|null
     */
    public function getFastBackOff(): ?Backoff
    {
        return $this->fastBackOff;
    }

    /**
     * @param int|mixed $timeoutMs
     */
    public function setTimeoutMs($timeoutMs): void
    {
        $this->timeoutMs = $timeoutMs;
    }

    /**
     * @param Backoff $slowBackOff
     */
    public function setSlowBackOff(Backoff $slowBackOff): void
    {
        $this->slowBackOff = $slowBackOff;
    }

    /**
     * @param Backoff $fastBackOff
     */
    public function setFastBackOff(Backoff $fastBackOff): void
    {
        $this->fastBackOff = $fastBackOff;
    }
}
