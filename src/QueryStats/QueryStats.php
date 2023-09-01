<?php

namespace YdbPlatform\Ydb\QueryStats;

class QueryStats
{

    protected $queryStats;
    public function __construct(\Ydb\TableStats\QueryStats $queryStats)
    {
        $this->queryStats = $queryStats;
    }

    /**
     * @return QueryPhaseStats[]
     */
    public function getQueryPhases()
    {
        $result = iterator_to_array($this->queryStats->getQueryPhases()->getIterator());
        foreach ($result as $key=>$item) {
            $result[$key] = new QueryPhaseStats($item);
        }
        return $result;
    }

    /**
     * @return CompilationStats|null
     */
    public function getCompilation(): ?CompilationStats
    {
        if($this->queryStats->getQueryPhases()){
            return new CompilationStats($this->queryStats->getCompilation());
        } else {
            return null;
        }
    }

    /**
     * @return int|string
     */
    public function getProcessCpuTimeUs()
    {
        return $this->queryStats->getProcessCpuTimeUs();
    }

    /**
     * @return string
     */
    public function getQueryPlan(): string
    {
        return $this->queryStats->getQueryPlan();
    }

    /**
     * @return string
     */
    public function getQueryAst(): string
    {
        return $this->queryStats->getQueryAst();
    }

    /**
     * @return int|string
     */
    public function getTotalDurationUs()
    {
        return $this->queryStats->getTotalDurationUs();
    }

    /**
     * @return int|string
     */
    public function getTotalCpuTimeUs()
    {
        return $this->queryStats->getTotalCpuTimeUs();
    }

}
