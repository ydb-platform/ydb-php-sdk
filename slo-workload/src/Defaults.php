<?php

namespace YdbPlatform\Ydb\Slo;

class Defaults
{
    const TABLE_MIN_PARTITION_COUNT = 6;
    const TABLE_MAX_PARTITION_COUNT = 1000;
    const TABLE_PARTITION_SIZE = 1;

    const PREFILL_COUNT = 1000;

    const DURATION = 600; // seconds

    const READ_FORKS = 3;
    const WRITE_FORKS = 1;

    const READ_RPS = 1000;
    const READ_TIMEOUT = 10000; // milliseconds

    const WRITE_RPS = 100;
    const WRITE_TIMEOUT = 10000; // milliseconds

    const SHUTDOWN_TIME = 30; // seconds
    const REPORT_PERIOD = 1000; // milliseconds

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
