<?php

namespace YdbPlatform\Ydb;

use Closure;
use Exception;
use Ydb\Table\Query;
use Ydb\Table\QueryCachePolicy;
// use Ydb\Table\StaleModeSettings;
// use Ydb\Table\OnlineModeSettings;
use Ydb\Table\TransactionControl;
use Ydb\Table\TransactionSettings;
use Ydb\Table\SerializableModeSettings;

class Session
{
    use Traits\RequestTrait;
    use Traits\ParseResultTrait;
    use Traits\TypeHelpersTrait;
    use Traits\TableHelpersTrait;
    use Traits\LoggerTrait;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var \Ydb\Table\V1\TableServiceClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $meta;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $session_id;

    /**
     * @var string
     */
    protected $tx_id;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $is_busy = false;

    /**
     * @var bool
     */
    protected $is_alive = false;

    /**
     * @var bool
     */
    protected $keep_query_in_cache = null;

    /**
     * @param Table $table
     * @param string $session_id
     */
    public function __construct(Table $table, $session_id)
    {
        $this->ydb = $table->ydb();

        $this->table = $table;

        $this->session_id = $session_id;

        $this->client = $table->client();
        $this->meta = $table->meta();
        $this->credentials = $table->credentials();
        $this->path = $table->path();
        $this->logger = $table->getLogger();

        $this->is_alive = true;
    }

    /**
     * @return string
     */
    public function id()
    {
        return $this->session_id;
    }

    /**
     * @return bool
     */
    public function isAlive()
    {
        return $this->is_alive;
    }

    /**
     * @return bool
     */
    public function isBusy()
    {
        return $this->is_busy;
    }

    /**
     * @return bool
     */
    public function isIdle()
    {
        return !$this->is_busy;
    }

    /**
     * @return $this
     */
    public function take()
    {
        $this->is_busy = true;
        $this->table->sessionTaken($this);
        return $this;
    }

