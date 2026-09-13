<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/processes.php';
require_once __DIR__ . '/src/ydb.php';
require_once __DIR__ . '/src/workload.php';

$usage = "Usage: php application.php [create|run|cleanup] [options]

Without a command the whole lifecycle is executed: create, run, cleanup.
The connection settings are taken from the environment (YDB_CONNECTION_STRING or
YDB_ENDPOINT with YDB_DATABASE), see README.MD for the full contract.

Commands:
  create    creates the table and fills it with the initial data
  run       runs the workload (reads and writes rows with the given RPS)
  cleanup   drops the table

Options (every setting of src/Config.php can be overridden, the most useful ones):
  -table-name             <string> table path relative to the database
  -prefill-count          <int>    amount of rows written before the workload starts

  -read-rps               <int>    read RPS, an upper bound for all read processes
  -read-timeout           <int>    read timeout in milliseconds
  -read-forks             <int>    amount of read processes
  -write-rps              <int>    write RPS, an upper bound for all write processes
  -write-timeout          <int>    write timeout in milliseconds
  -write-forks            <int>    amount of write processes

  -duration               <int>    workload duration in seconds
  -report-period          <int>    metrics push period in milliseconds
  -shutdown-time          <int>    graceful shutdown time in seconds
  -otlp-endpoint          <string> OTLP metrics endpoint
";

$phases = ['create', 'run', 'cleanup'];
$args = array_slice($argv, 1);

if ($args && substr($args[0], 0, 1) != '-') {
    $phase = array_shift($args);
    if (!in_array($phase, $phases)) {
        fwrite(STDERR, $usage);
        exit(1);
    }
    $phases = [$phase];
}

try {
    $config = Config::fromEnv($args);
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n\n" . $usage);
    exit(1);
}

/**
 * Every phase runs in its own process: the gRPC core does not survive a fork, so the
 * main process must never connect to the database — the workload forks its own
 * workers, see src/processes.php.
 *
 * @return int exit code of the phase
 */
function runPhase(string $phase, Config $config): int
{
    $exitCode = waitForProcess(forkProcess(function () use ($phase, $config) {
        switch ($phase) {
            case 'create':
                create($config);
                break;
            case 'run':
                run($config);
                break;
            case 'cleanup':
                cleanup($config);
                break;
        }
    }));

    if ($exitCode != 0) {
        fwrite(STDERR, "$phase failed with exit code $exitCode\n");
    }

    return $exitCode;
}

foreach ($phases as $phase) {
    if (runPhase($phase, $config) == 0) {
        continue;
    }

    // The whole lifecycle always drops the table, even when the workload itself failed:
    // a leftover table would break the next run against the same database.
    if (count($phases) > 1 && $phase != 'cleanup') {
        runPhase('cleanup', $config);
    }

    exit(1);
}
