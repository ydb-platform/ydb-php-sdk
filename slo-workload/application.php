<?php

require_once __DIR__ . '/vendor/autoload.php';

use YdbPlatform\Ydb\Slo\Command;
use YdbPlatform\Ydb\Slo\Commands\CleanupCommand;
use YdbPlatform\Ydb\Slo\Commands\CreateCommand;
use YdbPlatform\Ydb\Slo\Commands\RunCommand;
use YdbPlatform\Ydb\Slo\Config;

/**
 * @var Command[] $commands
 */
$commands = [
    'create' => new CreateCommand(),
    'run' => new RunCommand(),
    'cleanup' => new CleanupCommand(),
];

$usage = "Usage: php application.php [create|run|cleanup] [options]

Without a command the whole lifecycle is executed: create, run, cleanup.
The connection settings are taken from the environment (YDB_CONNECTION_STRING or
YDB_ENDPOINT with YDB_DATABASE), see README.MD for the full contract.

Commands:
" . implode("", array_map(function (Command $command) {
        return sprintf("  %-10s %s\n", $command->name, $command->description);
    }, $commands)) . "
Options:
  -t -table-name          <string> table path relative to the database
  -min-partitions-count   <int>    minimum amount of partitions in the table
  -max-partitions-count   <int>    maximum amount of partitions in the table
  -partition-size         <int>    partition size in mb
  -prefill-count          <int>    amount of rows written before the workload starts

  -read-rps               <int>    read RPS, an upper bound for all read processes
  -read-timeout           <int>    read timeout in milliseconds
  -read-forks             <int>    amount of read processes
  -write-rps              <int>    write RPS, an upper bound for all write processes
  -write-timeout          <int>    write timeout in milliseconds
  -write-forks            <int>    amount of write processes

  -time                   <int>    workload duration in seconds
  -report-period          <int>    metrics push period in milliseconds
  -shutdown-time          <int>    graceful shutdown time in seconds
  -otlp-endpoint          <string> OTLP metrics endpoint
";

$args = array_slice($argv, 1);
$phases = array_keys($commands);

if ($args && substr($args[0], 0, 1) != '-') {
    $phase = array_shift($args);
    if (!isset($commands[$phase])) {
        fwrite(STDERR, $usage);
        exit(1);
    }
    $phases = [$phase];
}

try {
    $config = Config::fromEnv(Config::parseOptions($args));
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . "\n\n" . $usage);
    exit(1);
}

// The whole lifecycle always drops the table, even when the workload itself failed:
// a leftover table would break the next run against the same database.
$cleanupOnFailure = count($phases) > 1 && in_array('cleanup', $phases);

foreach ($phases as $phase) {
    try {
        $commands[$phase]->execute($config);
    } catch (Throwable $e) {
        fwrite(STDERR, sprintf("%s failed: %s\n", $phase, $e->getMessage()));

        if ($cleanupOnFailure && $phase != 'cleanup') {
            try {
                $commands['cleanup']->execute($config);
            } catch (Throwable $cleanupError) {
                fwrite(STDERR, sprintf("cleanup failed: %s\n", $cleanupError->getMessage()));
            }
        }

        exit(1);
    }
}
