<?php

namespace YdbPlatform\Ydb\Slo;

/**
 * Counts the events of the workers into the metrics required by
 * `ydb-platform/ydb-slo-action`:
 *
 *   sdk_operations_total{ref, operation_type, operation_status}
 *   sdk_retry_attempts_total{ref, operation_type, operation_status}
 *   sdk_operation_latency_p50_seconds{ref, operation_type, operation_status}
 *   sdk_operation_latency_p95_seconds{...}
 *   sdk_operation_latency_p99_seconds{...}
 *   sdk_errors_total{ref, operation_type, operation_status, error_name}
 *
 * Counters are cumulative, latency percentiles are computed over the samples
 * collected since the previous payload.
 */
class Metrics
{
    const READ = 'read';
    const WRITE = 'write';

    const SUCCESS = 'success';
    const FAILURE = 'failure';

    /** Metric name => the quantile it reports. */
    const PERCENTILES = [
        'sdk_operation_latency_p50_seconds' => 0.5,
        'sdk_operation_latency_p95_seconds' => 0.95,
        'sdk_operation_latency_p99_seconds' => 0.99,
    ];

    /** @var string value of the `ref` label: `current` or `baseline` */
    protected $ref;
    /** @var int[] finished operations, keyed by "operation|status" */
    protected $operations = [];
    /** @var int[] attempts, keyed by "operation|status" */
    protected $attempts = [];
    /** @var int[] errors, keyed by "operation|error name" */
    protected $errors = [];
    /** @var float[][] latencies in seconds since the last payload, keyed by "operation|status" */
    protected $latencies = [];

    public function __construct(string $ref)
    {
        $this->ref = $ref;

        // Series are initialized with zeroes, so that `rate()` over them is defined
        // from the very beginning of the workload.
        foreach ([self::READ, self::WRITE] as $operation) {
            foreach ([self::SUCCESS, self::FAILURE] as $status) {
                $this->operations["$operation|$status"] = 0;
                $this->attempts["$operation|$status"] = 0;
            }
        }
    }

    public function add(Event $event)
    {
        if ($event->type == Event::SUCCEEDED || $event->type == Event::FAILED) {
            $status = $event->type == Event::SUCCEEDED ? self::SUCCESS : self::FAILURE;
            $key = $event->operation . '|' . $status;

            $this->operations[$key]++;
            $this->attempts[$key] += max(1, $event->attempts);
            $this->latencies[$key][] = $event->latency;
        }

        // Both a failed and a retried operation carry the error of the last attempt.
        if ($event->error !== '') {
            $key = $event->operation . '|' . $event->error;
            $this->errors[$key] = (isset($this->errors[$key]) ? $this->errors[$key] : 0) + 1;
        }
    }

    /**
     * Builds the OTLP payload out of the current state, to be pushed with
     * Otlp::push(). The collected latencies are consumed: the next percentiles are
     * computed over the next window.
     *
     * @param string[] $resourceAttributes attributes of every metric of this workload
     * @param float $startTime unix time the cumulative counters are measured from
     */
    public function payload(array $resourceAttributes, float $startTime): array
    {
        $start = Otlp::unixNano($startTime);
        $now = Otlp::unixNano(microtime(true));

        $operations = [];
        foreach ($this->operations as $key => $value) {
            $operations[] = Otlp::point($this->labels($key), $value, true, $start, $now);
        }

        $attempts = [];
        foreach ($this->attempts as $key => $value) {
            $attempts[] = Otlp::point($this->labels($key), $value, true, $start, $now);
        }

        $errors = [];
        foreach ($this->errors as $key => $value) {
            list($operation, $name) = explode('|', $key, 2);
            $labels = $this->labels($operation . '|' . self::FAILURE);
            $labels['error_name'] = $name;
            $errors[] = Otlp::point($labels, $value, true, $start, $now);
        }

        $metrics = [
            Otlp::metric('sdk_operations_total', Otlp::SUM, $operations),
            Otlp::metric('sdk_retry_attempts_total', Otlp::SUM, $attempts),
            Otlp::metric('sdk_errors_total', Otlp::SUM, $errors),
        ];

        $latencies = []; // metric name => data points
        foreach ($this->latencies as $key => $samples) {
            sort($samples);
            foreach (self::PERCENTILES as $name => $quantile) {
                $value = self::percentile($samples, $quantile);
                $latencies[$name][] = Otlp::point($this->labels($key), $value, false, $start, $now);
            }
        }
        $this->latencies = [];

        foreach ($latencies as $name => $points) {
            $metrics[] = Otlp::metric($name, Otlp::GAUGE, $points);
        }

        return [
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => Otlp::attributes($resourceAttributes)],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'ydb-php-sdk-slo-workload'],
                            'metrics' => array_values(array_filter($metrics)),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Labels of a data point, out of the "operation|status" key of a counter.
     *
     * @return string[]
     */
    protected function labels(string $key): array
    {
        list($operation, $status) = explode('|', $key);

        return [
            'ref' => $this->ref,
            'operation_type' => $operation,
            'operation_status' => $status,
        ];
    }

    /**
     * @param float[] $sorted latencies in seconds, sorted
     */
    protected static function percentile(array $sorted, float $quantile): float
    {
        if (!$sorted) {
            return 0.0;
        }

        $index = (int)ceil($quantile * count($sorted)) - 1;

        return $sorted[max(0, min(count($sorted) - 1, $index))];
    }
}
