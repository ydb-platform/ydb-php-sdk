<?php

namespace YdbPlatform\Ydb\Slo;

use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Types\DoubleType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\Utf8Type;

class DataGenerator
{
    static $currentObjectId = 0;

    static function setMaxId($startId)
    {
        self::$currentObjectId = $startId;
    }

    static function getMaxId()
    {
        return self::$currentObjectId;
    }

    static function getRandomId()
    {
        return round(lcg_value() * self::getMaxId());
    }

    static function loadMaxId(\YdbPlatform\Ydb\Ydb $ydb, string $tableName)
    {
        return $ydb->table()->retrySession(function (Session $session) use ($tableName) {
            $res = $session->query("SELECT MAX(id) as max_id FROM `$tableName`");
            self::$currentObjectId = $res->rows()[0]["max_id"];
            return $res->rows()[0]["max_id"];
        });
    }

    static function getUpsertData()
    {
        self::$currentObjectId++;
        return [
            "\$id" => new Uint64Type(self::$currentObjectId),
            "\$payload_str" => new Utf8Type(base64_encode(bin2hex(random_bytes(round(lcg_value() * 20 + 20))))),
            "\$payload_double" => new DoubleType(lcg_value()),
            "\$payload_timestamp" => new TimestampType(time())
        ];
    }

    public static function loadMaxIdTable(Table $table, string $tableName)
    {
        return $table->retrySession(function (Session $session) use ($tableName) {
            $res = $session->query("SELECT MAX(id) as max_id FROM `$tableName`");
            self::$currentObjectId = $res->rows()[0]["max_id"];
            return $res->rows()[0]["max_id"];
        });
    }
}