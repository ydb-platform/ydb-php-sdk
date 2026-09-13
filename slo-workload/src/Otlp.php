<?php

namespace YdbPlatform\Ydb\Slo;

/**
 * Minimal OTLP/HTTP metrics encoder and sender.
 *
 * Metrics are encoded as OTLP/JSON (the protobuf JSON mapping) and pushed to the
 * Prometheus OTLP receiver: it accepts both `application/x-protobuf` and
 * `application/json`, and JSON keeps the workload free of a protobuf dependency
 * conflicting with the one pinned by the SDK itself.
 */
class Otlp
{
    const SUM = 'sum';
    const GAUGE = 'gauge';

    const AGGREGATION_TEMPORALITY_CUMULATIVE = 2;

    /**
     * One metric of the payload.
     *
     * @param string $kind self::SUM for a cumulative counter, self::GAUGE for a current value
     * @param array[] $points data points, see point(); a metric without them is not reported
     * @return array|null
     */
    public static function metric(string $name, string $kind, array $points)
    {
        if (!$points) {
            return null;
        }

        $data = ['dataPoints' => $points];
        if ($kind == self::SUM) {
            $data['aggregationTemporality'] = self::AGGREGATION_TEMPORALITY_CUMULATIVE;
            $data['isMonotonic'] = true;
        }

        return ['name' => $name, $kind => $data];
    }

    /**
     * @param string[] $labels label name => value
     * @param int|float $value
     * @param bool $isInt integers and doubles are different fields in OTLP
     * @param string $start unix nanoseconds the counters are measured from, see unixNano()
     * @param string $now unix nanoseconds of the data point itself
     */
    public static function point(array $labels, $value, bool $isInt, string $start, string $now): array
    {
        $point = [
            'attributes' => self::attributes($labels),
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

    /**
     * @param string[] $attributes attribute name => value
     */
    public static function attributes(array $attributes): array
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
    public static function unixNano(float $time): string
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
     * Pushes the payload built by Metrics::payload().
     *
     * @return string empty when the push succeeded, the error otherwise
     */
    public static function push(string $endpoint, array $payload): string
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
}
