<?php

namespace YdbPlatform\Ydb\Enums;

use UnexpectedValueException;
use Ydb\Table\ExecuteScanQueryRequest\Mode;

class ScanQueryMode
{
    const MODE_UNSPECIFIED = Mode::MODE_UNSPECIFIED;
    const MODE_EXPLAIN = Mode::MODE_EXPLAIN;
    const MODE_EXEC = Mode::MODE_EXEC;
    private static $valueToName = [
        self::MODE_UNSPECIFIED => 'MODE_UNSPECIFIED',
        self::MODE_EXPLAIN => 'MODE_EXPLAIN',
        self::MODE_EXEC => 'MODE_EXEC',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}
