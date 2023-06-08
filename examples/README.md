# YDB PHP Basic Example

## Prerequisites

### Docker setup

- Docker

### Native setup

- Install PHP 7.3+
- Install Composer
- Install PHP extensions:
    - grpc
    - bcmatch
    - curl

Bash commands:

```bash
sudo apt install php
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
sudo apt install php-pear
sudo pecl install grpc
sudo apt install php-curl php-bcmath
```


## Installation

Clone this repository.
```bash
git clone git@github.com:ydb-platform/ydb-php-examples.git
cd ydb-php-examples
```

Copy the .env file:
```bash
cp .env.example .env
```

Edit your .env file:
```
# Common YDB settings
DB_ENDPOINT=ydb.serverless.yandexcloud.net:2135
DB_DATABASE=/ru-central1/b1gxxxxxxxxx/etnyyyyyyyyy

YDB_ANONYMOUS=false
YDB_INSECURE=false

# Auto discovery
DB_DISCOVERY=false

# Auth option 1:
# OAuth token authentication
DB_OAUTH_TOKEN=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA

# Auth option 2:
# Private key authentication
SA_PRIVATE_KEY_FILE=./private.key
SA_ACCESS_KEY_ID=ajexxxxxxxxx
SA_ID=ajeyyyyyyyyy

# Auth option 3:
# Service account JSON file authentication
SA_SERVICE_FILE=./sa_name.json

# Auth option 4:
# Metadata URL authentication
USE_METADATA=true

# Root CA file (dedicated server only)
YDB_SSL_ROOT_CERTIFICATES_FILE=./CA.pem

# Logging settings
USE_LOGGER=false
```

To use locally installed YDB:

```
DB_ENDPOINT=localhost:2136
DB_DATABASE=/local

YDB_ANONYMOUS=true
YDB_INSECURE=true
```


### Docker setup

Install and run services

```bash
docker compose up -d
```

Or update dependencies:
```bash
docker compose run --rm ydb-app composer update
```

Run the console application:

```bash
docker compose run --rm ydb-app php console

docker compose run --rm ydb-app php console select1

docker compose run --rm ydb-app php console create my_table
docker compose run --rm ydb-app php console select my_table
```


### Native setup

Install dependencies:
```bash
composer install
```

Or update dependencies:
```bash
composer update
```

Run the console application:
```bash
php console

php console select1

php console create table1
php console select table1
```


## Basic Example

### Docker setup

```bash
docker compose run --rm ydb-app php console basic_example_v1
```

### Native setup

```bash
php console basic_example_v1
```

This will run examples from the `App\Commands\BasicExampleCommand` class:

```
> Create tables:

Table `series` has been created.
Table `seasons` has been created.
Table `episodes` has been created.

> Describe table:

Table `seasons`
+-------------+--------+
| Name        | Type   |
+-------------+--------+
| series_id   | UINT64 |
| season_id   | UINT64 |
| title       | UTF8   |
| first_aired | UINT64 |
| last_aired  | UINT64 |
+-------------+--------+

Primary key: series_id, season_id

> Fill tables with data:

Finished.

> Select simple transaction:

+-----------+----------+--------------+
| series_id | title    | release_date |
+-----------+----------+--------------+
| 1         | IT Crowd | 2006-02-03   |
+-----------+----------+--------------+

> Upsert simple transaction:

Finished.

> Bulk upsert:

Finished.

> Select prepared:

+------------------------+------------+
| Episode title          | Air date   |
+------------------------+------------+
| To Build a Better Beta | 2016-06-05 |
+------------------------+------------+
+------------------------------+------------+
| Episode title                | Air date   |
+------------------------------+------------+
| Bachman's Earnings Over-Ride | 2016-06-12 |
+------------------------------+------------+

> Explicit tcl:

Finished.

> Select prepared:

+---------------+------------+
| Episode title | Air date   |
+---------------+------------+
| TBD           | 2021-05-28 |
+---------------+------------+
```

## Work with SDK

### Driver Initizalization

The driver is responsible for communication between the application and the YDB at the transport level. The driver must exist throughout the life cycle of working with YDB. Before creating a YDB client and establishing a session, you need to initialize the YDB driver. A snippet of application code with driver initialization:

