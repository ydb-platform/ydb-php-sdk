<?php

namespace YdbPlatform\Ydb\Types;

use Ydb\Type;
use Ydb\Value;
use Ydb\TypedValue;

use Google\Protobuf\NullValue;

use YdbPlatform\Ydb\Contracts\TypeContract;
use YdbPlatform\Ydb\Traits\TypeValueHelpersTrait;

abstract class AbstractType implements TypeContract
{
    use TypeValueHelpersTrait;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var string
     */
    protected $ydb_key_name = 'bytes_value';

    /**
     * @var string
     */
    protected $ydb_type = 'STRING';

    /**
     * @param mixed|null $value
     */
    public function __construct($value = null)
    {
        if ($value !== null)
        {
            $this->setValue($value);
        }
    }

    /**
     * @param mixed|null $value
     * @return void
     */
    public function setValue($value = null)
    {
        $this->value = $value === null ? null : $this->normalizeValue($value);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toYqlString();
    }

    /**
     * @return string
     */
    public function toYqlString()
    {
        return $this->value === null ? 'NULL' : $this->getYqlString();
    }

    /**
     * @return Value
     */
    public function toYdbValue()
    {
        if ($this->value === null)
        {
            return new Value(['null_flag_value' => NullValue::NULL_VALUE]);
        }
        return new Value([$this->getYdbKeyName() => $this->getYdbValue()]);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->ydb_type;
    }

    /**
     * @return int|mixed
     */
    public function getYdbType()
    {
        return $this->convertType($this->ydb_type);
    }

    /**
     * @return Type
     */
    public function toYdbType()
    {
        return new Type([
            'type_id' => $this->getYdbType(),
        ]);
    }

    /**
     * @return TypedValue
     */
    public function toTypedValue()
    {
        return new TypedValue([
            'type' => $this->toYdbType(),
            'value' => $this->toYdbValue(),
        ]);
    }

    /**
     * @return string
     */
    protected function getYdbKeyName()
    {
        return $this->ydb_key_name;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeValue($value)
    {
        return $value;
    }

    /**
     * @return string
     */
    protected function getYqlString()
    {
        return $this->quoteString($this->value);
    }

    /**
     * @return mixed
     */
    protected function getYdbValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return string
     */
    protected function quoteString($value)
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\"'], $value) . '"';
    }
}
