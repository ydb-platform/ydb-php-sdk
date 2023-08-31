<?php
namespace YdbPlatform\Ydb\Enums;
use UnexpectedValueException;
use Ydb\Table\QueryStatsCollection\Mode;

class CollectQueryStatsMode
{

    const STATS_COLLECTION_UNSPECIFIED = Mode::STATS_COLLECTION_UNSPECIFIED;

    const STATS_COLLECTION_NONE = Mode::STATS_COLLECTION_NONE;

    const STATS_COLLECTION_BASIC = Mode::STATS_COLLECTION_BASIC;

    const STATS_COLLECTION_FULL = Mode::STATS_COLLECTION_FULL;

    const STATS_COLLECTION_PROFILE = Mode::STATS_COLLECTION_PROFILE;

    private static $valueToName = [
        self::STATS_COLLECTION_UNSPECIFIED => 'STATS_COLLECTION_UNSPECIFIED',
        self::STATS_COLLECTION_NONE => 'STATS_COLLECTION_NONE',
        self::STATS_COLLECTION_BASIC => 'STATS_COLLECTION_BASIC',
        self::STATS_COLLECTION_FULL => 'STATS_COLLECTION_FULL',
        self::STATS_COLLECTION_PROFILE => 'STATS_COLLECTION_PROFILE',
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
