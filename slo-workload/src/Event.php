<?php

namespace YdbPlatform\Ydb\Slo;

/**
 * What a worker reports to the metrics process, see EventQueue.
 */
class Event
{
    const SUCCEEDED = 'succeeded';
    const FAILED = 'failed';
    const RETRIED = 'retried';
    const WORKER_DONE = 'worker-done';

    /** @var string one of the constants above */
    public $type;
    /** @var string Metrics::READ or Metrics::WRITE */
    public $operation = '';
    /** @var int attempts the operation took, including the first one */
    public $attempts = 0;
    /** @var float how long the operation took, in seconds */
    public $latency = 0.0;
    /** @var string error name of a failed or retried attempt, empty when there is none */
    public $error = '';

    public static function succeeded(string $operation, int $attempts, float $latency): Event
    {
        $event = new Event();
        $event->type = self::SUCCEEDED;
        $event->operation = $operation;
        $event->attempts = $attempts;
        $event->latency = $latency;

        return $event;
    }

    public static function failed(string $operation, int $attempts, float $latency, string $error): Event
    {
        $event = new Event();
        $event->type = self::FAILED;
        $event->operation = $operation;
        $event->attempts = $attempts;
        $event->latency = $latency;
        $event->error = $error;

        return $event;
    }

    /**
     * An attempt that failed and is being retried: the operation itself is not
     * finished yet, only the error is counted.
     */
    public static function retried(string $operation, string $error): Event
    {
        $event = new Event();
        $event->type = self::RETRIED;
        $event->operation = $operation;
        $event->error = $error;

        return $event;
    }

    /**
     * Tells the metrics process that one of the workers has finished.
     */
    public static function workerDone(): Event
    {
        $event = new Event();
        $event->type = self::WORKER_DONE;

        return $event;
    }
}
