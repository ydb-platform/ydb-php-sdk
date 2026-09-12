<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;

/**
 * Workload configuration.
 *
 * The workload is started by `ydb-platform/ydb-slo-action`, which passes everything
 * essential through the environment (see the workload contract in README.MD) and
 * allows to override the tuning knobs with CLI flags.
 */
class Config
{
    /** @var string YDB endpoint, e.g. `grpc://ydb:2136` */
    public $endpoint;
    /** @var string database path, e.g. `/Root/testdb` */
    public $database;
    /** @var string value of the `ref` label in metrics: `current` or `baseline` */
    public $ref;
    /** @var string workload name, used as a part of the table path */
    public $workloadName;
    /** @var int workload duration in seconds */
    public $duration;
    /** @var string|null OTLP metrics endpoint, metrics are disabled when empty */
    public $otlpEndpoint;

    /** @var string table path relative to the database */
    public $tableName;
    /** @var int */
    public $minPartitionsCount;
    /** @var int */
    public $maxPartitionsCount;
    /** @var int */
    public $partitionSize;
    /** @var int amount of rows written before the workload starts */
    public $prefillCount;

    /** @var int */
    public $readRps;
    /** @var int milliseconds */
    public $readTimeout;
    /** @var int */
    public $readForks;
    /** @var int */
    public $writeRps;
    /** @var int milliseconds */
    public $writeTimeout;
    /** @var int */
    public $writeForks;

    /** @var int metrics push period in milliseconds */
    public $reportPeriod;
    /** @var int seconds reserved for the graceful shutdown and the cleanup */
    public $shutdownTime;
    /** @var float unix time the workload has been started at */
    public $startTime;

    /**
     * The action waits `WORKLOAD_DURATION + 60s` for the container to exit, so the
     * whole lifecycle has to fit into the duration: the workload stops early enough
     * to leave the shutdown time for dropping the table.
     */
    public function runDeadline(): float
    {
        return $this->startTime + $this->duration - $this->shutdownTime;
    }

    /**
     * Deadline for the workers to finish the operation they are in the middle of.
     */
    public function hardDeadline(): float
    {
        return $this->runDeadline() + $this->shutdownTime / 3;
    }

    /**
     * @param array $options parsed CLI options, see Config::parseOptions()
     * @throws Exception
     */
    public static function fromEnv(array $options = []): Config
    {
        $config = new Config();
        $config->startTime = microtime(true);

        list($endpoint, $database) = self::connectionFromEnv();
        $config->endpoint = $options['endpoint'] ?? $endpoint;
        $config->database = $options['database'] ?? $database;

        $config->ref = self::env('WORKLOAD_REF', 'current');
        $config->workloadName = self::env('WORKLOAD_NAME', 'php-table');
        $config->duration = (int)($options['time'] ?? self::env('WORKLOAD_DURATION', Defaults::DURATION));
        if ($config->duration <= 0) {
            throw new Exception('workload duration must be > 0');
        }

        $config->otlpEndpoint = $options['otlp-endpoint'] ?? self::otlpEndpointFromEnv();

        // Current and baseline workloads share the database, so every ref gets its own table.
        $config->tableName = $options['table-name'] ?? $config->workloadName . '/' . $config->ref;

        $config->minPartitionsCount = (int)($options['min-partitions-count'] ?? Defaults::TABLE_MIN_PARTITION_COUNT);
        $config->maxPartitionsCount = (int)($options['max-partitions-count'] ?? Defaults::TABLE_MAX_PARTITION_COUNT);
        $config->partitionSize = (int)($options['partition-size'] ?? Defaults::TABLE_PARTITION_SIZE);
        $config->prefillCount = (int)($options['prefill-count'] ?? Defaults::PREFILL_COUNT);

        $config->readRps = (int)($options['read-rps'] ?? Defaults::READ_RPS);
        $config->readTimeout = (int)($options['read-timeout'] ?? Defaults::READ_TIMEOUT);
        $config->readForks = (int)($options['read-forks'] ?? Defaults::READ_FORKS);
        $config->writeRps = (int)($options['write-rps'] ?? Defaults::WRITE_RPS);
        $config->writeTimeout = (int)($options['write-timeout'] ?? Defaults::WRITE_TIMEOUT);
        $config->writeForks = (int)($options['write-forks'] ?? Defaults::WRITE_FORKS);

        $config->reportPeriod = (int)($options['report-period'] ?? Defaults::REPORT_PERIOD);
        $config->shutdownTime = (int)($options['shutdown-time'] ?? Defaults::SHUTDOWN_TIME);

        return $config;
    }

