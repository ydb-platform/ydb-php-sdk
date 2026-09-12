<?php

/**
 * Metrics required by `ydb-platform/ydb-slo-action`:
 *
 *   sdk_operations_total{ref, operation_type, operation_status}
 *   sdk_retry_attempts_total{ref, operation_type, operation_status}
 *   sdk_operation_latency_p50_seconds{ref, operation_type, operation_status}
 *   sdk_operation_latency_p95_seconds{...}
 *   sdk_operation_latency_p99_seconds{...}
 *   sdk_errors_total{ref, operation_type, operation_status, error_name}
 *
 * Counters are cumulative, latency percentiles are computed over the samples
 * collected since the previous push.
 *
 * They are pushed as OTLP/JSON to the Prometheus OTLP receiver: it accepts both
 * protobuf and JSON, and JSON keeps the workload free of a protobuf dependency
 * conflicting with the one pinned by the SDK itself.
 */

const SLO_PERCENTILES = [
    'sdk_operation_latency_p50_seconds' => 0.5,
    'sdk_operation_latency_p95_seconds' => 0.95,
    'sdk_operation_latency_p99_seconds' => 0.99,
];

const SLO_CUMULATIVE = 2; // AGGREGATION_TEMPORALITY_CUMULATIVE

function slo_metrics_new($ref)
{
    $metrics = [
        'ref' => $ref,
        'operations' => [], // "type|status" => amount of operations
        'attempts' => [],   // "type|status" => amount of attempts
        'errors' => [],     // "type|error name" => amount of errors
        'latencies' => [],  // "type|status" => latencies in seconds, since the last push
    ];

    // Series are initialized with zeroes, so that `rate()` over them is defined
    // from the very beginning of the workload.
    foreach (['read', 'write'] as $type) {
        foreach (['success', 'failure'] as $status) {
            $metrics['operations']["$type|$status"] = 0;
            $metrics['attempts']["$type|$status"] = 0;
        }
    }

    return $metrics;
}

/**
 * Counts one event of a worker, see slo_operation() for the event itself.
 */
function slo_metrics_add(array &$metrics, array $event)
{
    if ($event['type'] == 'ok' || $event['type'] == 'err') {
        $key = $event['operation'] . ($event['type'] == 'ok' ? '|success' : '|failure');

        $metrics['operations'][$key]++;
        $metrics['attempts'][$key] += max(1, $event['attempts']);
        $metrics['latencies'][$key][] = $event['latency'];
    }

    // Both a failed and a retried operation carry the error they ended the attempt with.
    if (isset($event['error'])) {
        $key = $event['operation'] . '|' . $event['error'];
        $metrics['errors'][$key] = (isset($metrics['errors'][$key]) ? $metrics['errors'][$key] : 0) + 1;
    }
}

/**
 * Builds the OTLP payload out of the current state. The collected latencies are
 * consumed: the next percentiles are computed over the next window.
 *
 * @param array $resourceAttributes attributes of every metric of this workload
 */
function slo_metrics_payload(array &$metrics, array $resourceAttributes, $startTime)
{
    $start = slo_unix_nano($startTime);
    $now = slo_unix_nano(microtime(true));
    $ref = $metrics['ref'];

    $operations = [];
    foreach ($metrics['operations'] as $key => $value) {
        $operations[] = slo_otlp_point(slo_labels($ref, $key), $value, true, $start, $now);
    }

    $attempts = [];
    foreach ($metrics['attempts'] as $key => $value) {
        $attempts[] = slo_otlp_point(slo_labels($ref, $key), $value, true, $start, $now);
    }

    $errors = [];
    foreach ($metrics['errors'] as $key => $value) {
        list($type, $name) = explode('|', $key, 2);
        $attributes = slo_labels($ref, "$type|failure");
        $attributes['error_name'] = $name;
        $errors[] = slo_otlp_point($attributes, $value, true, $start, $now);
    }

    $latencies = []; // metric name => points
    foreach ($metrics['latencies'] as $key => $samples) {
        sort($samples);
        foreach (SLO_PERCENTILES as $name => $quantile) {
            $value = slo_percentile($samples, $quantile);
            $latencies[$name][] = slo_otlp_point(slo_labels($ref, $key), $value, false, $start, $now);
        }
    }
    $metrics['latencies'] = [];

    $encoded = [
        slo_otlp_metric('sdk_operations_total', 'sum', $operations),
        slo_otlp_metric('sdk_retry_attempts_total', 'sum', $attempts),
        slo_otlp_metric('sdk_errors_total', 'sum', $errors),
    ];
    foreach ($latencies as $name => $points) {
        $encoded[] = slo_otlp_metric($name, 'gauge', $points);
    }

    return [
        'resourceMetrics' => [
            [
                'resource' => ['attributes' => slo_otlp_attributes($resourceAttributes)],
                'scopeMetrics' => [
                    [
                        'scope' => ['name' => 'ydb-php-sdk-slo-workload'],
                        'metrics' => array_values(array_filter($encoded)),
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Pushes the payload to the OTLP endpoint.
 *
 * @return string empty when the push succeeded, the error otherwise
 */
function slo_metrics_push($endpoint, array $payload)
{
    $body = json_encode($payload);
    if ($body === false) {
        return 'failed to encode metrics: ' . json_last_error_msg();
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint,
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
        return "push to $endpoint failed: $error";
    }
    if ($status < 200 || $status >= 300) {
        return "push to $endpoint failed with HTTP $status: $response";
    }

    return '';
}

/**
 * Labels of a data point, out of the "type|status" key the counters are stored by.
 */
function slo_labels($ref, $key)
{
    list($type, $status) = explode('|', $key);

    return ['ref' => $ref, 'operation_type' => $type, 'operation_status' => $status];
}

/**
 * @param array $points data points, a metric without them is not reported at all
 * @param string $kind `sum` for a cumulative counter, `gauge` for a current value
 * @return array|null
 */
function slo_otlp_metric($name, $kind, array $points)
{
    if (!$points) {
        return null;
    }

    $data = ['dataPoints' => $points];
    if ($kind == 'sum') {
        $data['aggregationTemporality'] = SLO_CUMULATIVE;
        $data['isMonotonic'] = true;
    }

    return ['name' => $name, $kind => $data];
}

/**
 * @param bool $isInt integers and doubles are different fields in OTLP
 */
function slo_otlp_point(array $attributes, $value, $isInt, $start, $now)
{
    $point = [
        'attributes' => slo_otlp_attributes($attributes),
        'startTimeUnixNano' => $start,
        'timeUnixNano' => $now,
    ];
    if ($isInt) {
        $point['asInt'] = (string)$value;
    } else {
        $point['asDouble'] = (float)$value;
    }

    return $point;
}

function slo_otlp_attributes(array $attributes)
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
function slo_unix_nano($time)
{
    $seconds = (int)$time;
    $nanos = (int)round(($time - $seconds) * 1e9);
    if ($nanos >= 1000000000) {
        $seconds++;
        $nanos -= 1000000000;
    }

    return sprintf('%d%09d', $seconds, $nanos);
}

/**
 * @param array $sorted latencies in seconds, sorted
 */
function slo_percentile(array $sorted, $quantile)
{
    if (!$sorted) {
        return 0.0;
    }

    $index = (int)ceil($quantile * count($sorted)) - 1;

    return $sorted[max(0, min(count($sorted) - 1, $index))];
}