    /**
     * @return $this
     */
    public function release()
    {
        $this->is_busy = false;
        $this->table->sessionReleased($this);
        return $this;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function delete()
    {
        if ($this->is_alive)
        {
            $this->request('DeleteSession', ['session_id' => $this->session_id]);

            $this->is_alive = false;

            $this->table->dropSession($this->session_id);
        }
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function refresh()
    {
        $old_session_id = $this->session_id;

        $result = $this->request('CreateSession');
        $session_id = $result->getSessionId();
        $this->logger()->info('YDB: New session created [...' . substr($session_id, -6) . '].');

        $this->session_id = $session_id;

        $this->table->syncSession($old_session_id);

        return $this;
    }

    /**
     * @return array|mixed|null
     * @throws Exception
     */
    public function keepAlive()
    {
        $result = $this->request('KeepAlive', ['session_id' => $this->session_id]);
        return $this->parseResult($result, 'sessionStatus');
    }

    /**
     * @param Closure $closure
     * @return mixed
     * @throws Exception
     */
    public function transaction(Closure $closure)
    {
        $this->beginTransaction();
        try
        {
            $result = $closure($this);
            $this->commitTransaction();
            return $result;
        }
        catch (Exception $e)
        {
            try {
                $this->rollbackTransaction();
            } catch (Exception $e2) {
            }
            throw $e;
        }
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function beginTransaction()
    {
        $serializable_read_write = new SerializableModeSettings;
        // $online_read_only = new OnlineModeSettings;
        // $stale_read_only = new StaleModeSettings;

        $transaction_settings = new TransactionSettings([
            'serializable_read_write' => $serializable_read_write,
        ]);

        $result = $this->request('BeginTransaction', [
            'session_id' => $this->session_id,
            'tx_settings' => $transaction_settings,
        ]);

        if ($result && method_exists($result, 'getTxMeta'))
        {
            $this->tx_id = $result->getTxMeta()->getId();

            return $this->tx_id;
        }
        else
        {
            $this->logger->debug('YDB failed to begin transaction',[
                "result"    => $result,
                "method_exists" => method_exists($result, 'getTxMeta')
            ]);
            $this->logger->error('YDB failed to begin transaction');
            throw new Exception('YDB failed to begin transaction');
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function commitTransaction()
    {
        if ($tx_id = $this->tx_id)
        {
            $this->request('CommitTransaction', [
                'session_id' => $this->session_id,
                'tx_id' => $tx_id,
            ]);
        }

        $this->tx_id = null;

        return true;
    }

    /**
     * An alias to commitTransaction.
     *
     * @return bool
     * @throws Exception
     */
    public function commit()
    {
        return $this->commitTransaction();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function rollbackTransaction()
    {
        if ($tx_id = $this->tx_id)
        {
            $this->request('RollbackTransaction', [
                'session_id' => $this->session_id,
                'tx_id' => $tx_id,
            ]);
        }

        $this->tx_id = null;

        return true;
    }

    /**
     * An alias to rollbackTransaction.
     *
     * @return bool
     * @throws Exception
     */
    public function rollBack()
    {
        return $this->rollbackTransaction();
    }

    /**
     * Set whether to keep query in cache.
     *
     * @return $this
     */
    public function keepInCache($value = true)
    {
        $this->keep_query_in_cache = (bool)$value;
        return $this;
    }

    /**
     * @param string|\Ydb\Table\Query $yql
     * @return YdbQuery
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function newQuery($yql)
    {
        return new YdbQuery($this, $yql);
    }

    /**
     * @param YdbQuery $query
     * @return bool|QueryResult
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function executeQuery(YdbQuery $query)
    {
        $data = $query->getRequestData();
        $data['session_id'] = $this->session_id;

        $result = $this->request('ExecuteDataQuery', $data);

        return $result ? new QueryResult($result) : true;
    }

    /**
     * @param string|\Ydb\Table\Query $yql
     * @param array|null $parameters
     * @return bool|QueryResult
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function query($yql, array $parameters = null)
    {
        $tx_id = $this->tx_id;

        if (!$tx_id)
        {
            $tx_id = $this->beginTransaction();
        }

        $tx_control = new TransactionControl([
            'tx_id' => $tx_id,
        ]);

        $query = $this->newQuery($yql)
            ->parameters($parameters)
            ->txControl($tx_control)
            ->keepInCache($this->keep_query_in_cache ?? ($parameters&&count($parameters)>0));

        return $this->executeQuery($query);
    }

    /**
     * An alias to query with no result.
     *
     * @param string|\Ydb\Table\Query $yql
     * @param array|null $parameters
     * @return bool
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function exec($yql, array $parameters = null)
    {
        $this->query($yql, $parameters);

        return true;
    }

    /**
     * @param string $yql
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function schemeQuery($yql)
    {
        return $this->request('ExecuteSchemeQuery', [
            'session_id' => $this->session_id,
            'yql_text' => $yql,
        ]);
    }

    /**
     * @param string $yql
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function explainQuery($yql)
    {
        $result = $this->request('ExplainDataQuery', [
            'session_id' => $this->session_id,
            'yql_text' => $yql,
        ]);

        return $result;
    }

    /**
     * @param string $yql
     * @return Statement
     * @throws Exception
     */
    public function prepare($yql)
    {
        $statement = new Statement($this, $yql);

        if ($statement->isCached())
        {
            return $statement;
        }

        $result = $this->request('PrepareDataQuery', [
            'session_id' => $this->session_id,
            'yql_text' => $yql,
        ]);

        $statement->saveInCache();

        return $statement;
    }

    /**
     * @param string $path
     * @param array $columns
     * @param array $options
     * @return \Generator
     */
    public function readTable($path, $columns = [], $options = [])
    {
        $params = [
            'session_id' => $this->session_id,
            'path' => $this->pathPrefix($path),
            'columns' => $columns,
        ];

        if (isset($options['row_limit']))
        {
            $params['row_limit'] = (int)$options['row_limit'];
        }

        if (isset($options['ordered']))
        {
            $params['ordered'] = (bool)$options['ordered'];
        }

        if (isset($options['key_range']))
        {
            if ($key_range = $this->convertKeyRange($options['key_range']))
            {
                $params['key_range'] = $key_range;
            }
        }

        return $this->streamRequest('StreamReadTable', $params);
    }

    /**
     * @param string $table
     * @param mixed $columns
     * @param string|array $primary_key
     * @param array $indexes
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function createTable($table, $columns, $primary_key = 'id', $indexes = [])
    {
        $data = [
            'path' => $this->pathPrefix($table),
            'session_id' => $this->session_id,
        ];

        if (is_a($columns, YdbTable::class))
        {
            $data['columns'] = $columns->getColumns();
            $data['primary_key'] = $columns->getPrimaryKey();
            $data['indexes'] = $columns->getIndexes();
            $data['storage_settings'] = $columns->getStorageSettings();
            $data['column_families'] = $columns->getColumnFamilies();
            $data['attributes'] = $columns->getAttributes();
            $data['compaction_policy'] = $columns->getCompactionPolicy();
            $data['partitioning_settings'] = $columns->getPartitionSettings();
            $data['uniform_partitions'] = $columns->getUniformPartitions();
            $data['key_bloom_filter'] = $columns->getKeyBloomFilter();
            $data['read_replicas_settings'] = $columns->getReadReplicasSettings();

            $data = array_filter($data);
        }
        else
        {
            $data['columns'] = $this->convertColumns($columns);
            $data['primary_key'] = (array)$primary_key;
            $data['indexes'] = $this->convertIndexes($indexes);
        }

        return $this->request('CreateTable', $data);
    }

    /**
     * @param string $source_table
     * @param string $destination_table
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function copyTable($source_table, $destination_table)
    {
        return $this->request('CopyTable', [
            'source_path' => $this->pathPrefix($source_table),
            'destination_path' => $this->pathPrefix($destination_table),
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * @param array $tables
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function copyTables($tables)
    {
        return $this->request('CopyTables', [
            'tables' => $this->convertTableItems($tables),
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * @param string $table
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function dropTable($table)
    {
        return $this->request('DropTable', [
            'path' => $this->pathPrefix($table),
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * @param string $table
     * @param array $columns
     * @param array $indexes
     * @return bool|mixed|void|null
     * @throws Exception
     */
    public function alterTable($table, $columns = [], $indexes = [])
    {
        return $this->request('AlterTable', [
            'path' => $this->pathPrefix($table),
            'add_columns' => $this->convertColumns($columns['add'] ?? []),
            'drop_columns' => $columns['drop'] ?? [],
            'alter_columns' => $this->convertColumns($columns['alter'] ?? []),
            'add_indexes' => $this->convertIndexes($indexes['add'] ?? []),
            'drop_indexes' => $indexes['drop'] ?? [],
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * @param string $table
     * @return array|mixed|null
     * @throws Exception
     */
    public function describeTable($table)
    {
        $result = $this->request('DescribeTable', [
            'session_id' => $this->session_id,
            'path' => $this->pathPrefix($table),
            'include_table_stats' => true,
        ]);

        return $this->parseResult($result);
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function request($method, array $data = [])
    {
        $this->take();

        try
        {
            $result = $this->doRequest('Table', $method, $data);
        }
        catch (Exception $e)
        {
            $this->release();
            throw $e;
        }

        $this->release();

        return $result;
    }

    /**
     * @param string $method
     * @param array $data
     * @return \Generator
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function streamRequest($method, array $data = [])
    {
        return $this->doStreamRequest('Table', $method, $data);
    }
}
