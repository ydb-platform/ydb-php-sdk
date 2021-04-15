<?php

namespace YandexCloud\Ydb\Traits;

use DateTime;
use Exception;

use Ydb\Type;
use Ydb\Value;
use Ydb\ListType as YdbListType;
use Ydb\TypedValue;
use Ydb\StructType;
use Ydb\StructMember;
use Ydb\Type\PrimitiveTypeId;

use YandexCloud\Ydb\Types\IntType;
use YandexCloud\Ydb\Types\BoolType;
use YandexCloud\Ydb\Types\DateType;
use YandexCloud\Ydb\Types\JsonType;
use YandexCloud\Ydb\Types\ListType;
use YandexCloud\Ydb\Types\UintType;
use YandexCloud\Ydb\Types\Utf8Type;
use YandexCloud\Ydb\Types\Int64Type;
use YandexCloud\Ydb\Types\FloatType;
use YandexCloud\Ydb\Types\DoubleType;
use YandexCloud\Ydb\Types\Uint64Type;
use YandexCloud\Ydb\Types\StringType;
use YandexCloud\Ydb\Types\DecimalType;
use YandexCloud\Ydb\Types\DatetimeType;
use YandexCloud\Ydb\Types\TimestampType;
use YandexCloud\Ydb\Contracts\TypeContract;

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
            return new DateType($value);
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
        $type = strtoupper($type);
        switch ($type)
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

        if (substr($type, 0, 4) === 'LIST')
        {
            return (new ListType($value))->itemType(trim(substr($type, 5, -1)));
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
                    $value = $this->typeValue($row[$column]);
                    $item[] = $value->toYdbValue();
                }
                else
                {
                    $item[] = new Value(['null_flag_value' => null]);
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
                        'struct_type' => new StructType([
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
                default:
                    return PrimitiveTypeId::PRIMITIVE_TYPE_ID_UNSPECIFIED;
            }
        }

        return $type;
    }

}