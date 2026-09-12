<?php

/**
 * Processes and the events they exchange.
 *
 * PHP has no threads, so the workload is a set of forked processes. The gRPC core
 * cannot survive a fork — a process that has already opened a connection dies with
 * "Oops, failed to shutdown gRPC Core after fork()" in the child — so a process
 * forks first and connects to the database afterwards.
 *
 * The workers report every finished operation to the metrics process over a System V
 * message queue.
 */

const SLO_MESSAGE_TYPE = 1;
const SLO_MESSAGE_SIZE_LIMIT = 1024;

/**
 * Runs the job in a child process and returns its pid.
 */
function slo_fork(Closure $job)
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
function slo_wait($pid)
{
    pcntl_waitpid($pid, $status);

    return pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 1;
}

/**
 * Asks the process to stop at the next possible moment instead of dying on SIGTERM.
 */
function slo_stop_on_signals()
{
    pcntl_async_signals(true);
    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, function () {
            $GLOBALS['slo_stopped'] = true;
        });
    }
}

function slo_stopped()
{
    return !empty($GLOBALS['slo_stopped']);
}

/**
 * Creates the queue the workers report their operations to. It has to be created
 * before the workers are forked: concurrent `msg_get_queue()` calls for the same key
 * race with each other, and the loser gets an error instead of the queue.
 */
function slo_queue_create()
{
    $key = ftok(__FILE__, 'm');

    // A queue left over from a previous run would mix stale events into the metrics.
    $existing = @msg_get_queue($key);
    if ($existing) {
        msg_remove_queue($existing);
    }

    $queue = msg_get_queue($key);
    if (!$queue) {
        throw new Exception('unable to create the message queue');
    }

    return $queue;
}

/**
 * The send is blocking: the queue is drained continuously by the metrics process,
 * and losing events would skew the metrics.
 */
function slo_queue_send($queue, array $event)
{
    msg_send($queue, SLO_MESSAGE_TYPE, $event);
}

/**
 * Receives the next event without blocking.
 *
 * @param array|null $event
 * @return bool false when the queue is empty
 */
function slo_queue_receive($queue, &$event)
{
    return (bool)@msg_receive(
        $queue,
        SLO_MESSAGE_TYPE,
        $messageType,
        SLO_MESSAGE_SIZE_LIMIT,
        $event,
        true,
        MSG_IPC_NOWAIT
    );
}

function slo_queue_remove($queue)
{
    @msg_remove_queue($queue);
}
