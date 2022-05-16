<?php

namespace YdbPlatform\Ydb\Traits;

use Ydb\Type;
use Ydb\OptionalType;
use Ydb\Table\ColumnMeta;
use Ydb\Table\TableIndex;
use Ydb\Table\GlobalIndex;

use YdbPlatform\Ydb\Types\IntType;
use YdbPlatform\Ydb\Types\BoolType;
use YdbPlatform\Ydb\Types\DateType;
use YdbPlatform\Ydb\Types\JsonType;
use YdbPlatform\Ydb\Types\ListType;
use YdbPlatform\Ydb\Types\UintType;
use YdbPlatform\Ydb\Types\Utf8Type;
use YdbPlatform\Ydb\Types\Int64Type;
use YdbPlatform\Ydb\Types\FloatType;
use YdbPlatform\Ydb\Types\TupleType;
use YdbPlatform\Ydb\Types\DoubleType;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\StringType;
use YdbPlatform\Ydb\Types\StructType;
use YdbPlatform\Ydb\Types\DecimalType;
use YdbPlatform\Ydb\Types\AbstractType;
use YdbPlatform\Ydb\Types\DatetimeType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Contracts\TypeContract;

trait TypeHelpersTrait
{
    use TypeValueHelpersTrait;

    /**
     * @param string $name
     * @param string $type
     * @param array $options
     * @return ColumnMeta
     */
    public function column($name, $type, $options = [])
    {
        return new ColumnMeta([
            'name' => $name,
            'type' => new Type([
                'type_id' => $this->convertType($type),
                'optional_type' => new OptionalType([
                    'item' => new Type([
                        'type_id' => $this->convertType($type),
                    ]),
                ]),
            ]),
        ]);
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function intColumn($name)
    {
        return $this->column($name, 'INT32');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function uintColumn($name)
    {
        return $this->column($name, 'UINT32');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function unsignedIntColumn($name)
    {
        return $this->column($name, 'UINT32');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function int64Column($name)
    {
        return $this->column($name, 'INT64');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function uint64Column($name)
    {
        return $this->column($name, 'UINT64');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function bigIntColumn($name)
    {
        return $this->column($name, 'INT64');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function bigUintColumn($name)
    {
        return $this->column($name, 'UINT64');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function unsignedBigIntColumn($name)
    {
        return $this->column($name, 'UINT64');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function textColumn($name)
    {
        return $this->column($name, 'UTF8');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function utf8Column($name)
    {
        return $this->column($name, 'UTF8');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function blobColumn($name)
    {
        return $this->column($name, 'STRING');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function stringColumn($name)
    {
        return $this->column($name, 'STRING');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function dateColumn($name)
    {
        return $this->column($name, 'DATE');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function datetimeColumn($name)
    {
        return $this->column($name, 'DATETIME');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function timestampColumn($name)
    {
        return $this->column($name, 'TIMESTAMP');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function boolColumn($name)
    {
        return $this->column($name, 'BOOL');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function jsonColumn($name)
    {
        return $this->column($name, 'JSON');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function floatColumn($name)
    {
        return $this->column($name, 'FLOAT');
    }

    /**
     * @param string $name
     * @return ColumnMeta
     */
    public function doubleColumn($name)
    {
        return $this->column($name, 'DOUBLE');
    }

    /**
     * @param string $name
     * @param array $columns
     * @param false $globalIndex
     * @return TableIndex
     */
    public function tableIndex($name, array $columns, $globalIndex = false)
    {
        $data = [
            'name' => $name,
            'index_columns' => (array)$columns,
        ];
        if ($globalIndex)
        {
            $data['global_index'] = new GlobalIndex();
        }
        return new TableIndex($data);
    }

    /**
     * @param int $value
     * @param bool $unsigned
     * @param int $bits
     * @return TypeContract
     */
    public function int($value = null, $unsigned = false, $bits = 32)
    {
        return (new IntType($value))
            ->unsigned($unsigned)
            ->bits($bits);
    }

    /**
     * @param int $value
     * @param int $bits
     * @return TypeContract
     */
    public function uint($value = null, $bits = 32)
    {
        return (new UintType($value))
            ->bits($bits);
    }

    /**
     * @param int $value
     * @param bool $unsigned
     * @return TypeContract
     */
    public function bigint($value = null, $unsigned = false)
    {
        return (new Int64Type($value))
            ->unsigned($unsigned);
    }

    /**
     * @param int $value
     * @return TypeContract
     */
    public function biguint($value = null)
    {
        return new Uint64Type($value);
    }

    /**
     * @param int $value
     * @param bool $unsigned
     * @return TypeContract
     */
    public function int64($value = null, $unsigned = false)
    {
        return (new Int64Type($value))
            ->unsigned($unsigned);
    }

    /**
     * @param int $value
     * @return TypeContract
     */
    public function uint64($value = null)
    {
        return new Uint64Type($value);
    }

    /**
     * @param bool $value
     * @return TypeContract
     */
    public function bool($value = null)
    {
        return new BoolType($value);
    }

    /**
     * @param string $value
     * @return TypeContract
     */
    public function string($value = null)
    {
        return new StringType($value);
    }

    /**
     * @param string $value
     * @return TypeContract
     */
    public function blob($value = null)
    {
        return new StringType($value);
    }

    /**
     * @param string $value
     * @return TypeContract
     */
    public function utf8($value = null)
    {
        return new Utf8Type($value);
    }

    /**
     * @param string $value
     * @return TypeContract
     */
    public function text($value = null)
    {
        return new Utf8Type($value);
    }

    /**
     * @param mixed $value
     * @return TypeContract
     */
    public function timestamp($value = null)
    {
        return new TimestampType($value);
    }

    /**
     * @param mixed $value
     * @return TypeContract
     */
    public function datetime($value = null)
    {
        return new DatetimeType($value);
    }

    /**
     * @param mixed $value
     * @return TypeContract
     */
    public function date($value = null)
    {
        return new DateType($value);
    }

    /**
     * @param mixed $value
     * @return TypeContract
     */
    public function json($value = null)
    {
        return new JsonType($value);
    }

    /**
     * @param float $value
     * @return TypeContract
     */
    public function float($value = null)
    {
        return new FloatType($value);
    }

    /**
     * @param float $value
     * @return TypeContract
     */
    public function double($value = null)
    {
        return new DoubleType($value);
    }

    /**
     * @param float $value
     * @param int $digits
     * @param int $scale
     * @return TypeContract
     */
    public function decimal($value = null, $digits = 10, $scale = 2)
    {
        return (new DecimalType($value))
            ->digits($digits)
            ->scale($scale);
    }

    /**
     * @param mixed $type
     * @param mixed $value
     * @return TypeContract
     */
    public function list($type, $value = null)
    {
        return (new ListType($value))
            ->itemType($type);
    }

    /**
     * @param mixed $types
     * @param mixed $value
     * @return TypeContract
     */
    public function struct($types, $value = null)
    {
        return (new StructType($value))
            ->itemTypes($types);
    }

    /**
     * @param mixed $types
     * @param array $value
     * @return TypeContract
     */
    public function tuple($types, $value = [])
    {
        return (new TupleType($value))
            ->itemTypes($types);
    }

}