<?php

namespace YdbPlatform\Ydb\Types;

use DateTime;
use Exception;

class TimestampType extends DatetimeType
{

    protected $ydb_key_name = "uint64_value";

    protected $ydb_type = "TIMESTAMP";

    protected static $datetime_format = 'Y-m-d\TH:i:s.u\Z';
    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Timestamp(' . $this->quoteString($this->value) . ')';
    }

    /**
     * @inherit
     */
    protected function getYdbValue()
    {
        $value = new DateTime($this->value);
        $x = ($value->format("U")."000000");
        $y = $value->format("u");
        return $x+$y;
    }
}
