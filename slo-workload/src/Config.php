<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;

/**
 * Workload settings: the defaults below, then the environment (the action passes
 * everything essential through it, see README.MD), then the command line options.
 */
class Config
{
    /** Command line option => the property it sets. */
    const OPTIONS = [
        'endpoint' => 'endpoint',
        'database' => 'database',
        'table-name' => 'tableName',
        'otlp-endpoint' => 'otlpEndpoint',

        'duration' => 'duration',
        'shutdown-time' => 'shutdownTime',
        'report-period' => 'reportPeriod',

        'min-partitions-count' => 'minPartitionsCount',
        'max-partitions-count' => 'maxPartitionsCount',
        'partition-size' => 'partitionSize',
        'prefill-count' => 'prefillCount',

        'read-rps' => 'readRps',
        'read-timeout' => 'readTimeout',
        'read-forks' => 'readForks',
        'write-rps' => 'writeRps',
        'write-timeout' => 'writeTimeout',
        'write-forks' => 'writeForks',
    ];

    /** @var string endpoint, e.g. `grpc://ydb:2136` */
    public $endpoint = '';
    /** @var string database path, e.g. `/Root/testdb` */
    public $database = '';
    /** @var string value of the `ref` label in metrics: `current` or `baseline` */
    public $ref = 'current';
    /** @var string workload name, used as a part of the table path */
    public $workloadName = 'php-table';
    /** @var string table path relative to the database, empty: `<workloadName>/<ref>` */
    public $tableName = '';
    /** @var string OTLP metrics endpoint, empty: metrics are not pushed */
    public $otlpEndpoint = '';

    /** @var int duration of the whole run in seconds */
    public $duration = 600;
    /** @var int seconds reserved for stopping the workers and dropping the table */
    public $shutdownTime = 30;
    /** @var int metrics push period in milliseconds */
    public $reportPeriod = 1000;

    /** @var int minimum amount of partitions in the table */
    public $minPartitionsCount = 6;
    /** @var int maximum amount of partitions in the table */
    public $maxPartitionsCount = 1000;
    /** @var int partition size in mb */
    public $partitionSize = 1;
    /** @var int amount of rows written before the workload starts */
    public $prefillCount = 1000;

    /** @var int read RPS, an upper bound for all read processes */
    public $readRps = 1000;
    /** @var int read timeout in milliseconds */
    public $readTimeout = 10000;
    /** @var int amount of read processes */
    public $readForks = 3;
    /** @var int write RPS, an upper bound for all write processes */
    public $writeRps = 100;
    /** @var int write timeout in milliseconds */
    public $writeTimeout = 10000;
    /** @var int amount of write processes */
    public $writeForks = 1;

    /** @var float unix time the workload has been started at */
    public $startTime = 0.0;
    /** @var float no operation is started after this point, the workers stop */
    public $runDeadline = 0.0;
    /** @var float a worker still running at this point is killed */
    public $killDeadline = 0.0;

    /**
     * @param string[] $args command line arguments without the command itself
     * @throws Exception on a malformed environment or an unknown option
     */
    public static function fromEnv(array $args): Config
    {
        $config = new Config();

        $connection = self::connectionFromEnv();
        $config->endpoint = $connection->endpoint;
        $config->database = $connection->database;
        $config->ref = self::env('WORKLOAD_REF', $config->ref);
        $config->workloadName = self::env('WORKLOAD_NAME', $config->workloadName);
        $config->duration = (int)self::env('WORKLOAD_DURATION', (string)$config->duration);
        $config->otlpEndpoint = self::otlpEndpointFromEnv();

        foreach (self::parseOptions($args) as $name => $value) {
            if (!isset(self::OPTIONS[$name])) {
                throw new Exception('unknown option: -' . $name);
            }

            $property = self::OPTIONS[$name];
            $config->$property = is_int($config->$property) ? (int)$value : $value;
        }

        if ($config->duration <= 0) {
            throw new Exception('workload duration must be > 0');
        }
        if ($config->tableName === '') {
            // Current and baseline workloads share the database, every ref gets its own table.
            $config->tableName = $config->workloadName . '/' . $config->ref;
        }

        // The action waits `duration + 60s` for the container to exit, so the whole
        // lifecycle has to fit into the duration: the workload stops early enough to
        // leave the shutdown time for dropping the table, and a worker stuck in an
        // operation is killed even earlier.
        $config->startTime = microtime(true);
        $config->runDeadline = $config->startTime + $config->duration - $config->shutdownTime;
        $config->killDeadline = $config->runDeadline + $config->shutdownTime * 2 / 3;

        return $config;
    }

    /**
     * Parses `-read-rps 100`, `--read-rps 100` and `--read-rps=100`.
     *
     * @param string[] $args
     * @return string[] option name without the dashes => value
     */
    public static function parseOptions(array $args): array
    {
        $options = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = ltrim($args[$i], '-');

            if (strpos($arg, '=') !== false) {
                list($name, $value) = explode('=', $arg, 2);
            } else {
                $name = $arg;
                $value = isset($args[$i + 1]) ? $args[$i + 1] : '';
                $i++;
            }

            $options[$name] = $value;
        }

        return $options;
    }

    /**
     * Splits `grpc://ydb:2136/Root/testdb` (or `grpc://ydb:2136?database=/Root/testdb`)
     * into the endpoint and the database path. The path is empty when there is none.
     *
     * @throws Exception on a string without a scheme
     */
    public static function splitConnectionString(string $connectionString): Connection
    {
        $database = '';

        $parts = explode('?', $connectionString, 2);
        if (isset($parts[1])) {
            parse_str($parts[1], $query);
            if (isset($query['database'])) {
                $database = $query['database'];
            }
        }

        $url = $parts[0];
        $schemeEnd = strpos($url, '://');
        if ($schemeEnd === false) {
            throw new Exception('invalid connection string: ' . $connectionString);
        }

        $slash = strpos($url, '/', $schemeEnd + 3);
        if ($slash === false) {
            return new Connection($url, $database);
        }

        return new Connection(
            substr($url, 0, $slash),
            $database !== '' ? $database : substr($url, $slash)
        );
    }

    /**
     * @throws Exception when the environment describes no database to connect to
     */
    protected static function connectionFromEnv(): Connection
    {
        $endpoint = self::env('YDB_ENDPOINT', '');
        $database = self::env('YDB_DATABASE', '');

        $connectionString = self::env('YDB_CONNECTION_STRING', '');
        if ($connectionString !== '') {
            $connection = self::splitConnectionString($connectionString);
            $endpoint = $connection->endpoint;
            if ($database === '') {
                $database = $connection->database;
            }
        }

        if ($endpoint === '' || $database === '') {
            throw new Exception('YDB_CONNECTION_STRING or YDB_ENDPOINT with YDB_DATABASE is required');
        }

        return new Connection($endpoint, $database);
    }

    protected static function otlpEndpointFromEnv(): string
    {
        $endpoint = self::env('OTEL_EXPORTER_OTLP_METRICS_ENDPOINT', '');
        if ($endpoint !== '') {
            return $endpoint;
        }

        $endpoint = self::env('OTEL_EXPORTER_OTLP_ENDPOINT', '');
        if ($endpoint === '') {
            return '';
        }

        // The base endpoint is `.../api/v1/otlp`, metrics are pushed to `.../v1/metrics`.
        return rtrim($endpoint, '/') . '/v1/metrics';
    }

    protected static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return ($value === false || $value === '') ? $default : $value;
    }
}
