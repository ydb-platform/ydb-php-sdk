<?php

namespace YdbPlatform\Ydb\QueryStats;

class QueryPhaseStats
{
    /**
     * @var \Ydb\TableStats\QueryPhaseStats
     */
    protected $phaseStats;

    public function __construct(\Ydb\TableStats\QueryPhaseStats $phaseStats)
    {
        $this->phaseStats = $phaseStats;
    }


    /**
     * @return int|string
     */
    public function getDurationUs()
    {
        return $this->phaseStats->getDurationUs();
    }

    /**
     * @return TableAccessStats[]
     */
    public function getTableAccess()
    {
        $result = iterator_to_array($this->phaseStats->getTableAccess());
        foreach ($result as $key=>$item) {
            $result[$key] = new TableAccessStats($item);
        }
        return $result;
    }

    /**
     * @return int|string
     */
    public function getCpuTimeUs()
    {
        return $this->phaseStats->getCpuTimeUs();
    }

    /**
     * @return int|string
     */
    public function getAffectedShards()
    {
        return $this->phaseStats->getAffectedShards();
    }

    /**
     * @return bool
     */
    public function getLiteralPhase()
    {
        return $this->phaseStats->getLiteralPhase();
    }
}
