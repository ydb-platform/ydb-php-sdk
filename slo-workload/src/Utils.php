<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use YdbPlatform\Ydb\Ydb;

class Utils
{
    public static function initDriver(string $endpoint, string $db, string $process)
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
            "credentials" => new \YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication()
        ];
        if (file_exists("./ca.pem")) {
            $config['iam_config']['root_cert_file'] = './ca.pem';
        }
        @mkdir("./logs");
        return new Ydb($config, new src\SimpleFileLogger(6, "./logs/" . $process . ".log"));
    }

    public static function initPush(string $endpoint, string $interval, string $time)
    {
        return static::postData('prepare',
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
        return static::postData('start',
            http_build_query([
                "job" => $job,
                "process" => $process
            ]));
    }

    public static function metricDone(string $job, int $process, int $attemps)
    {
        return static::postData('done',
            http_build_query([
                "job" => $job,
                "process" => $process,
                "attempts" => $attemps
            ]));
    }

    public static function metricFail(string $job, int $process, int $attemps, string $error)
    {
        return static::postData('fail',
            http_build_query([
                "job" => $job,
                "process" => $process,
                "attempts" => $attemps,
                "error" => $error
            ]));
    }

    public static function postData(string $path, string $data)
    {
        $curl = curl_init("http://localhost:88/$path?$data");

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 0,
        ]);

        return curl_getinfo($curl, CURLINFO_HTTP_CODE);
    }

}
