<?php

namespace YdbPlatform\Ydb\Types;

use DateTime;
use Exception;

class DateType extends AbstractType
{
    /**
     * @var string
     */
    protected static $date_format = 'Y-m-d';

    /**
     * @inherit
     */
    protected $ydb_key_name = 'uint32_value';

    /**
     * @inherit
     */
    protected $ydb_type = 'DATE';

    /**
     * @inherit
     * @throws Exception
     */
    protected function normalizeValue($value)
    {
        if (is_a($value, DateTime::class))
        {
            $value = $value->format(static::$date_format);
        }
        else if (is_int($value))
        {
            $value = date(static::$date_format, $value);
        }
        else if (is_string($value))
        {
            $value = new DateTime($value);
            $value = $value->format(static::$date_format);
        }
        else
        {
            throw new Exception('YDB Casting failed for date value');
        }

        return $value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Date(' . $this->quoteString($this->value) . ')';
    }

    /**
     * @inherit
     */
    protected function getYdbValue()
    {
        $value = new DateTime($this->value);
        return $value->getTimestamp() / 86400;
    }
}
