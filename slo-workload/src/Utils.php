<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use YdbPlatform\Ydb\Ydb;

class Utils
{
    public static function initDriver(string $endpoint, string $db)
    {
        $endpointData = explode("://", $endpoint);
        if (count($endpointData) != 2) throw new Exception("Invalid endpoint exception");
        $config = [

            // Database path
            'database' => $db,

            // Database endpoint
            'endpoint' => $endpointData[1],

            // Auto discovery (dedicated server only)
            'discovery' => true,

            // IAM config
            'iam_config' => [
                'insecure' => $endpointData[0] != "grpcs",
            ],
            "credentials" => new \YdbPlatform\Ydb\Auth\EnvironCredentials()
        ];
        if (file_exists("./ca.pem")) {
            $config['iam_config']['root_cert_file'] = './ca.pem';
        }
        return new Ydb($config, new \YdbPlatform\Ydb\Logger\SimpleStdLogger(6));
    }

    public static function initPush(string $endpoint, string $interval, string $time)
    {
        file_get_contents('http://127.0.0.1:88/prepare?' .
            http_build_query([
                "endpoint" => $endpoint,
                "label" => "php",
                "version" => Ydb::VERSION,
                "interval" => $interval,
                "time" => $time
            ]));
    }

    public static function metricInflight(string $job, int $process)
    {
        file_get_contents('http://127.0.0.1:88/start?' .
            http_build_query([
                "job" => $job,
                "process" => $process
            ]));
    }

    public static function metricDone(string $job, int $process, int $attemps)
    {
        file_get_contents('http://127.0.0.1:88/done?' .
            http_build_query([
                "job" => $job,
                "process" => $process,
                "attempts" => $attemps
            ]));
    }

    public static function metricFail(string $job, int $process, int $attemps)
    {
        file_get_contents('http://127.0.0.1:88/fail?' .
            http_build_query([
                "job" => $job,
                "process" => $process,
                "attempts" => $attemps
            ]));
    }
}
