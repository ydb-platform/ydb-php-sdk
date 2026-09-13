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

## Options

Every setting of [src/Config.php](src/Config.php) can be overridden with the option listed next to it in
`Config::OPTIONS`. The options are passed through `workload_current_command` / `workload_baseline_command` of the
action, the most useful ones are:

```
  -table-name             <string> table path relative to the database
  -prefill-count          <int>    amount of rows written before the workload starts

  -read-rps               <int>    read RPS, an upper bound for all read processes
  -read-timeout           <int>    read timeout in milliseconds
  -read-forks             <int>    amount of read processes
  -write-rps              <int>    write RPS, an upper bound for all write processes
  -write-timeout          <int>    write timeout in milliseconds
  -write-forks            <int>    amount of write processes

  -duration               <int>    workload duration in seconds
  -report-period          <int>    metrics push period in milliseconds
  -shutdown-time          <int>    graceful shutdown time in seconds
  -otlp-endpoint          <string> OTLP metrics endpoint
```

`-endpoint` with `-database` replace the connection settings of the environment, which is how the workload is run
without the action: `php application.php run -endpoint grpc://localhost:2136 -database /local`.

## What's inside

Data is classes, everything else is plain functions:

| File                                       | What is in it                                                |
|--------------------------------------------|--------------------------------------------------------------|
| [application.php](application.php)         | command line: the options and the phases to run              |
| [src/Config.php](src/Config.php)           | settings: defaults, the environment and the options on top   |
| [src/Connection.php](src/Connection.php)   | endpoint and database path                                   |
| [src/Event.php](src/Event.php)             | what a worker reports about an operation                     |
| [src/EventQueue.php](src/EventQueue.php)   | the message queue the processes talk over                    |
| [src/Metrics.php](src/Metrics.php)         | counting the events into the metrics                         |
| [src/Otlp.php](src/Otlp.php)               | encoding the metrics as OTLP/JSON and pushing them           |
| [src/processes.php](src/processes.php)     | forking and stopping the processes                           |
| [src/ydb.php](src/ydb.php)                 | connecting to the database and the queries                   |
| [src/workload.php](src/workload.php)       | the phases themselves: create, run, cleanup                  |

PHP has no threads, so `run` forks a set of processes: `-read-forks` read processes, `-write-forks` write processes
and one metrics process. Workers send every finished operation to the metrics process over a System V message queue,
the metrics process counts the events and pushes the metrics every second.

The gRPC core does not survive a fork, so the main process never connects to the database: every phase, and every
worker inside `run`, is a separate forked process that connects on its own.

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
