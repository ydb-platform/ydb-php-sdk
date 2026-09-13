<?php

namespace YdbPlatform\Ydb\Slo;

use Closure;
use Exception;
use Throwable;

/**
 * Forking and stopping the processes of the workload.
 *
 * PHP has no threads, so the workload is a set of forked processes. The gRPC core
 * cannot survive a fork — a process that has already opened a connection dies with
 * "Oops, failed to shutdown gRPC Core after fork()" in the child — so a process
 * forks first and connects to the database afterwards.
 */

/**
 * Runs the job in a child process and returns its pid.
 *
 * @throws Exception when the process cannot be forked
 */
function forkProcess(Closure $job): int
{
    $pid = pcntl_fork();
    if ($pid == -1) {
        throw new Exception('unable to fork a process');
    }
    if ($pid != 0) {
        return $pid;
    }

    $exitCode = 0;
    try {
        $job();
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
        $exitCode = 1;
    }

    // The child must never return into the code of the parent process.
    exit($exitCode);
}

/**
 * Waits for the child process and returns its exit code.
 */
function waitForProcess(int $pid): int
{
    pcntl_waitpid($pid, $status);

    return pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 1;
}

/**
 * Asks the process to stop at the next possible moment instead of dying on SIGTERM.
 */
function stopOnSignals()
{
    pcntl_async_signals(true);
    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, function () {
            $GLOBALS['slo_stop_requested'] = true;
        });
    }
}

function stopRequested(): bool
{
    return !empty($GLOBALS['slo_stop_requested']);
}
