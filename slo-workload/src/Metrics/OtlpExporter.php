<?php

namespace YdbPlatform\Ydb\Slo\Metrics;

/**
 * Minimal OTLP/HTTP metrics exporter.
 *
 * Metrics are encoded as OTLP/JSON (protobuf JSON mapping) and pushed to the
 * Prometheus OTLP receiver, which accepts both `application/x-protobuf` and
 * `application/json`. JSON keeps the workload free of any protobuf dependency
 * conflicting with the one pinned by the SDK itself.
 */
class OtlpExporter
{
    const SUM = 'sum';
    const GAUGE = 'gauge';

    const AGGREGATION_TEMPORALITY_CUMULATIVE = 2;

    /** @var string */
    protected $endpoint;
    /** @var array<string, string> */
    protected $resourceAttributes;
    /** @var int nanoseconds, the start of the cumulative counters */
    protected $startTimeUnixNano;
    /** @var string|null the last export error, for diagnostics */
    protected $lastError;

    public function __construct(string $endpoint, array $resourceAttributes, float $startTime)
    {
        $this->endpoint = $endpoint;
        $this->resourceAttributes = $resourceAttributes;
        $this->startTimeUnixNano = self::toUnixNano($startTime);
    }

    /**
     * @param array $metrics list of metrics, each one is
     *      ['name' => string, 'kind' => self::SUM|self::GAUGE, 'points' => [
     *          ['attributes' => array<string, string>, 'value' => int|float, 'isInt' => bool]
     *      ]]
     * @return bool false when the push failed, the error is available via lastError()
     */
    public function export(array $metrics): bool
    {
        $timeUnixNano = self::toUnixNano(microtime(true));

        $encoded = [];
        foreach ($metrics as $metric) {
            if (!$metric['points']) {
                continue;
            }

            $dataPoints = [];
            foreach ($metric['points'] as $point) {
                $dataPoint = [
                    'attributes' => $this->encodeAttributes($point['attributes']),
                    'startTimeUnixNano' => $this->startTimeUnixNano,
                    'timeUnixNano' => $timeUnixNano,
                ];
                if (!empty($point['isInt'])) {
                    $dataPoint['asInt'] = (string)$point['value'];
                } else {
                    $dataPoint['asDouble'] = (float)$point['value'];
                }
                $dataPoints[] = $dataPoint;
            }

            if ($metric['kind'] == self::SUM) {
                $data = [
                    'dataPoints' => $dataPoints,
                    'aggregationTemporality' => self::AGGREGATION_TEMPORALITY_CUMULATIVE,
                    'isMonotonic' => true,
                ];
            } else {
                $data = ['dataPoints' => $dataPoints];
            }

            $encoded[] = [
                'name' => $metric['name'],
                $metric['kind'] => $data,
            ];
        }

        if (!$encoded) {
            return true;
        }

        return $this->post([
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => $this->encodeAttributes($this->resourceAttributes)],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'ydb-php-sdk-slo-workload'],
                            'metrics' => $encoded,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function lastError()
    {
        return $this->lastError;
    }

    protected function post(array $payload): bool
    {
        $body = json_encode($payload);
        if ($body === false) {
            $this->lastError = 'failed to encode metrics: ' . json_last_error_msg();
            return false;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            $this->lastError = 'push to ' . $this->endpoint . ' failed: ' . $error;
            return false;
        }
        if ($status < 200 || $status >= 300) {
            $this->lastError = 'push to ' . $this->endpoint . ' failed with HTTP ' . $status . ': ' . $response;
            return false;
        }

        $this->lastError = null;
        return true;
    }

    protected function encodeAttributes(array $attributes): array
    {
        $encoded = [];
        foreach ($attributes as $key => $value) {
            $encoded[] = ['key' => $key, 'value' => ['stringValue' => (string)$value]];
        }

        return $encoded;
    }

    /**
     * Unix nanoseconds are 64-bit protobuf fields, encoded as strings in OTLP/JSON.
     */
    protected static function toUnixNano(float $time): string
    {
        $seconds = (int)$time;
        $nanos = (int)round(($time - $seconds) * 1e9);
        if ($nanos >= 1000000000) {
            $seconds++;
            $nanos -= 1000000000;
        }

        return sprintf('%d%09d', $seconds, $nanos);
    }
}
