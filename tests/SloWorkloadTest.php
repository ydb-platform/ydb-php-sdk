<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;

/**
 * The SLO workload reports metrics to `ydb-platform/ydb-slo-action`, which reads them
 * from Prometheus by exact metric and label names, so both are checked here.
 */
class SloWorkloadTest extends TestCase
{
    public function testSplitConnectionString()
    {
        $this->assertEquals(
            ['grpc://ydb:2136', '/Root/testdb'],
            slo_split_connection_string('grpc://ydb:2136/Root/testdb')
        );
        $this->assertEquals(
            ['grpcs://ydb.example.com:2135', '/some/database'],
            slo_split_connection_string('grpcs://ydb.example.com:2135?database=/some/database')
        );
    }

    public function testOptions()
    {
        $options = slo_options(['-table-name', 'php-table/current', '--read-rps', '500', '--write-rps=50']);

        $this->assertEquals([
            'table_name' => 'php-table/current',
            'read_rps' => '500',
            'write_rps' => '50',
        ], $options);
    }

    public function testMetrics()
    {
        $metrics = slo_metrics_new('current');
        slo_metrics_add($metrics, ['type' => 'ok', 'operation' => 'read', 'attempts' => 1, 'latency' => 0.01]);
        slo_metrics_add($metrics, ['type' => 'ok', 'operation' => 'read', 'attempts' => 2, 'latency' => 0.02]);
        slo_metrics_add($metrics, [
            'type' => 'err',
            'operation' => 'write',
            'attempts' => 3,
            'latency' => 0.5,
            'error' => 'YDB_UNAVAILABLE',
        ]);

        $series = $this->series(slo_metrics_payload($metrics, ['service.name' => 'php-table'], microtime(true)));

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

        // Samples are consumed by the push, the next window starts empty.
        $next = $this->series(slo_metrics_payload($metrics, [], microtime(true)));
        $this->assertArrayNotHasKey('sdk_operation_latency_p50_seconds', $next);
        $this->assertEquals(2, $next['sdk_operations_total']['read|success']['asInt']);
    }

    public function testPayloadFormat()
    {
        $metrics = slo_metrics_new('baseline');
        slo_metrics_add($metrics, ['type' => 'ok', 'operation' => 'read', 'attempts' => 1, 'latency' => 0.01]);

        $payload = slo_metrics_payload($metrics, ['ref' => 'baseline'], 1.5);
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
        $this->assertEquals(SLO_CUMULATIVE, $sum['aggregationTemporality']);
        // Nanoseconds of a 64-bit protobuf field are encoded as strings.
        $this->assertEquals('1500000000', $sum['dataPoints'][0]['startTimeUnixNano']);

        $this->assertArrayHasKey('gauge', $encoded['sdk_operation_latency_p50_seconds']);
        $this->assertArrayNotHasKey('sum', $encoded['sdk_operation_latency_p50_seconds']);
    }

    /**
     * @return array metric name => "operation_type|operation_status" => data point
     */
    protected function series(array $payload)
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
