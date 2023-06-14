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
use YdbPlatform\Ydb\Types\Uint16Type;
use YdbPlatform\Ydb\Types\Uint32Type;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\Uint8Type;
use YdbPlatform\Ydb\Types\Utf8Type;
use YdbPlatform\Ydb\Ydb;
use function PHPUnit\Framework\assertEquals;

class CheckTypeTest  extends TestCase{
    public function testAnonymousConnection(){
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            // IAM config
            'iam_config'  => [
                'insecure' => true,
            ],

            'credentials' => new AnonymousAuthentication("y0_AgAAAAAnEIuGAATuwQAAAADlZVUrMkWLKkQ6TIW_JUmzUTCpU39u3cQ")
//            'credentials' => new OAuthTokenAuthentication("y0_AgAAAAAnEIuGAATuwQAAAADlZVUrMkWLKkQ6TIW_JUmzUTCpU39u3cQ")
        ];

        $checkTypes = [
            "Bool"  => [
                "class" => BoolType::class,
                "values" => [
                    true, false
                ]
            ],
            "Int8"  => [
                "class" => Int8Type::class,
                "values" => [
                    -1*pow(2,7), 0, pow(2,7)-1
                ]
            ],
            "Uint8"  => [
                "class" => Uint8Type::class,
                "values" => [
                    0, pow(2,8)-1
                ]
            ],
            "Int16"  => [
                "class" => Int16Type::class,
                "values" => [
                    -1*pow(2,15), 0, pow(2,15)-1
                ]
            ],
            "Uint16"  => [
                "class" => Uint16Type::class,
                "values" => [
                    0, pow(2,16)-1
                ]
            ],
            "Int32"  => [
                "class" => Int32Type::class,
                "values" => [
                    -1*pow(2,31), 0, pow(2,31)-1
                ]
            ],
            "Uint32"  => [
                "class" => Uint32Type::class,
                "values" => [
                    0, pow(2,32)-1
                ]
            ],
            "Int64"  => [
                "class" => Int64Type::class,
                "values" => [
                    -1*pow(2,63), 0, 0x7FFFFFFFFFFFFFFF // 2^63 -1
                ]
            ],
            "Uint64"  => [
                "class" => Uint64Type::class,
                "values" => [
                    0, 1<<64 -1 // 2^64 - 1
                ]
            ],
            "Float"  => [
                "class" => FloatType::class,
                "values" => [
                    345.34534
                ]
            ],
            "Double"  => [
                "class" => DoubleType::class,
                "values" => [
                    -345.3453453745
                ]
            ],
            "String" => [
                "class" => StringType::class,
                "values" => [
                    random_bytes(5)
                ]
            ],
            "Utf8"  => [
                "class"     => Utf8Type::class,
                "values"    => [
                    "", "YDB"
                ]
            ],
            "Json"  => [
                "class"     => JsonType::class,
                "values"    => [
                    [], (object)[
                        "num" => 1
                    ]
                ]
            ],
            "Date"  => [
                "class"     => DateType::class,
                "values"    => [
                    "2023-06-14"
                ]
            ],
            "Datetime"  => [
                "class"     => DatetimeType::class,
                "values"    => [
                    "2023-06-14 17:12:15"
                ]
            ],
//            "Timestamp" => [
//                "class"     => TimestampType::class,
//                "values"    => [
//                    new TimestampType(43578634985)
//                ]
//            ]
        ];

        $ydb = new Ydb($config);
        $table = $ydb->table();
        $session = $table->createSession();

        foreach ($checkTypes as $type=>$data) {
            $query = "DECLARE \$v as $type; SELECT \$v as val;";
            print ($query)."\n";
            $prepared = $session->prepare($query);
            foreach ($data["values"] as $value) {
                $result = $prepared->execute([
                    'v' => $value,
                ]);
                assertEquals($value,$result->rows()[0]["val"]);
                assertEquals(strtoupper($type),strtoupper($result->columns()[0]["type"]));
            }
        }

    }
}
