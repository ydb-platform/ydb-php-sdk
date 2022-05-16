<?php

namespace YdbPlatform\Ydb;

use Ydb\Table\ColumnMeta;
use Ydb\Table\TableIndex;
use Ydb\Table\StoragePool;
use Ydb\Table\ColumnFamily;
use Ydb\Table\StorageSettings;
use Ydb\Table\PartitioningSettings;
use Ydb\Table\ReadReplicasSettings;

class YdbTable
{
    use Traits\TypeHelpersTrait;
    use Traits\TableHelpersTrait;

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var array
     */
    protected $primary_keys = [];

    /**
     * @var array
     */
    protected $indexes = [];

    /**
     * @var array
     */
    protected $storage_settings = [];

    /**
     * @var array
     */
    protected $column_families = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var string
     */
    protected $compaction_policy = 'default';

    /**
     * @var array
     */
    protected $partition_settings = [];

    protected $uniform_partitions;
    protected $key_bloom_filter;

    /**
     * @var array
     */
    protected $read_replicas_settings = [];

    /**
     * @return static
     */
    public static function make()
    {
        return new static;
    }

    /**
     * @param $name
     * @param $type
     * @return $this
     */
    public function addColumn($name, $type)
    {
        $this->columns[] = $this->column($name, $type);
        return $this;
    }

    public function addColumns(array $columns = [])
    {
        foreach ($columns as $name => $type)
        {
            $this->columns[] = is_a($type, ColumnMeta::class) ? $type : $this->column($name, $type);
        }
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function primaryKey($columns)
    {
        foreach ((array)$columns as $column)
        {
            $this->primary_keys[] = $column;
        }
        return $this;
    }

    public function pk($columns)
    {
        return $this->primaryKey($columns);
    }

    public function getPrimaryKey()
    {
        return $this->primary_keys;
    }

    public function addIndex($name, $columns, $globalIndex = false)
    {
        $this->indexes[] = $this->tableIndex($name, (array)$columns, $globalIndex);
        return $this;
    }

    public function addIndexes(array $indexes)
    {
        foreach ((array)$indexes as $name => $columns)
        {
            $this->indexes[] = is_a($columns, TableIndex::class) ? $columns : $this->tableIndex($name, (array)$columns);
        }        
        return $this;
    }

    public function getIndexes()
    {
        return $this->indexes;
    }

    public function storageSettings(array $storageSettings = null)
    {
        $data = [];
        if (isset($storageSettings['tablet_commit_log0']))
        {
            $data['tablet_commit_log0'] = new StoragePool([
                'media' => $storageSettings['tablet_commit_log0'],
            ]);
        }
        if (isset($storageSettings['tablet_commit_log1']))
        {
            $data['tablet_commit_log1'] = new StoragePool([
                'media' => $storageSettings['tablet_commit_log1'],
            ]);
        }
        if (isset($storageSettings['external']))
        {
            $data['external'] = new StoragePool([
                'media' => $storageSettings['external'],
            ]);
        }
        if (isset($storageSettings['store_external_blobs']))
        {
            $data['store_external_blobs'] = (int)$storageSettings['store_external_blobs'];
        }
        $this->storage_settings = new StorageSettings($data);
        return $this;
    }

    public function getStorageSettings()
    {
        return $this->storage_settings;
    }

    public function columnFamilies(array $columnFamilies)
    {
        foreach ($columnFamilies as $columnFamily)
        {
            $data = [];
            if (isset($columnFamily['name']))
            {
                $data['name'] = $columnFamily['name'];
            }
            if (isset($columnFamily['data']))
            {
                $data['data'] = new StoragePool([
                    'media' => $columnFamily['data'],
                ]);
            }
            if (isset($columnFamily['compression']))
            {
                $data['compression'] = (int)$columnFamily['compression'];
            }
            if (isset($columnFamily['keep_in_memory']))
            {
                $data['keep_in_memory'] = (int)$columnFamily['keep_in_memory'];
            }
            $this->column_families[] = new ColumnFamily($data);
        }
        return $this;
    }

    public function getColumnFamilies()
    {
        return $this->column_families;
    }

    public function attributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function addAttribute($attribute)
    {
        $this->attributes[] = $attribute;
        return $this;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function compactionPolicy($compactionPolicy)
    {
        $this->compaction_policy = (string)$compactionPolicy;
        return $this;
    }

    public function getCompactionPolicy()
    {
        return $this->compaction_policy;
    }

    public function partitionSettings(array $partitionSettings)
    {
        $data = [];
        if (isset($partitionSettings['partitioning_by_size']))
        {
            $data['partitioning_by_size'] = (int)$partitionSettings['partitioning_by_size'];
        }
        if (isset($partitionSettings['partition_size_mb']))
        {
            $data['partition_size_mb'] = (int)$partitionSettings['partition_size_mb'];
        }
        if (isset($partitionSettings['partitioning_by_load']))
        {
            $data['partitioning_by_load'] = (int)$partitionSettings['partitioning_by_load'];
        }
        if (isset($partitionSettings['min_partitions_count']))
        {
            $data['min_partitions_count'] = (int)$partitionSettings['min_partitions_count'];
        }
        if (isset($partitionSettings['max_partitions_count']))
        {
            $data['max_partitions_count'] = (int)$partitionSettings['max_partitions_count'];
        }
        $this->partition_settings = new PartitioningSettings($data);
        return $this;
    }

    public function getPartitionSettings()
    {
        return $this->partition_settings;
    }

    public function uniformPartitions($uniformPartitions)
    {
        $this->uniform_partitions = (int)$uniformPartitions;
        return $this;
    }

    public function getUniformPartitions()
    {
        return $this->uniform_partitions;
    }

    public function keyBloomFilter($keyBloomFilter)
    {
        $this->key_bloom_filter = (int)$keyBloomFilter;
        return $this;
    }

    public function getKeyBloomFilter()
    {
        return $this->key_bloom_filter;
    }

    public function readReplicasSettings(array $readReplicasSettings)
    {
        $data = [];
        if (isset($readReplicasSettings['per_az_read_replicas_count']))
        {
            $data['per_az_read_replicas_count'] = (int)$readReplicasSettings['per_az_read_replicas_count'];
        }
        if (isset($readReplicasSettings['any_az_read_replicas_count']))
        {
            $data['any_az_read_replicas_count'] = (int)$readReplicasSettings['any_az_read_replicas_count'];
        }
        $this->read_replicas_settings = new ReadReplicasSettings($data);
        return $this;
    }

    public function getReadReplicasSettings()
    {
        return $this->read_replicas_settings;
    }

}