<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use Ydb\StatusIds\StatusCode;
use YdbPlatform\Ydb\Traits\RequestTrait;
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
        return new Ydb($config, new SimpleSloLogger(6, $process));
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
        $e = substr(strrchr($error, '\\'), 1);
        if($ydbErr = array_search($error,RequestTrait::$ydbExceptions)){
            $e = 'YDB_'.StatusCode::name($ydbErr);
        } elseif ($grpcErr = array_search($error,RequestTrait::$grpcExceptions)){
            $e = 'GRPC_'.RequestTrait::$grpcNames[$grpcErr];
        } else {
            $e = substr(strrchr($error, '\\'), 1);
        }
        return static::postData('fail',
            http_build_query([
                "job" => $job,
                "process" => $process,
                "attempts" => $attemps,
                "error" => $e
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

        curl_exec($curl);

        return curl_getinfo($curl, CURLINFO_HTTP_CODE);
    }

    public static function reset()
    {
        self::postData("reset","");
    }

}