```php
$config = [
    'database' => $_ENV['DB_DATABASE'] ?? null,
    'endpoint' => $_ENV['DB_ENDPOINT'] ?? 'ydb.serverless.yandexcloud.net:2135',
    'iam_config' => [
        'use_metadata'       => $_ENV['USE_METADATA'] ?? false,
        'key_id'             => $_ENV['SA_ACCESS_KEY_ID'] ?? null,
        'service_account_id' => $_ENV['SA_ID'] ?? null,
        'private_key_file'   => $_ENV['SA_PRIVATE_KEY_FILE'] ?? null,
        'service_file'       => $_ENV['SA_SERVICE_FILE'] ?? null,
        'oauth_token'        => $_ENV['DB_OAUTH_TOKEN'] ?? null,
        'root_cert_file'     => $_ENV['YDB_SSL_ROOT_CERTIFICATES_FILE'] ?? null,
        'temp_dir'           => './tmp',
    ],
];

$ydb = new Ydb($config);
```

### Client And Session Initialization

The client is responsible for working with YDB entities. The session contains information about the transactions and prepared statements. A snippet of application code for creating a session:

```php
$session = $ydb->table()->session();
```

### Creating Tables

To create a table use the `createTable` method:

```php
use YdbPlatform\Ydb\YdbTable;

$session->createTable(
    'series',
    YdbTable::make()
        ->addColumn('series_id', 'UINT64')
        ->addColumn('title', 'UTF8')
        ->addColumn('series_info', 'UTF8')
        ->addColumn('release_date', 'UINT64')
        ->primaryKey('series_id')
);

// Alternative syntax:

$session->createTable(
    'series',
    [
        'series_id' => 'UINT64',
        'title' => 'UTF8',
        'series_info' => 'UTF8',
        'release_date' => 'UINT64',
    ],
    'series_id'
);

// The YdbTable::primaryKey method and the third argument of the createTable method can be an array, if you want to create a composite primary key.
```

To retrieve information about the table structure use the `describeTable` method:

```php
$result = $session->describeTable($tableName);

$columns = [];

foreach ($data['columns'] as $column)
{
    echo 'Column name: ' . $column['name'] . PHP_EOL;
    echo 'Column type: ' . $column['type']['optionalType']['item']['typeId'] . PHP_EOL;
}
```

### Processing Queries And Transactions

To execute YQL-queries use a callable in the `transaction` method:

```php
$result = $session->transaction(function($session) {
    return $session->query('
        $format = DateTime::Format("%Y-%m-%d");
        SELECT
            series_id,
            title,
            $format(DateTime::FromSeconds(CAST(release_date AS Uint32))) AS release_date
        FROM series
        WHERE series_id = 1;');
});
echo 'Row count: ' . $result->rowCount() . PHP_EOL;
print_r($result->rows());
```

### Processing Execution Results

To iterate over the execution results use the `foreach` construction:

```php
foreach ($result->rows() as $row)
{
    echo 'Id:' . $row['id'] .  PHP_EOL;
    echo 'Title:' . $row['title'] .  PHP_EOL;
    echo 'Release Date:' . $row['release_date'] .  PHP_EOL;
}
```

### Data Manipulation Requests

```php
$session->transaction(function($session) {
    return $session->query('
        UPSERT INTO episodes (series_id, season_id, episode_id, title)
        VALUES (2, 6, 1, "TBD");');
});
```

### Prepared Statements

```php
$prepared_query = $session->prepare('
    DECLARE $series_id AS Uint64;
    DECLARE $season_id AS Uint64;
    DECLARE $episode_id AS Uint64;

    $format = DateTime::Format("%Y-%m-%d");
    SELECT
        title,
        $format(DateTime::FromSeconds(CAST(air_date AS Uint32))) AS air_date
    FROM episodes
    WHERE series_id = $series_id AND season_id = $season_id AND episode_id = $episode_id;');

$result = $session->transaction(function($session) use ($prepared_query){
    return $prepared_query->execute([
        'series_id' => 2,
        'season_id' => 3,
        'episode_id' => 7,
    ]);
});

foreach ($result->rows() as $row)
{
    echo 'Title:' . $row['title'] .  PHP_EOL;
    echo 'Air Date:' . $row['air_date'] .  PHP_EOL;
}
```

### Explicit Usage of TCL Begin / Commit Calls

```php
$prepared_query = $session->prepare('
    DECLARE $today AS Uint64;
    DECLARE $series_id AS Uint64;
    DECLARE $season_id AS Uint64;
    DECLARE $episode_id AS Uint64;

    UPDATE episodes
    SET air_date = $today
    WHERE series_id = $series_id AND season_id = $season_id AND episode_id = $episode_id;');

$session->beginTransaction();

$today = strtotime('today');

$prepared_query->execute(compact(
    'series_id',
    'season_id',
    'episode_id',
    'today'
));

$session->commitTransaction();
```
