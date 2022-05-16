<?php

namespace YdbPlatform\Ydb;

use Ydb\Table\Query;
use Ydb\Table\QueryCachePolicy;
use Ydb\Table\StaleModeSettings;
use Ydb\Table\OnlineModeSettings;
use Ydb\Table\TransactionControl;
use Ydb\Table\TransactionSettings;
use Ydb\Table\SerializableModeSettings;
use Ydb\Operations\OperationParams;

class YdbQuery
{
    /**
     * @var Session
     */
    protected $session = null;

    /**
     * @var \Ydb\Table\Query
     */
    protected $yql;

    /**
     * @var bool
     */
    protected $keep_query_in_cache = false;

    /**
     * @var int
     */
    protected $collect_stats = 1;

    /**
     * @var \Ydb\Table\TransactionControl
     */
    protected $tx_control;

    /**
     * @var \Ydb\Operations\OperationParams
     */
    protected $operation_params;

    /**
     * @var array
     */
    protected $parameters = null;

    /**
     * @param Session $session
     * @param string|\Ydb\Table\Query $yql
     */
    public function __construct(Session $session, $yql)
    {
        $this->session = $session;
        $this->yql($yql);
    }

    /**
     * @param string|\Ydb\Table\Query $yql
     * @return $this
     */
    public function yql($yql)
    {
        if (!is_a($yql, Query::class))
        {
            $yql = new Query([
                'yql_text' => $yql,
            ]);
        }
        $this->yql = $yql;
        return $this;
    }

    /**
     * Set whether to keep query in cache.
     *
     * @param bool $value
     * @return $this
     */
    public function keepInCache($value = true)
    {
        $this->keep_query_in_cache = (bool)$value;
        return $this;
    }

    /**
     * Set whether to collect stats.
     *
     * @param int $value
     * @return $this
     */
    public function collectStats($value = 1)
    {
        $this->collect_stats = $value;
        return $this;
    }

    /**
     * Set parameters.
     *
     * @param array|null $parameters
     * @return $this
     */
    public function parameters(array $parameters = null)
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set operation params.
     *
     * @param array|\Ydb\Operations\OperationParams $params
     * @return $this
     */
    public function operationParams($operation_params)
    {
        if (!is_a($operation_params, OperationParams::class))
        {
            $operation_params = new OperationParams($operation_params);
        }
        $this->operation_params = $operation_params;
        return $this;
    }

    /**
     * Set transaction control.
     *
     * @param \Ydb\Table\TransactionControl $tx_control
     * @return $this
     */
    public function txControl(TransactionControl $tx_control)
    {
        $this->tx_control = $tx_control;
        return $this;
    }

    /**
     * Begin a transaction with the given mode (stale, online, serializable).
     *
     * @param string $mode
     * @return $this
     */
    public function beginTx($mode)
    {
        $tx_settings = [];

        switch ($mode)
        {
            case 'stale':
            case 'stale_read_only':
                $tx_settings['stale_read_only'] = new StaleModeSettings;
                break;

            case 'online':
            case 'online_read_only':
                $tx_settings['online_read_only'] = new OnlineModeSettings([
                    'allow_inconsistent_reads' => false,
                ]);
                break;

            case 'inconsistent_reads':
            case 'online_inconsistent':
            case 'online_inconsistent_reads':
                $tx_settings['online_read_only'] = new OnlineModeSettings([
                    'allow_inconsistent_reads' => true,
                ]);
                break;

            case 'serializable':
            case 'serializable_read_write':
            default:
                $tx_settings['serializable_read_write'] = new SerializableModeSettings;
                break;
        }

        $this->tx_control = new TransactionControl([
            'begin_tx' => new TransactionSettings($tx_settings),
            'commit_tx' => true,
        ]);
        return $this;
    }

    /**
     * @return array
     */
    public function getRequestData()
    {
        $data = [];
        $data['query'] = $this->yql;
        $data['tx_control'] = $this->tx_control;
        $data['collect_stats'] = $this->collect_stats;
        if ($this->parameters)
        {
            $data['parameters'] = $this->parameters;
        }
        if ($this->operation_params)
        {
            $data['operation_params'] = $this->operation_params;
        }
        $data['query_cache_policy'] = new QueryCachePolicy([
            'keep_in_cache' => $this->keep_query_in_cache,
        ]);
        return $data;
    }

    /**
     * @return bool|QueryResult
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function execute()
    {
        return $this->session->executeQuery($this);
    }
}
