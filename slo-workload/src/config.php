<?php

/**
 * Workload settings: defaults, then the environment (the action passes everything
 * essential through it, see README.MD), then the command line options on top.
 *
 * The settings are a plain array: every key can be set with the option of the same
 * name, with underscores replaced by dashes (`read_rps` <- `-read-rps`).
 */
function slo_config(array $args)
{
    list($endpoint, $database) = slo_connection();

    $config = [
        'endpoint' => $endpoint,                            // e.g. grpc://ydb:2136
        'database' => $database,                            // e.g. /Root/testdb
        'ref' => slo_env('WORKLOAD_REF', 'current'),        // `current` or `baseline`
        'workload_name' => slo_env('WORKLOAD_NAME', 'php-table'),
        'otlp_endpoint' => slo_otlp_endpoint(),             // empty: metrics are not pushed
        'table_name' => '',                                 // empty: <workload_name>/<ref>

        'duration' => (int)slo_env('WORKLOAD_DURATION', 600), // seconds, the whole run
        'shutdown_time' => 30,                              // seconds reserved for the cleanup
        'report_period' => 1000,                            // metrics push period, milliseconds

        'min_partitions_count' => 6,
        'max_partitions_count' => 1000,
        'partition_size' => 1,                              // mb
        'prefill_count' => 1000,                            // rows written before the workload

        'read_rps' => 1000,                                 // upper bound for all read processes
        'read_timeout' => 10000,                            // milliseconds
        'read_forks' => 3,
        'write_rps' => 100,
        'write_timeout' => 10000,
        'write_forks' => 1,
    ];

    foreach (slo_options($args) as $name => $value) {
        if (!array_key_exists($name, $config)) {
            throw new Exception('unknown option: ' . str_replace('_', '-', $name));
        }
        $config[$name] = is_int($config[$name]) ? (int)$value : $value;
    }

    if ($config['duration'] <= 0) {
        throw new Exception('workload duration must be > 0');
    }
    if ($config['table_name'] === '') {
        // Current and baseline workloads share the database, so every ref gets its own table.
        $config['table_name'] = $config['workload_name'] . '/' . $config['ref'];
    }

    // The action waits `duration + 60s` for the container to exit, so the whole lifecycle
    // has to fit into the duration: the workload stops early enough to leave the shutdown
    // time for dropping the table, and a worker stuck in an operation is killed even earlier.
    $config['start_time'] = microtime(true);
    $config['run_deadline'] = $config['start_time'] + $config['duration'] - $config['shutdown_time'];
    $config['kill_deadline'] = $config['run_deadline'] + $config['shutdown_time'] * 2 / 3;

    return $config;
}

/**
 * Parses `-read-rps 100`, `--read-rps 100` and `--read-rps=100` into
 * `['read_rps' => '100']`.
 */
function slo_options(array $args)
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

        $options[str_replace('-', '_', $name)] = $value;
    }

    return $options;
}

/**
 * @return array endpoint and database path, taken from the environment
 */
function slo_connection()
{
    $endpoint = slo_env('YDB_ENDPOINT', '');
    $database = slo_env('YDB_DATABASE', '');

    $connectionString = slo_env('YDB_CONNECTION_STRING', '');
    if ($connectionString !== '') {
        list($endpoint, $databaseFromString) = slo_split_connection_string($connectionString);
        if ($database === '') {
            $database = $databaseFromString;
        }
    }

    if ($endpoint === '' || $database === '') {
        throw new Exception('YDB_CONNECTION_STRING or YDB_ENDPOINT with YDB_DATABASE is required');
    }

    return [$endpoint, $database];
}

/**
 * Splits `grpc://ydb:2136/Root/testdb` (or `grpc://ydb:2136?database=/Root/testdb`)
 * into `grpc://ydb:2136` and `/Root/testdb`.
 *
 * @return array endpoint and database path, the path is empty when there is none
 */
function slo_split_connection_string($connectionString)
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
        return [$url, $database];
    }

    return [substr($url, 0, $slash), $database !== '' ? $database : substr($url, $slash)];
}

function slo_otlp_endpoint()
{
    $endpoint = slo_env('OTEL_EXPORTER_OTLP_METRICS_ENDPOINT', '');
    if ($endpoint !== '') {
        return $endpoint;
    }

    $endpoint = slo_env('OTEL_EXPORTER_OTLP_ENDPOINT', '');
    if ($endpoint === '') {
        return '';
    }

    // The base endpoint is `.../api/v1/otlp`, metrics are pushed to `.../v1/metrics`.
    return rtrim($endpoint, '/') . '/v1/metrics';
}

function slo_env($name, $default)
{
    $value = getenv($name);

    return ($value === false || $value === '') ? $default : $value;
}
