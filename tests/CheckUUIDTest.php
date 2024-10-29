<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Types\BoolType;
use YdbPlatform\Ydb\Types\DatetimeType;
use YdbPlatform\Ydb\Types\DateType;
use YdbPlatform\Ydb\Types\DoubleType;
use YdbPlatform\Ydb\Types\FloatType;
use YdbPlatform\Ydb\Types\Int16Type;
use YdbPlatform\Ydb\Types\Int32Type;
use YdbPlatform\Ydb\Types\Int64Type;
use YdbPlatform\Ydb\Types\Int8Type;
use YdbPlatform\Ydb\Types\JsonType;
use YdbPlatform\Ydb\Types\StringType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Types\Uint16Type;
use YdbPlatform\Ydb\Types\Uint32Type;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\Uint8Type;
use YdbPlatform\Ydb\Types\Utf8Type;
use YdbPlatform\Ydb\Ydb;

class CheckUUIDTest extends TestCase
{
    public function testCheckTypesInDeclare()
    {
        $config = [

// Database path
            'database' => '/local',

// Database endpoint
            'endpoint' => 'localhost:2136',

// Auto discovery (dedicated server only)
            'discovery' => false,

// IAM config
            'iam_config' => [
                'insecure' => true,
            ],

            'credentials' => new AnonymousAuthentication()
        ];

        $ydb = new Ydb($config);
        $table = $ydb->table();
        $session = $table->createSession();

        $query = "DECLARE \$v as Utf8; SELECT CAST(\$v AS UUID) as val;";
        $prepared = $session->prepare($query);
        $result = $prepared->execute([
            'v' => "6E73B41C-4EDE-4D08-9CFB-B7462D9E498B",
        ]);
        self::assertEquals("6E73B41C-4EDE-4D08-9CFB-B7462D9E498B", $result->rows()[0]["val"]);
    }
}
