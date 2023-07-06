<?php
namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\EnvironCredentials;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class TestEnvTypes extends TestCase{

    public function testEnvTypes(){

        $dataset = [
            [
                "env"   => [
                    "name"  => "YDB_SERVICE_ACCOUNT_KEY_FILE_CREDENTIALS",
                    "value" => "./some.json"
                ],
                "wait"  => "SA JSON key"
            ],
            [
                "env"   => [
                    "name"  => "YDB_ACCESS_TOKEN_CREDENTIALS",
                    "value" => "76254876234"
                ],
                "wait"  => "Access token"
            ],
            [
                "env"   => [
                    "name"  => "YDB_ANONYMOUS_CREDENTIALS",
                    "value" => "1"
                ],
                "wait"  => "Anonymous"
            ],
            [
                "env"   => [
                    "name"  => "YDB_METADATA_CREDENTIALS",
                    "value" => "1"
                ],
                "wait"  => "Metadata URL"
            ],
            [
                "env"   => [
                    "name"  => "YDB_ANONYMOUS_CREDENTIALS",
                    "value" => "0"
                ],
                "wait"  => "Metadata URL"
            ],
            [
                "env"   => [
                    "name"  => "none",
                    "value" => "none"
                ],
                "wait"  => "Metadata URL"
            ],
        ];
        foreach ($dataset as $data){
            putenv($data["env"]["name"]."=".$data["env"]["value"]);
            self::assertEquals(
                $data["wait"],
                (new EnvironCredentials())->getName()
            );
            putenv($data["env"]["name"]);
        }

    }
}
