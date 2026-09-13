<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Slo\Config;
use YdbPlatform\Ydb\Slo\Event;
use YdbPlatform\Ydb\Slo\Metrics;
use YdbPlatform\Ydb\Slo\Otlp;

/**
 * The SLO workload reports metrics to `ydb-platform/ydb-slo-action`, which reads them
 * from Prometheus by exact metric and label names, so both are checked here.
 */
class SloWorkloadTest extends TestCase
{
    public function testSplitConnectionString()
    {
        $connection = Config::splitConnectionString('grpc://ydb:2136/Root/testdb');
        $this->assertEquals('grpc://ydb:2136', $connection->endpoint);
        $this->assertEquals('/Root/testdb', $connection->database);

        $connection = Config::splitConnectionString('grpcs://ydb.example.com:2135?database=/some/database');
        $this->assertEquals('grpcs://ydb.example.com:2135', $connection->endpoint);
        $this->assertEquals('/some/database', $connection->database);
    }

    public function testParseOptions()
    {
        $options = Config::parseOptions(['-table-name', 'php-table/current', '--read-rps', '500', '--write-rps=50']);

        $this->assertEquals([
            'table-name' => 'php-table/current',
            'read-rps' => '500',
            'write-rps' => '50',
        ], $options);
    }

    /**
     * A missing value must not be taken from the next option: `-read-rps` without a
     * number would silently become 0, which is "no rate limit at all".
     */
    public function testOptionWithoutValue()
    {
        $this->expectExceptionMessage('option -read-rps needs a value');
        Config::parseOptions(['-read-rps', '-write-rps', '50']);
    }

    public function testOptionWithNotANumber()
    {
        $this->expectExceptionMessage('option -read-rps needs a number');
        Config::fromEnv(['-read-rps', 'fast']);
    }

    public function testMetrics()
    {
        $metrics = new Metrics('current');
        $metrics->add(Event::succeeded(Metrics::READ, 1, 0.01));
        $metrics->add(Event::succeeded(Metrics::READ, 2, 0.02));
        $metrics->add(Event::failed(Metrics::WRITE, 3, 0.5, 'YDB_UNAVAILABLE'));

        $series = $this->series($metrics->payload(['service.name' => 'php-table'], microtime(true)));

        // Counters are cumulative and include the zero-initialized series.
        $this->assertEquals(2, $series['sdk_operations_total']['read|success']['asInt']);
        $this->assertEquals(1, $series['sdk_operations_total']['write|failure']['asInt']);
        $this->assertEquals(0, $series['sdk_operations_total']['write|success']['asInt']);
        $this->assertEquals(3, $series['sdk_retry_attempts_total']['read|success']['asInt']);
        $this->assertEquals(3, $series['sdk_retry_attempts_total']['write|failure']['asInt']);
        $this->assertEquals(1, $series['sdk_errors_total']['write|failure']['asInt']);

        // Latency percentiles are gauges over the samples of the last window.
        $this->assertEquals(0.01, $series['sdk_operation_latency_p50_seconds']['read|success']['asDouble']);
        $this->assertEquals(0.02, $series['sdk_operation_latency_p99_seconds']['read|success']['asDouble']);
        $this->assertEquals(0.5, $series['sdk_operation_latency_p50_seconds']['write|failure']['asDouble']);

        // Samples are consumed by the payload, the next window starts empty.
        $next = $this->series($metrics->payload([], microtime(true)));
        $this->assertArrayNotHasKey('sdk_operation_latency_p50_seconds', $next);
        $this->assertEquals(2, $next['sdk_operations_total']['read|success']['asInt']);
    }

    /**
     * A retried attempt is counted as an error, but not as a finished operation.
     */
    public function testRetriedAttempt()
    {
        $metrics = new Metrics('current');
        $metrics->add(Event::retried(Metrics::READ, 'GRPC_UNAVAILABLE'));

        $series = $this->series($metrics->payload([], microtime(true)));

        $this->assertEquals(1, $series['sdk_errors_total']['read|failure']['asInt']);
        $this->assertEquals(0, $series['sdk_operations_total']['read|success']['asInt']);
        $this->assertEquals(0, $series['sdk_operations_total']['read|failure']['asInt']);
    }

    public function testPayloadFormat()
    {
        $metrics = new Metrics('baseline');
        $metrics->add(Event::succeeded(Metrics::READ, 1, 0.01));

        $payload = $metrics->payload(['ref' => 'baseline'], 1.5);
        $resource = $payload['resourceMetrics'][0];

        $this->assertEquals(
            [['key' => 'ref', 'value' => ['stringValue' => 'baseline']]],
            $resource['resource']['attributes']
        );

        $encoded = [];
        foreach ($resource['scopeMetrics'][0]['metrics'] as $metric) {
            $encoded[$metric['name']] = $metric;
        }

        $sum = $encoded['sdk_operations_total']['sum'];
        $this->assertTrue($sum['isMonotonic']);
        $this->assertEquals(Otlp::AGGREGATION_TEMPORALITY_CUMULATIVE, $sum['aggregationTemporality']);
        // Nanoseconds of a 64-bit protobuf field are encoded as strings.
        $this->assertEquals('1500000000', $sum['dataPoints'][0]['startTimeUnixNano']);

        $this->assertArrayHasKey('gauge', $encoded['sdk_operation_latency_p50_seconds']);
        $this->assertArrayNotHasKey('sum', $encoded['sdk_operation_latency_p50_seconds']);
    }

    /**
     * @return array metric name => "operation_type|operation_status" => data point
     */
    protected function series(array $payload): array
    {
        $series = [];
        foreach ($payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'] as $metric) {
            $data = isset($metric['sum']) ? $metric['sum'] : $metric['gauge'];

            foreach ($data['dataPoints'] as $dataPoint) {
                $labels = [];
                foreach ($dataPoint['attributes'] as $attribute) {
                    $labels[$attribute['key']] = $attribute['value']['stringValue'];
                }
                $key = $labels['operation_type'] . '|' . $labels['operation_status'];
                $series[$metric['name']][$key] = $dataPoint;
            }
        }

        return $series;
    }
}
