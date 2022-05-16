<?php

namespace YdbPlatform\Ydb\Traits;

use DateTime;
use Exception;

use Ydb\Type;
use Ydb\Value;
use Ydb\ListType as YdbListType;
use Ydb\TypedValue;
use Ydb\StructType as YdbStructType;
use Ydb\StructMember;
use Ydb\Table\KeyRange;
use Ydb\Type\PrimitiveTypeId;

use Google\Protobuf\NullValue;

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
use YdbPlatform\Ydb\Types\DatetimeType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Contracts\TypeContract;

trait TypeValueHelpersTrait
{
    /**
     * @param mixed $value
     * @param null $type
     * @return TypeContract
     * @throws Exception
     */
    public function typeValue($value, $type = null)
    {
        if (is_a($value, TypeContract::class))
        {
            return $value;
        }

        if ($type !== null)
        {
            return $this->valueOfType($value, $type);
        }

        if (is_int($value))
        {
            return (new IntType($value))
                ->unsigned($value > 0);
        }
        else if (is_float($value))
        {
            return new FloatType($value);
        }
        else if (is_bool($value))
        {
            return new BoolType($value);
        }
        else if (is_a($value, DateTime::class))
        {
            return new DatetimeType($value);
        }
        else if (is_string($value) && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $value))
        {
            return new DateType($value);
        }
        else if (is_string($value) && preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/', $value))
        {
            return new DatetimeType($value);
        }
        else if (is_array($value))
        {
            return new JsonType($value);
        }
        else if (is_object($value))
        {
            return new JsonType($value);
        }
        else
        {
            return new Utf8Type($value);
        }

        throw new Exception('YDB: Failed to cast value [' . $value . '].');
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return TypeContract
     * @throws Exception
     */
    public function valueOfType($value, $type)
    {
        $_type = strtoupper($type);
        switch ($_type)
        {
            case 'BOOL': return new BoolType($value);
            case 'TINYINT':
            case 'INT8': return new Int8Type($value);
            case 'TINYUINT':
            case 'UINT8': return new Uint8Type($value);
            case 'SMALLINT':
            case 'INT16': return new Int16Type($value);
            case 'SMALLUINT':
            case 'UINT16': return new Uint16Type($value);
            case 'INT':
            case 'INT32': return new IntType($value);
            case 'UINT':
            case 'UINT32': return new UintType($value);
            case 'BIGINT':
            case 'INT64': return new Int64Type($value);
            case 'BIGUINT':
            case 'UINT64': return new Uint64Type($value);
            case 'FLOAT': return new FloatType($value);
            case 'DOUBLE': return new DoubleType($value);
            case 'DATE': return new DateType($value);
            case 'DATETIME': return new DatetimeType($value);
            case 'TIMESTAMP': return new TimestampType($value);
            // case 'INTERVAL': return new StringValue(); // not implemented
            case 'TZ_DATE': return new DateType($value);
            case 'TZ_DATETIME': return new DatetimeType($value);
            case 'TZ_TIMESTAMP': return new TimestampType($value);
            case 'BLOB':
            case 'STRING': return new StringType($value);
            case 'TEXT':
            case 'UTF8': return new Utf8Type($value);
            // case 'YSON': return new StringValue(); // not implemented
            case 'JSON': return new JsonType($value);
            case 'UUID': return new StringType($value);
        }

        if (substr($_type, 0, 4) === 'LIST')
        {
            return (new ListType($value))->itemType(trim(substr($type, 5, -1)));
        }

        else if (substr($_type, 0, 6) === 'STRUCT')
        {
            return (new StructType($value))->itemTypes(trim(substr($type, 7, -1)));
        }

        else if (substr($_type, 0, 5) === 'TUPLE')
        {
            return (new TupleType($value))->itemTypes(trim(substr($type, 6, -1)));
        }

        throw new Exception('YDB: Unknown [' . $type . '] type.');
    }

    /**
     * @param array $rows
     * @param array $columns_types
     * @return TypedValue
     * @throws Exception
     */
    protected function convertBulkRows($rows, $columns_types = [])
    {
        if (is_a($rows, TypedValue::class))
        {
            return $rows;
        }

        $struct_members = [];

        if (!empty($columns_types))
        {
            foreach ($columns_types as $column => $type)
            {
                $struct_members[$column] = new StructMember([
                    'name' => $column,
                    'type' => new Type([
                        'type_id' => $this->convertType($type),
                    ]),
                ]);
            }
        }
        else
        {
            foreach ($rows as $index => $row)
            {
                foreach ($row as $column => $value)
                {
                    $value = $this->typeValue($value);
                    $row[$column] = $value;
                    if (!isset($struct_members[$column]))
                    {
                        $struct_members[$column] = new StructMember([
                            'name' => $column,
                            'type' => $value->toYdbType(),
                        ]);
                    }
                }
                $rows[$index] = $row;
            }
        }

        $data = [];

        foreach ($rows as $row)
        {
            $item = [];
            foreach (array_keys($struct_members) as $column)
            {
                if (isset($row[$column]))
                {
                    $value = $this->typeValue($row[$column], $columns_types[$column] ?? null);
                    $item[] = $value->toYdbValue();
                }
                else
                {
                    $item[] = new Value(['null_flag_value' => NullValue::NULL_VALUE]);
                }
            }
            $data[] = new Value([
                'items' => $item,
            ]);
        }

        $_rows = new TypedValue([
            'type' => new Type([
                'list_type' => new YdbListType([
                    'item' => new Type([
                        'struct_type' => new YdbStructType([
                            'members' => array_values($struct_members),
                        ]),
                    ])
                ])
            ]),
            'value' => new Value([
                'items' => $data,
            ]),
        ]);

        return $_rows;
    }

    /**
     * @param mixed $type
     * @return int|mixed
     */
    protected function convertType($type)
    {
        if (is_string($type))
        {
            switch (strtoupper($type))
            {
                case 'BOOL': return PrimitiveTypeId::BOOL;
                case 'INT8': return PrimitiveTypeId::INT8;
                case 'UINT8': return PrimitiveTypeId::UINT8;
                case 'INT16': return PrimitiveTypeId::INT16;
                case 'UINT16': return PrimitiveTypeId::UINT16;
                case 'INT':
                case 'INT32':
                    return PrimitiveTypeId::INT32;
                case 'UINT':
                case 'UINT32':
                    return PrimitiveTypeId::UINT32;
                case 'INT64': return PrimitiveTypeId::INT64;
                case 'UINT64': return PrimitiveTypeId::UINT64;
                case 'FLOAT': return PrimitiveTypeId::FLOAT;
                case 'DOUBLE': return PrimitiveTypeId::DOUBLE;
                case 'DATE': return PrimitiveTypeId::DATE;
                case 'DATETIME': return PrimitiveTypeId::DATETIME;
                case 'TIMESTAMP': return PrimitiveTypeId::TIMESTAMP;
                case 'INTERVAL': return PrimitiveTypeId::INTERVAL;
                case 'TZ_DATE': return PrimitiveTypeId::TZ_DATE;
                case 'TZ_DATETIME': return PrimitiveTypeId::TZ_DATETIME;
                case 'TZ_TIMESTAMP': return PrimitiveTypeId::TZ_TIMESTAMP;
                case 'BLOB':
                case 'STRING': return PrimitiveTypeId::STRING;
                case 'TEXT':
                case 'UTF8': return PrimitiveTypeId::UTF8;
                case 'YSON': return PrimitiveTypeId::YSON;
                case 'JSON': return PrimitiveTypeId::JSON;
                case 'UUID': return PrimitiveTypeId::UUID;
                case 'JSON_DOCUMENT': return PrimitiveTypeId::JSON_DOCUMENT;
                case 'DYNUMBER': return PrimitiveTypeId::DYNUMBER;

                case 'PRIMITIVE_TYPE_ID_UNSPECIFIED':
                    return PrimitiveTypeId::PRIMITIVE_TYPE_ID_UNSPECIFIED;

                default:
                    return null;
            }
        }

        return $type;
    }

    protected function convertKeyRange(array $ranges = [])
    {
        if (!is_a($ranges, KeyRange::class))
        {
            $key_range = [];
            foreach ($ranges as $key => $value)
            {
                if (!is_a($value, TypedValue::class))
                {
                    if (!is_a($value, TupleType::class))
                    {
                        throw new Exception('YDB: KeyRange must be instance of [' . TupleType::class . ']');
                    }
                    else if (is_a($value, TypeContract::class))
                    {
                        $value = $value->toTypedValue();
                    }
                    else
                    {
                        $value = $this->typeValue($value)->toTypedValue();
                    }
                }
                switch ($key)
                {
                    case '>':
                    case 'gt':
                    case 'greater':
                        $key_range['greater'] = $value;
                        break;

                    case '>=':
                    case 'gte':
                    case 'greater_or_equal':
                        $key_range['greater_or_equal'] = $value;
                        break;

                    case '<':
                    case 'lt':
                    case 'less':
                        $key_range['less'] = $value;
                        break;

                    case '<=':
                    case 'lte':
                    case 'less_or_equal':
                        $key_range['less_or_equal'] = $value;
                        break;
                }
            }
            if ($key_range)
            {
                return new KeyRange($key_range);
            }
        }
        return $ranges;
    }
}