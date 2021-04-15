<?php

namespace YandexCloud\Ydb\Types;

use DateTime;
use Exception;

class DatetimeType extends AbstractType
{
    /**
     * @var string
     */
    protected static $datetime_format = 'Y-m-d\TH:i:s\Z';

    /**
     * @inherit
     */
    protected $ydb_key_name = 'uint32_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'DATETIME';

    /**
     * @inherit
     * @throws Exception
     */
    protected function normalizeValue($value)
    {
        if (is_a($value, DateTime::class))
        {
            $value = $value->format(static::$datetime_format);
        }
        else if (is_int($value))
        {
            $value = date(static::$datetime_format, $value);
        }
        else if (is_string($value))
        {
            $value = new DateTime($value);
            $value = $value->format(static::$datetime_format);
        }
        else
        {
            throw new Exception('YDB Casting failed for datetime value');
        }

        return $value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Datetime(' . $this->quoteString($this->value) . ')';
    }

    /**
     * @inherit
     */
    protected function getYdbValue()
    {
        $value = new DateTime($this->value);
        return $value->getTimestamp();
    }
}
