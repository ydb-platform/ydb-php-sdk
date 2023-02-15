<?php

namespace YdbPlatform\Ydb;

class QueryResult
{
    protected $columns = [];
    protected $rows = [];
    protected $truncated = false;

    public function __construct($result)
    {
        if (method_exists($result, 'getResultSets'))
        {
            $sets = $result->getResultSets();

            if ($sets && count($sets))
            {
                $data = json_decode($sets->offsetGet(0)->serializeToJsonString(), true);

                $this->fillColumns($data['columns'] ?? []);
                $this->fillRows($data['rows'] ?? []);
                $this->truncated = $data['truncated'] ?? false;
            }
        }
        else if (method_exists($result, 'getResultSet'))
        {
            $set = $result->getResultSet();

            if ($set)
            {
                $data = json_decode($set->serializeToJsonString(), true);

                $this->fillColumns($data['columns']);
                $this->fillRows($data['rows']);
                $this->truncated = $data['truncated'] ?? false;
            }
        }
        else
        {
            throw new Exception('Unknown result');
        }
    }

    public function isTruncated()
    {
        return $this->truncated;
    }

    public function columnCount()
    {
        return count($this->columns);
    }

    public function rowCount()
    {
        return count($this->rows);
    }

    public function columns()
    {
        return $this->columns;
    }

    public function rows()
    {
        return $this->rows;
    }

    public function value($column = null)
    {
        $rows = $this->rows();
        $value = null;

        if (isset($rows[0]))
        {
            if ($column !== null)
            {
                if (is_int($column))
                {
                    $values = array_values($rows[0]);
                    $value = $values[$column] ?? null;
                }
                else
                {
                    $value = $rows[0][$column] ?? null;
                }
            }
            else
            {
                $values = array_values($rows[0]);
                $value = $values[0] ?? null;
            }
        }

        return $value;
    }

    public function pluck($column, $key = null)
    {
        $rows = $this->rows();
        $values = [];

        foreach ($rows as $row)
        {
            if ($key !== null)
            {
                if (isset($row[$key]))
                {
                    $values[$row[$key]] = $row[$column] ?? null;
                }
                else
                {
                    $values[] = $row[$column] ?? null;
                }
            }
            else
            {
                $values[] = $row[$column] ?? null;
            }
        }

        return $values;
    }

    protected function fillColumns($columns)
    {
        $this->columns = [];

        foreach ($columns as $column)
        {
            $name = $column['name'];
            $type = null;
            $options = null;

            if (isset($column['type']['optionalType']))
            {
                $type = $column['type']['optionalType']['item']['typeId'];
            }
            else if (isset($column['type']['structType']))
            {
                $type = 'STRUCT';
                $options = [];
                foreach ($column['type']['structType']['members'] as $member)
                {
                    $options[] = $member;
                }
            }
            else if (isset($column['type']['typeId']))
            {
                $type = $column['type']['typeId'];
            }

            $this->columns[] = [
                'name' => $name,
                'type' => $type,
                'options' => $options,
            ];
        }
    }

    protected function fillRows($rows)
    {
        $this->rows = [];

        foreach ($rows as $row)
        {
            $_row = [];

            foreach ($row['items'] as $i => $item)
            {
                $values = array_values($item);
                $value = $values[0];
                $column = $this->columns[$i];
                if ($value === null)
                {
                    $_row[$column['name']] = null;
                    continue;
                }
                switch ($column['type'])
                {
                    case 'STRING':
                        $_row[$column['name']] = base64_decode($value);
                        break;

                    case 'UUID':
                        $_row[$column['name']] = dechex($value);
                        break;

                    case 'JSON':
                        $_row[$column['name']] = json_decode($value);
                        break;

                    case 'TIMESTAMP':
                        $_row[$column['name']] = is_numeric($value) ? date('Y-m-d H:i:s', $value / 1000000) : $value;
                        break;

                    case 'DATETIME':
                        $_row[$column['name']] = is_int($value) ? date('Y-m-d H:i:s', $value) : $value;
                        break;

                    case 'DATE':
                        $_row[$column['name']] = is_int($value) ? date('Y-m-d', $value * 86400) : $value;
                        break;

                    case 'INT32':
                    case 'INT64':
                    case 'UINT32':
                        $_row[$column['name']] = (int)($value);
                        break;

                    case 'UINT64':
                        $value_int = (int)$value;
                        if ($value_int === PHP_INT_MAX && PHP_INT_SIZE === 8) {
                            $value_int = (int)bcsub($value, '18446744073709551616', 0);
                        }
                        $_row[$column['name']] = $value_int;
                        break;

                    default:
                        $_row[$column['name']] = $value;
                }
            }

            $this->rows[] = $_row;
        }
    }
}
