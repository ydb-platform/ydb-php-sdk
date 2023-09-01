<?php

namespace YdbPlatform\Ydb\QueryStats;

class CompilationStats
{
    /**
     * @var \Ydb\TableStats\CompilationStats
     */
    protected $compilationStats;

    public function __construct(\Ydb\TableStats\CompilationStats $compilationStats)
    {
        $this->compilationStats = $compilationStats;
    }

    /**
     * @return bool
     */
    public function getFromCache(): bool
    {
        return $this->compilationStats->getFromCache();
    }

    /**
     * @return int|string
     */
    public function getDurationUs()
    {
        return $this->compilationStats->getDurationUs();
    }

    /**
     * @return int|string
     */
    public function getCpuTimeUs()
    {
        return $this->compilationStats->getCpuTimeUs();
    }
}
