<?php

namespace YdbPlatform\Ydb\Slo;

use Closure;
use Exception;
use Throwable;

/**
 * Forking helper.
 *
 * The gRPC core cannot survive a fork: a process that has already opened a channel
 * dies with "Oops, failed to shutdown gRPC Core after fork()" in the child. Therefore
 * the main process never talks to YDB itself — every phase runs in its own child, and
 * the workload workers are forked from a process that has not opened a channel yet.
 */
class Fork
{
    /**
     * Runs the job in a child process and returns its exit code.
     *
     * @throws Exception when the process cannot be forked
     */
    public static function runAndWait(Closure $job): int
    {
        $pid = self::start($job);
        pcntl_waitpid($pid, $status);

        return self::exitCode($status);
    }

    /**
     * Forks the job into the background and returns its pid.
     *
     * @throws Exception when the process cannot be forked
     */
    public static function start(Closure $job): int
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new Exception("unable to fork a process");
        }
        if ($pid != 0) {
            return $pid;
        }

        $exitCode = 0;
        try {
            $job();
        } catch (Throwable $e) {
            fwrite(STDERR, get_class($e) . ": " . $e->getMessage() . "\n");
            $exitCode = 1;
        }

        // The child must never return into the code of the parent process.
        exit($exitCode);
    }

    public static function exitCode(int $status): int
    {
        return pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 1;
    }
}
