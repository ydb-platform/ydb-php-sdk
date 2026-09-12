<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Slo\Config;
use YdbPlatform\Ydb\Slo\Metrics\Metrics;
use YdbPlatform\Ydb\Slo\Metrics\OtlpExporter;

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
            Config::splitConnectionString('grpc://ydb:2136/Root/testdb')
        );
        $this->assertEquals(
            ['grpcs://ydb.example.com:2135', '/some/database'],
            Config::splitConnectionString('grpcs://ydb.example.com:2135?database=/some/database')
        );
    }

    public function testParseOptions()
    {
        $options = Config::parseOptions([
            'grpc://ydb:2136', '/Root/testdb',
            '-t', 'php-table/current',
            '--read-rps', '500',
            '--write-rps=50',
        ]);

        $this->assertEquals([
            'endpoint' => 'grpc://ydb:2136',
            'database' => '/Root/testdb',
            'table-name' => 'php-table/current',
            'read-rps' => '500',
            'write-rps' => '50',
        ], $options);
    }

    public function testMetricsPayload()
    {
        $metrics = new Metrics('current');
        $metrics->operationFinished(Metrics::READ, Metrics::SUCCESS, 1, 0.01);
        $metrics->operationFinished(Metrics::READ, Metrics::SUCCESS, 2, 0.02);
        $metrics->operationFinished(Metrics::WRITE, Metrics::FAILURE, 3, 0.5);
        $metrics->errorOccurred(Metrics::WRITE, 'YDB_UNAVAILABLE');

        $exporter = new PayloadCapturingExporter('http://localhost:9090/api/v1/otlp/v1/metrics', [
            'service.name' => 'php-table',
        ], microtime(true));
        $this->assertTrue($exporter->export($metrics->snapshot()));

        $series = $exporter->series();

        // Counters are cumulative and include the zero-initialized series.
        $this->assertEquals(2, $series['sdk_operations_total']['read|success']['asInt']);
        $this->assertEquals(1, $series['sdk_operations_total']['write|failure']['asInt']);
        $this->assertEquals(0, $series['sdk_operations_total']['write|success']['asInt']);
        $this->assertEquals(3, $series['sdk_retry_attempts_total']['read|success']['asInt']);
        $this->assertEquals(3, $series['sdk_retry_attempts_total']['write|failure']['asInt']);

        // Latency percentiles are gauges over the samples of the last window.
        $this->assertEquals(0.01, $series['sdk_operation_latency_p50_seconds']['read|success']['asDouble']);
        $this->assertEquals(0.02, $series['sdk_operation_latency_p99_seconds']['read|success']['asDouble']);
        $this->assertEquals(0.5, $series['sdk_operation_latency_p50_seconds']['write|failure']['asDouble']);

        // Samples are consumed by the export, the next window starts empty.
        $next = new PayloadCapturingExporter('http://localhost:9090/api/v1/otlp/v1/metrics', [], microtime(true));
        $next->export($metrics->snapshot());
        $this->assertEquals([], $next->series()['sdk_operation_latency_p50_seconds'] ?? []);
        $this->assertEquals(2, $next->series()['sdk_operations_total']['read|success']['asInt']);
    }

    public function testEncodedTypes()
    {
        $metrics = new Metrics('baseline');
        $metrics->operationFinished(Metrics::READ, Metrics::SUCCESS, 1, 0.01);

        $exporter = new PayloadCapturingExporter('http://localhost:9090/api/v1/otlp/v1/metrics', ['ref' => 'baseline'], 1.5);
        $exporter->export($metrics->snapshot());
        $payload = $exporter->payload();

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
        $this->assertEquals(OtlpExporter::AGGREGATION_TEMPORALITY_CUMULATIVE, $sum['aggregationTemporality']);
        // Nanoseconds of a 64-bit protobuf field are encoded as strings.
        $this->assertEquals('1500000000', $sum['dataPoints'][0]['startTimeUnixNano']);

        $this->assertArrayHasKey('gauge', $encoded['sdk_operation_latency_p50_seconds']);
        $this->assertArrayNotHasKey('sum', $encoded['sdk_operation_latency_p50_seconds']);
    }
}

class PayloadCapturingExporter extends OtlpExporter
{
    /** @var array */
    protected $payload = [];

    protected function post(array $payload): bool
    {
        $this->payload = $payload;
        return true;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array metric name => "operation_type|operation_status" => data point
     */
    public function series(): array
    {
        $series = [];
        foreach ($this->payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'] as $metric) {
            $data = $metric['sum'] ?? $metric['gauge'];
            $series[$metric['name']] = [];
            foreach ($data['dataPoints'] as $dataPoint) {
                $attributes = [];
                foreach ($dataPoint['attributes'] as $attribute) {
                    $attributes[$attribute['key']] = $attribute['value']['stringValue'];
                }
                $key = $attributes['operation_type'] . '|' . $attributes['operation_status'];
                $series[$metric['name']][$key] = $dataPoint;
            }
        }

        return $series;
    }
}
