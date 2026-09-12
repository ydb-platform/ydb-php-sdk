<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;

/**
 * System V message queue the workers report their operations to.
 *
 * The queue is created once, before the workers are forked: concurrent
 * `msg_get_queue()` calls for the same key race with each other, and the loser
 * gets `EEXIST` instead of the queue. Forked processes inherit the queue.
 */
class EventQueue
{
    const MSG_TYPE = 1;
    const MESSAGE_SIZE_LIMIT_BYTES = 1024;

    /** @var resource|\SysvMessageQueue */
    protected $queue;

    /**
     * @throws Exception
     */
    public static function create(string $keySource): EventQueue
    {
        $key = ftok($keySource, 'm');
        if ($key == -1) {
            throw new Exception('unable to build a message queue key from ' . $keySource);
        }

        // A queue left over from a previous run would mix stale events into the metrics.
        $existing = @msg_get_queue($key);
        if ($existing) {
            msg_remove_queue($existing);
        }

        $queue = msg_get_queue($key);
        if (!$queue) {
            throw new Exception('unable to create the message queue');
        }

        $eventQueue = new EventQueue();
        $eventQueue->queue = $queue;

        return $eventQueue;
    }

    public function operationSucceeded(string $job, int $attempts, float $latency)
    {
        $this->send([
            "type" => "ok",
            "job" => $job,
            "attempts" => $attempts,
            "latency" => $latency,
        ]);
    }

    public function operationFailed(string $job, int $attempts, string $error, float $latency)
    {
        $this->send([
            "type" => "err",
            "job" => $job,
            "attempts" => $attempts,
            "error" => Utils::getErrorName($error),
            "latency" => $latency,
        ]);
    }

    public function operationRetried(string $job, string $error)
    {
        $this->send([
            "type" => "retried",
            "job" => $job,
            "error" => Utils::getErrorName($error),
        ]);
    }

    /**
     * Tells the metrics job that one of the workers has finished.
     */
    public function workerDone()
    {
        $this->send(["type" => "done"]);
    }

    /**
     * Receives the next event without blocking.
     *
     * @param array|null $message
     */
    public function receive(&$message): bool
    {
        return (bool)@msg_receive(
            $this->queue,
            self::MSG_TYPE,
            $messageType,
            self::MESSAGE_SIZE_LIMIT_BYTES,
            $message,
            true,
            MSG_IPC_NOWAIT,
            $errorCode
        );
    }

    public function remove()
    {
        @msg_remove_queue($this->queue);
    }

    /**
     * The send is blocking: the queue is drained continuously by the metrics job,
     * and losing events would skew the metrics.
     */
    protected function send(array $data)
    {
        msg_send($this->queue, self::MSG_TYPE, $data);
    }
}
