<?php

namespace YdbPlatform\Ydb\Slo;

use Exception;

/**
 * System V message queue the workers report their operations to.
 *
 * The queue has to be created before the workers are forked: concurrent
 * `msg_get_queue()` calls for the same key race with each other, and the loser gets
 * an error instead of the queue. Forked processes inherit the queue.
 */
class EventQueue
{
    const MESSAGE_TYPE = 1;
    const MESSAGE_SIZE_LIMIT = 1024;

    /** @var resource|\SysvMessageQueue */
    protected $queue;

    /**
     * @throws Exception when the queue cannot be created
     */
    public static function create(): EventQueue
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

        $eventQueue = new EventQueue();
        $eventQueue->queue = $queue;

        return $eventQueue;
    }

    /**
     * The send is blocking: the queue is drained continuously by the metrics process,
     * and losing events would skew the metrics.
     */
    public function send(Event $event)
    {
        msg_send($this->queue, self::MESSAGE_TYPE, $event);
    }

    /**
     * Receives the next event without blocking.
     *
     * @return Event|null null when the queue is empty
     */
    public function receive(): ?Event
    {
        $received = @msg_receive(
            $this->queue,
            self::MESSAGE_TYPE,
            $messageType,
            self::MESSAGE_SIZE_LIMIT,
            $event,
            true,
            MSG_IPC_NOWAIT
        );

        return ($received && $event instanceof Event) ? $event : null;
    }

    public function remove()
    {
        @msg_remove_queue($this->queue);
    }
}
