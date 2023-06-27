<?php

namespace YdbPlatform\Ydb\Retry;

class Backoff
{
    protected $backoffCeiling;
    protected $backoffSlotMillis;

    /**
     * @param $backoffCeiling
     * @param $backoffSlotMillis
     */
    public function __construct($backoffCeiling, $backoffSlotMillis)
    {
        $this->backoffCeiling = $backoffCeiling;
        $this->backoffSlotMillis = $backoffSlotMillis;
    }

    /**
     * @return mixed
     */
    public function getBackoffCeiling()
    {
        return $this->backoffCeiling;
    }

    /**
     * @return mixed
     */
    public function getBackoffSlotMillis()
    {
        return $this->backoffSlotMillis;
    }

}
