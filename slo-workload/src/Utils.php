<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use Ydb\StatusIds\StatusCode;
use YdbPlatform\Ydb\Traits\RequestTrait;
use YdbPlatform\Ydb\Ydb;

class Utils
{
    const METRICS_MSG = 1;
    const AVAILABLE_READ_MSG = 2;
    const AVAILABLE_WRITE_MSG = 3;
    const MESSAGE_SIZE_LIMIT_BYTES = 1024;

    public static function initDriver(string $endpoint, string $db, string $process)
    {
        $endpointData = explode("://", $endpoint);
        if (count($endpointData) != 2){
            throw new Exception("Invalid endpoint exception");
        }
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
        return new Ydb($config, new SimpleSloLogger(SimpleSloLogger::INFO, $process));
    }


    public static function metricsStart(string $job, int $queueId)
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
        static::postData($queueId, [
            "type"  => "err",
            "job" => $job,
            "attempts" => $attemps,
            "error" => static::getErrorName($error),
            "latency" => $latency,
        ]);
    }

    public static function postData(int $queueId, array $data)
    {
        $data["sent"] = microtime(true);
        $msgQueue = msg_get_queue($queueId);
        msg_send($msgQueue, static::METRICS_MSG, $data);
    }

    public static function reset(int $queueId)
    {
        self::postData($queueId,[
            "type"  => "reset"
        ]);
    }

    public static function retriedError(int $queueId, string $job, string $error){
        self::postData($queueId,[
            "type"  => "retried",
            "job" => $job,
            "error" => self::getErrorName($error)
        ]);
    }

    protected static function getErrorName(string $error)
    {
        if($ydbErr = array_search($error,RequestTrait::$ydbExceptions)){
            return 'YDB_'.StatusCode::name($ydbErr);
        } elseif ($grpcErr = array_search($error,RequestTrait::$grpcExceptions)){
            return 'GRPC_'.RequestTrait::$grpcNames[$grpcErr];
        } else {
            return substr(strrchr($error, '\\'), 1);
        }
    }

}
