<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;
use Ydb\StatusIds\StatusCode;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Traits\RequestTrait;
use YdbPlatform\Ydb\Types\DoubleType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\Utf8Type;
use YdbPlatform\Ydb\Ydb;

/**
 * Connecting to the database and the queries of the workload.
 */

/**
 * The connection must be opened in the process that uses it, see src/processes.php.
 *
 * @param string $process name of the process, it prefixes every log line
 * @throws Exception on a malformed endpoint
 */
function connect(Config $config, string $process): Ydb
{
    $endpoint = explode('://', $config->endpoint);
    if (count($endpoint) != 2) {
        throw new Exception('invalid endpoint: ' . $config->endpoint);
    }

    $ydbConfig = [
        'database' => $config->database,
        'endpoint' => $endpoint[1],
        'discovery' => true,
        'iam_config' => [
            'insecure' => $endpoint[0] != 'grpcs',
        ],
        'credentials' => new AnonymousAuthentication(),
    ];
    if (file_exists('./ca.pem')) {
        $ydbConfig['iam_config']['root_cert_file'] = './ca.pem';
    }

    return new Ydb($ydbConfig, new SimpleSloLogger(SimpleSloLogger::INFO, $process));
}

function createTableQuery(Config $config): string
{
    return "CREATE TABLE IF NOT EXISTS `$config->tableName`
(
    `hash` Uint64,
    `id` Uint64,
    `payload_double` Double,
    `payload_hash` Uint64,
    `payload_str` Utf8,
    `payload_timestamp` Timestamp,
    PRIMARY KEY (`hash`, `id`)
)
WITH(
    AUTO_PARTITIONING_MIN_PARTITIONS_COUNT = $config->minPartitionsCount,
    AUTO_PARTITIONING_MAX_PARTITIONS_COUNT = $config->maxPartitionsCount,
    AUTO_PARTITIONING_PARTITION_SIZE_MB = $config->partitionSize
);";
}

function writeQuery(string $tableName): string
{
    return "DECLARE \$id AS Uint64;
DECLARE \$payload_str AS Utf8;
DECLARE \$payload_double AS Double;
DECLARE \$payload_timestamp AS Timestamp;
UPSERT INTO `$tableName` (
  id, hash, payload_str, payload_double, payload_timestamp
) VALUES (
  \$id, Digest::NumericHash(\$id), \$payload_str, \$payload_double, \$payload_timestamp
);";
}

function readQuery(string $tableName): string
{
    return "DECLARE \$id AS Uint64;
SELECT id, payload_str, payload_double, payload_timestamp, payload_hash
FROM `$tableName` WHERE id = \$id AND hash = Digest::NumericHash(\$id);";
}

/**
 * Parameters of a row to write, see writeQuery().
 *
 * @return array query parameter name => typed value of the SDK
 */
function writeParams(int $id): array
{
    $payload = base64_encode(bin2hex(random_bytes((int)round(lcg_value() * 20 + 20))));

    return [
        '$id' => (new Uint64Type($id))->toTypedValue(),
        '$payload_str' => (new Utf8Type($payload))->toTypedValue(),
        '$payload_double' => (new DoubleType(lcg_value()))->toTypedValue(),
        '$payload_timestamp' => (new TimestampType(time()))->toTypedValue(),
    ];
}

/**
 * Parameters of a row to read, see readQuery().
 *
 * @return array query parameter name => typed value of the SDK
 */
function readParams(int $id): array
{
    return ['$id' => (new Uint64Type($id))->toTypedValue()];
}

/**
 * Maps an exception class name to a short error name for the `error_name` label.
 */
function errorName(string $exceptionClass): string
{
    if ($ydbError = array_search($exceptionClass, RequestTrait::$ydbExceptions)) {
        return 'YDB_' . StatusCode::name($ydbError);
    }

    if ($grpcError = array_search($exceptionClass, RequestTrait::$grpcExceptions)) {
        return 'GRPC_' . RequestTrait::$grpcNames[$grpcError];
    }

    $shortName = strrchr($exceptionClass, '\\');

    return $shortName === false ? $exceptionClass : substr($shortName, 1);
}
