# SLO workload

SLO is the type of test where an app based on ydb-sdk is tested against falling YDB cluster nodes, tablets, network
(that is possible situations for distributed DBs with hundreds of nodes).

The workload is run by [ydb-platform/ydb-slo-action](https://github.com/ydb-platform/ydb-slo-action): the action
deploys a YDB cluster with Prometheus and a chaos monkey, starts the workload image of the current and of the baseline
revision side by side, and builds a comparison report out of the metrics both of them push. See
[.github/workflows/slo.yml](../.github/workflows/slo.yml) and [.github/workflows/slo-report.yml](../.github/workflows/slo-report.yml).

## Usage

```
php application.php [create|run|cleanup] [options]
```

Without a command the whole lifecycle is executed: `create`, `run`, `cleanup` — this is how the action runs the image.

- `create`  - creates the table and fills it with the initial data
- `run`     - runs the workload (reads and writes rows with the given RPS)
- `cleanup` - drops the table

## Workload contract

Everything essential comes from the environment, as set by the action:

| Variable                              | Description                                                     |
|---------------------------------------|-----------------------------------------------------------------|
| `YDB_CONNECTION_STRING`               | e.g. `grpc://ydb:2136/Root/testdb`                              |
| `YDB_ENDPOINT`, `YDB_DATABASE`        | used when `YDB_CONNECTION_STRING` is not set                    |
| `WORKLOAD_REF`                        | value of the `ref` label in metrics: `current` or `baseline`     |
| `WORKLOAD_NAME`                       | workload name, the table lives in `<database>/<name>/<ref>`      |
| `WORKLOAD_DURATION`                   | budget of the whole run in seconds, see below                   |
| `OTEL_EXPORTER_OTLP_METRICS_ENDPOINT` | OTLP metrics endpoint; metrics are not pushed when it is not set |
| `OTEL_EXPORTER_OTLP_ENDPOINT`         | base OTLP endpoint, `/v1/metrics` is appended to it              |

Authentication uses anonymous credentials.

`WORKLOAD_DURATION` bounds the whole container run, not only the workload itself: the action
waits `WORKLOAD_DURATION + 60s` for the container to exit. Creating the table and filling it
happens inside that budget, and the last `-shutdown-time` seconds of it are reserved for
stopping the workers and dropping the table.

PHP has no threads and the gRPC core does not survive a fork, so the main process never opens a
connection: every phase, and every read, write and metrics worker inside `run`, is a separate
forked process.

## Options

Options override the defaults and may be passed through `workload_current_command` / `workload_baseline_command`
of the action:

```
  -t -table-name          <string> table path relative to the database
  -min-partitions-count   <int>    minimum amount of partitions in the table
  -max-partitions-count   <int>    maximum amount of partitions in the table
  -partition-size         <int>    partition size in mb
  -prefill-count          <int>    amount of rows written before the workload starts

  -read-rps               <int>    read RPS, an upper bound for all read processes
  -read-timeout           <int>    read timeout in milliseconds
  -read-forks             <int>    amount of read processes
  -write-rps              <int>    write RPS, an upper bound for all write processes
  -write-timeout          <int>    write timeout in milliseconds
  -write-forks            <int>    amount of write processes

  -time                   <int>    workload duration in seconds
  -report-period          <int>    metrics push period in milliseconds
  -shutdown-time          <int>    graceful shutdown time in seconds
  -otlp-endpoint          <string> OTLP metrics endpoint
```

The endpoint and the database may also be passed positionally: `php application.php run grpc://localhost:2136 /local`.

## What's inside

PHP has no threads, so `run` forks a set of processes: `readForks` read processes, `writeForks` write processes and
one metrics process. Workers send every finished operation to the metrics process over a System V message queue,
the metrics process aggregates the events and pushes the metrics every second.

The table has these fields:

- `hash Uint64 Digest::NumericHash(id)`
- `id Uint64`
- `payload_double Double`
- `payload_hash Uint64`
- `payload_str UTF8`
- `payload_timestamp Timestamp`

Primary key: `("hash", "id")`

## Collected metrics

Metrics are pushed as OTLP/JSON to the Prometheus OTLP receiver (it accepts both protobuf and JSON, and JSON keeps the
workload free of a protobuf dependency conflicting with the one pinned by the SDK). Every data point carries the `ref`
label, which is how the report tells the current revision from the baseline one:

- `sdk_operations_total{ref, operation_type, operation_status}` - finished operations
- `sdk_retry_attempts_total{ref, operation_type, operation_status}` - attempts, including the first one
- `sdk_operation_latency_p50_seconds{ref, operation_type, operation_status}` - latency percentile of the last window
- `sdk_operation_latency_p95_seconds{...}`
- `sdk_operation_latency_p99_seconds{...}`
- `sdk_errors_total{ref, operation_type, operation_status, error_name}` - errors, both retried and final

## Running locally

The workload image is built from the repository root, so that it is built against the SDK sources of the checked out
revision:

```bash
docker build -t ydb-app-current -f slo-workload/Dockerfile .
```

```bash
docker run --rm --network host \
  -e YDB_CONNECTION_STRING=grpc://localhost:2136/local \
  -e WORKLOAD_DURATION=60 \
  ydb-app-current
```

Without `OTEL_EXPORTER_OTLP_*` the workload runs without pushing any metrics, which is enough to check that the
workload itself works.
