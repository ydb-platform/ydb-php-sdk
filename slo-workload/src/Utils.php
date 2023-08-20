<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use Ydb\StatusIds\StatusCode;
use YdbPlatform\Ydb\Traits\RequestTrait;
use YdbPlatform\Ydb\Ydb;

class Utils
{
    const MSG_TYPE = 1;
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
        return new Ydb($config, new SimpleSloLogger(5, $process));
    }


    public static function metricInflight(string $job, int $queueId)
    {
        static::postData($queueId,[
            "type"  => "start",
            "job"   => $job
        ]);
    }

    public static function metricDone(string $job, int $queueId, int $attemps, float $latency)
    {
        static::postData($queueId, [
            "type"  => "ok",
            "job" => $job,
            "attempts" => $attemps,
            "latency" => $latency,
        ]);
    }

    public static function metricFail(string $job, int $queueId, int $attemps, string $error, float $latency)
    {
        if($ydbErr = array_search($error,RequestTrait::$ydbExceptions)){
            $e = 'YDB_'.StatusCode::name($ydbErr);
        } elseif ($grpcErr = array_search($error,RequestTrait::$grpcExceptions)){
            $e = 'GRPC_'.RequestTrait::$grpcNames[$grpcErr];
        } else {
            $e = substr(strrchr($error, '\\'), 1);
        }
        static::postData($queueId, [
            "type"  => "err",
            "job" => $job,
            "attempts" => $attemps,
            "error" => $e,
            "latency" => $latency,
        ]);
    }

    public static function postData(int $queueId, array $data)
    {
        $msgQueue = msg_get_queue($queueId);
        msg_send($msgQueue, static::MSG_TYPE, $data);
    }

    public static function reset(int $queueId)
    {
        self::postData("reset",[
            "type"  => "reset"
        ]);
    }

}
