<?php

namespace YdbPlatform\Ydb\Slo\Metrics;

/**
 * Aggregates workload events into the metrics required by `ydb-platform/ydb-slo-action`:
 *
 *   sdk_operations_total{ref, operation_type, operation_status}
 *   sdk_retry_attempts_total{ref, operation_type, operation_status}
 *   sdk_operation_latency_p50_seconds{ref, operation_type, operation_status}
 *   sdk_operation_latency_p95_seconds{...}
 *   sdk_operation_latency_p99_seconds{...}
 *   sdk_errors_total{ref, operation_type, operation_status, error_name}
 *
 * Counters are cumulative, latency percentiles are computed over the samples
 * collected since the previous export.
 */
class Metrics
{
    const READ = 'read';
    const WRITE = 'write';

    const SUCCESS = 'success';
    const FAILURE = 'failure';

    const PERCENTILES = [
        'sdk_operation_latency_p50_seconds' => 0.5,
        'sdk_operation_latency_p95_seconds' => 0.95,
        'sdk_operation_latency_p99_seconds' => 0.99,
    ];

    /** @var string */
    protected $ref;
    /** @var array<string, int> operations, keyed by "type|status" */
    protected $operations = [];
    /** @var array<string, int> attempts, keyed by "type|status" */
    protected $attempts = [];
    /** @var array<string, int> errors, keyed by "type|name" */
    protected $errors = [];
    /** @var array<string, float[]> latency samples in seconds, keyed by "type|status" */
    protected $latencies = [];
    /** @var array<string, float[]> percentiles of the last window, keyed by "type|status" */
    protected $percentiles = [];

    public function __construct(string $ref)
    {
        $this->ref = $ref;

        // Series are initialized with zeroes, so that `rate()` over them is defined
        // from the very beginning of the workload.
        foreach ([self::READ, self::WRITE] as $type) {
            foreach ([self::SUCCESS, self::FAILURE] as $status) {
                $this->operations[self::key($type, $status)] = 0;
                $this->attempts[self::key($type, $status)] = 0;
            }
        }
    }

    public function operationFinished(string $type, string $status, int $attempts, float $latencySeconds)
    {
        $key = self::key($type, $status);

        $this->operations[$key] = ($this->operations[$key] ?? 0) + 1;
        $this->attempts[$key] = ($this->attempts[$key] ?? 0) + max(1, $attempts);
        $this->latencies[$key][] = $latencySeconds;
    }

    public function errorOccurred(string $type, string $name)
    {
        $key = self::key($type, $name);
        $this->errors[$key] = ($this->errors[$key] ?? 0) + 1;
    }

    /**
     * Snapshots the current state as a list of metrics for OtlpExporter::export().
     * Latency samples collected so far are consumed.
     */
    public function snapshot(): array
    {
        $this->percentiles = [];
        foreach ($this->latencies as $key => $samples) {
            sort($samples);
            $this->percentiles[$key] = [];
            foreach (self::PERCENTILES as $name => $quantile) {
                $this->percentiles[$key][$name] = self::percentile($samples, $quantile);
            }
        }
        $this->latencies = [];

        $metrics = [
            [
                'name' => 'sdk_operations_total',
                'kind' => OtlpExporter::SUM,
                'points' => $this->operationPoints($this->operations),
            ],
            [
                'name' => 'sdk_retry_attempts_total',
                'kind' => OtlpExporter::SUM,
                'points' => $this->operationPoints($this->attempts),
            ],
            [
                'name' => 'sdk_errors_total',
                'kind' => OtlpExporter::SUM,
                'points' => $this->errorPoints(),
            ],
        ];

        foreach (array_keys(self::PERCENTILES) as $name) {
            $metrics[] = [
                'name' => $name,
                'kind' => OtlpExporter::GAUGE,
                'points' => $this->latencyPoints($name),
            ];
        }

        return $metrics;
    }

    protected function operationPoints(array $counters): array
    {
        $points = [];
        foreach ($counters as $key => $value) {
            list($type, $status) = self::unkey($key);
            $points[] = [
                'attributes' => [
                    'ref' => $this->ref,
                    'operation_type' => $type,
                    'operation_status' => $status,
                ],
                'value' => $value,
                'isInt' => true,
            ];
        }

        return $points;
    }

    protected function errorPoints(): array
    {
        $points = [];
        foreach ($this->errors as $key => $value) {
            list($type, $name) = self::unkey($key);
            $points[] = [
                'attributes' => [
                    'ref' => $this->ref,
                    'operation_type' => $type,
                    'operation_status' => self::FAILURE,
                    'error_name' => $name,
                ],
                'value' => $value,
                'isInt' => true,
            ];
        }

        return $points;
    }

    protected function latencyPoints(string $name): array
    {
        $points = [];
        foreach ($this->percentiles as $key => $values) {
            list($type, $status) = self::unkey($key);
            $points[] = [
                'attributes' => [
                    'ref' => $this->ref,
                    'operation_type' => $type,
                    'operation_status' => $status,
                ],
                'value' => $values[$name],
                'isInt' => false,
            ];
        }

        return $points;
    }

    /**
     * @param float[] $sorted
     */
    protected static function percentile(array $sorted, float $quantile): float
    {
        $count = count($sorted);
        if ($count == 0) {
            return 0.0;
        }

        $index = (int)ceil($quantile * $count) - 1;

        return $sorted[max(0, min($count - 1, $index))];
    }

    protected static function key(string $first, string $second): string
    {
        return $first . '|' . $second;
    }

    protected static function unkey(string $key): array
    {
        return explode('|', $key, 2);
    }
}