    /**
     * @return array{0: string, 1: string} endpoint and database path
     * @throws Exception
     */
    protected static function connectionFromEnv(): array
    {
        $connectionString = self::env('YDB_CONNECTION_STRING');
        if ($connectionString) {
            return self::splitConnectionString($connectionString);
        }

        $endpoint = self::env('YDB_ENDPOINT');
        $database = self::env('YDB_DATABASE');
        if (!$endpoint || !$database) {
            throw new Exception('YDB_CONNECTION_STRING or YDB_ENDPOINT with YDB_DATABASE is required');
        }

        return [$endpoint, $database];
    }

    /**
     * Splits `grpc://host:2136/Root/testdb` into `grpc://host:2136` and `/Root/testdb`.
     * The database path may also be passed as a `database` query parameter.
     *
     * @return array{0: string, 1: string}
     * @throws Exception
     */
    public static function splitConnectionString(string $connectionString): array
    {
        $parts = explode('?', $connectionString, 2);
        $withoutQuery = $parts[0];

        $database = self::env('YDB_DATABASE');
        if (!$database && isset($parts[1])) {
            parse_str($parts[1], $query);
            $database = $query['database'] ?? null;
        }

        $schemeParts = explode('://', $withoutQuery, 2);
        if (count($schemeParts) != 2) {
            throw new Exception('invalid connection string: ' . $connectionString);
        }

        $slash = strpos($schemeParts[1], '/');
        if ($slash === false) {
            $endpoint = $withoutQuery;
        } else {
            $endpoint = $schemeParts[0] . '://' . substr($schemeParts[1], 0, $slash);
            $database = $database ?: substr($schemeParts[1], $slash);
        }

        if (!$database) {
            throw new Exception('database path is missing in ' . $connectionString . ', set YDB_DATABASE');
        }

        return [$endpoint, $database];
    }

    protected static function otlpEndpointFromEnv()
    {
        $endpoint = self::env('OTEL_EXPORTER_OTLP_METRICS_ENDPOINT');
        if ($endpoint) {
            return $endpoint;
        }

        $endpoint = self::env('OTEL_EXPORTER_OTLP_ENDPOINT');
        if (!$endpoint) {
            return null;
        }

        // The base endpoint is `.../api/v1/otlp`, metrics are pushed to `.../v1/metrics`.
        return rtrim($endpoint, '/') . '/v1/metrics';
    }

    /**
     * Parses `-read-rps 100` / `--read-rps 100` / `--read-rps=100` CLI options.
     */
    public static function parseOptions(array $args): array
    {
        $options = [];
        $positional = [];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (substr($arg, 0, 1) != '-') {
                $positional[] = $arg;
                continue;
            }

            $arg = ltrim($arg, '-');
            if (strpos($arg, '=') !== false) {
                list($name, $value) = explode('=', $arg, 2);
                $options[self::normalizeOption($name)] = $value;
                continue;
            }

            $value = $args[$i + 1] ?? null;
            if ($value === null || substr($value, 0, 1) == '-') {
                // Flags without a value are not used by the workload.
                continue;
            }
            $options[self::normalizeOption($arg)] = $value;
            $i++;
        }

        // Endpoint and database may also be passed positionally, as in the old workload.
        if (isset($positional[0])) {
            $options['endpoint'] = $positional[0];
        }
        if (isset($positional[1])) {
            $options['database'] = $positional[1];
        }

        return $options;
    }

    protected static function normalizeOption(string $name): string
    {
        $aliases = [
            't' => 'table-name',
            'c' => 'prefill-count',
            'initial-data-count' => 'prefill-count',
        ];

        return $aliases[$name] ?? $name;
    }

    protected static function env(string $name, $default = null)
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}
