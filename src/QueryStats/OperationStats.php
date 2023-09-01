<?php

namespace YdbPlatform\Ydb\QueryStats;

class OperationStats
{

    public function __construct(\Ydb\TableStats\OperationStats $operationStats)
    {
        $this->operationStats = $operationStats;
    }

    /**
     * @return int|string
     */
    public function getRows()
    {
        return $this->operationStats->getRows();
    }

    /**
     * @return int|string
     */
    public function getBytes()
    {
        return $this->operationStats->getBytes();
    }
}
