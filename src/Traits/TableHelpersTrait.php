<?php

namespace YdbPlatform\Ydb\Traits;

use Ydb\Table\ColumnMeta;
use Ydb\Table\TableIndex;
use Ydb\Table\CopyTableItem;

trait TableHelpersTrait
{
    /**
     * Prefix the table name.
     *
     * @param string $table
     * @return mixed|string
     */
    protected function pathPrefix($table)
    {
        if (substr($table, 0, 1) !== '/')
        {
            $table = rtrim($this->path . '/' . $table, '/');
        }
        return $table;
    }

    /**
     * @param array $columns
     * @return array
     */
    protected function convertColumns(array $columns)
    {
        $_columns = [];

        foreach ($columns as $name => $column)
        {
            if (!is_a($column, ColumnMeta::class))
            {
                $column = $this->column($name, $column);
            }
            $_columns[] = $column;
        }

        return $_columns;
    }

    /**
     * @param array $indexes
     * @return array
     */
    protected function convertIndexes(array $indexes)
    {
        $_indexes = [];

        foreach ($indexes as $name => $index)
        {
            if (!is_a($index, TableIndex::class))
            {
                $index = $this->tableIndex($name, (array)$index);
            }
            $_indexes[] = $index;
        }

        return $_indexes;
    }

    /**
     * @param array $tables
     * @return array
     */
    protected function convertTableItems(array $tables)
    {
        $_tables = [];

        foreach ($tables as $source_table => $destination_table)
        {
            $_table = $destination_table;
            if (!is_a($destination_table, CopyTableItem::class))
            {
                $_table = new CopyTableItem([
                    'source_path' => $this->pathPrefix($source_table),
                    'destination_path' => $this->pathPrefix($destination_table),
                ]);
            }
            $_tables[] = $_table;
        }

        return $_tables;
    }
}