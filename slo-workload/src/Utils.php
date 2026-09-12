<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use Ydb\StatusIds\StatusCode;
use YdbPlatform\Ydb\Traits\RequestTrait;
use YdbPlatform\Ydb\Ydb;

class Utils
{
    public static function initDriver(Config $config, string $process): Ydb
    {
        $endpointData = explode("://", $config->endpoint);
        if (count($endpointData) != 2) {
            throw new Exception("Invalid endpoint exception");
        }
        $ydbConfig = [

            // Database path
            'database' => $config->database,

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
            $ydbConfig['iam_config']['root_cert_file'] = './ca.pem';
        }
        return new Ydb($ydbConfig, new SimpleSloLogger(SimpleSloLogger::INFO, $process));
    }

    /**
     * Maps an exception class name to a short error name for the `error_name` label.
     */
    public static function getErrorName(string $error): string
    {
        if ($ydbErr = array_search($error, RequestTrait::$ydbExceptions)) {
            return 'YDB_' . StatusCode::name($ydbErr);
        } elseif ($grpcErr = array_search($error, RequestTrait::$grpcExceptions)) {
            return 'GRPC_' . RequestTrait::$grpcNames[$grpcErr];
        } else {
            $shortName = strrchr($error, '\\');
            return $shortName === false ? $error : substr($shortName, 1);
        }
    }
}
