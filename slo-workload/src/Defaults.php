<?php

namespace YdbPlatform\Ydb\Slo;

class Defaults
{
    const TABLE_NAME = 'slo-php';
    const TABLE_MIN_PARTITION_COUNT = 6;
    const TABLE_MAX_PARTITION_COUNT = 1000;
    const TABLE_PARTITION_SIZE = 1;

    const GENERATOR_DATA_COUNT = 1000;

    const RPS_PER_FORK = 10;

    const READ_RPS = 1000;
    const READ_TIMEOUT = 70; // milliseconds
    const READ_TIME = 140; // seconds

    const WRITE_RPS = 100;
    const WRITE_TIMEOUT = 20000; // milliseconds
    const WRITE_TIME = 140; // seconds

    const SHUTDOWN_TIME = 30;
    const PROMETHEUS_PUSH_GATEWAY = 'http://prometheus-pushgateway:9091';
    const PROMETHEUS_PUSH_PERIOD = 250; // milliseconds

    const WRITE_QUERY = 'DECLARE $id AS Uint64;
DECLARE $payload_str AS Utf8;
DECLARE $payload_double AS Double;
DECLARE $payload_timestamp AS Timestamp;
UPSERT INTO `%s` (
  id, hash, payload_str, payload_double, payload_timestamp
) VALUES (
  $id, Digest::NumericHash($id), $payload_str, $payload_double, $payload_timestamp
);';
    const READ_QUERY = 'DECLARE $id AS Uint64;
SELECT id, payload_str, payload_double, payload_timestamp, payload_hash
FROM `%s` WHERE id = $id AND hash = Digest::NumericHash($id);';
}
