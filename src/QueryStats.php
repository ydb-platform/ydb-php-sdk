<?php

namespace YdbPlatform\Ydb;

class QueryStats
{

    private $query_phases;
    protected $compilation = null;
    protected $process_cpu_time_us = 0;
    protected $query_plan = '';
    protected $query_ast = '';
    protected $total_duration_us = 0;
    protected $total_cpu_time_us = 0;
    public function __construct(\Ydb\TableStats\QueryStats $queryStats)
    {
        $this->compilation = $queryStats->getCompilation();
        $this->query_ast = $queryStats->getQueryAst();
        $this->query_plan = $queryStats->getQueryPlan();
        $this->query_phases = $queryStats->getQueryPhases();
        $this->total_cpu_time_us = $queryStats->getTotalCpuTimeUs();
        $this->total_duration_us = $queryStats->getTotalDurationUs();
        $this->process_cpu_time_us = $queryStats->getProcessCpuTimeUs();
    }

    /**
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getQueryPhases(): \Google\Protobuf\Internal\RepeatedField
    {
        return $this->query_phases;
    }

    /**
     * @return \Ydb\TableStats\CompilationStats|null
     */
    public function getCompilation(): ?\Ydb\TableStats\CompilationStats
    {
        return $this->compilation;
    }

    /**
     * @return int|string
     */
    public function getProcessCpuTimeUs()
    {
        return $this->process_cpu_time_us;
    }

    /**
     * @return string
     */
    public function getQueryPlan(): string
    {
        return $this->query_plan;
    }

    /**
     * @return string
     */
    public function getQueryAst(): string
    {
        return $this->query_ast;
    }

    /**
     * @return int|string
     */
    public function getTotalDurationUs()
    {
        return $this->total_duration_us;
    }

    /**
     * @return int|string
     */
    public function getTotalCpuTimeUs()
    {
        return $this->total_cpu_time_us;
    }

}
