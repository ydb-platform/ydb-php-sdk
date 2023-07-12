YDB PHP SDK provides access to [YDB](https://ydb.tech/) from PHP code.

YDB is a open-source distributed fault-tolerant DBMS with high availability and scalability, strict consistency and ACID. An SQL dialect – YQL – is used for queries.

YDB is available in several modes:

- On-prem installation (is not supported by this SDK yet);
- Serverless computing mode in YC (only performed operations are paid);
- Dedicated instance mode in YC (dedicated computing resources are paid).

# Documentation

[https://ydb.tech/docs/](https://ydb.tech/docs/)

# Installation

The recommended method of installing is Composer.

Run the following:

```bash
composer require ydb-platform/ydb-php-sdk
```

# Connection

First, create a database using [Yandex Cloud Console](https://cloud.yandex.com/docs/ydb/quickstart/create-db).

YDB supports the following authentication methods:

- Access token
- OAuth token
- JWT + private key
- JWT + JSON file
- Metadata URL
- Anonymous

## Access token

Use your access token:

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [

    // Database path
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',

    // Database endpoint
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'root_cert_file' => './CA.pem', // Root CA file (dedicated server only!)

        // Access token authentication
        'access_token'    => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
    ],
];

$ydb = new Ydb($config);
```
or:
```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\AccessTokenAuthentication;

$config = [

    // Database path
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',

    // Database endpoint
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'root_cert_file' => './CA.pem', // Root CA file (dedicated server only!)
    ],
    
    'credentials' => new AccessTokenAuthentication('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')
];

$ydb = new Ydb($config);
```
## OAuth token

You should obtain [a new OAuth token](https://cloud.yandex.com/docs/iam/concepts/authorization/oauth-token).

Use your OAuth token:

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [

    // Database path
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',

    // Database endpoint
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'temp_dir'       => './tmp', // Temp directory
        'root_cert_file' => './CA.pem', // Root CA file (dedicated server only!)

        // OAuth token authentication
        'oauth_token'    => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
    ],
];

$ydb = new Ydb($config);
```

or
```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\OAuthTokenAuthentication;

$config = [

    // Database path
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',

    // Database endpoint
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'temp_dir'       => './tmp', // Temp directory
        'root_cert_file' => './CA.pem', // Root CA file (dedicated server only!)
    ],
    
    'credentials' => new OAuthTokenAuthentication('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')
];

$ydb = new Ydb($config);
```
## JWT + private key

Create [a service account](https://cloud.yandex.com/docs/iam/operations/sa/create) with the `editor` role, then create a private key. Also you need a key ID and a service account ID.

Connect to your database:

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',
    'discovery'   => false,
    'iam_config'  => [
        'temp_dir'           => './tmp', // Temp directory
        'root_cert_file'     => './CA.pem', // Root CA file (dedicated server only!)

        // Private key authentication
        'key_id'             => 'ajexxxxxxxxx',
        'service_account_id' => 'ajeyyyyyyyyy',
        'private_key_file'   => './private.key',
    ],
];

$ydb = new Ydb($config);
```

or
```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\JwtWithPrivateKeyAuthentication;

$config = [
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',
    'discovery'   => false,
    'iam_config'  => [
        'temp_dir'           => './tmp', // Temp directory
        'root_cert_file'     => './CA.pem', // Root CA file (dedicated server only!)

        // Private key authentication
        'key_id'             => 'ajexxxxxxxxx',
        'service_account_id' => 'ajeyyyyyyyyy',
        'private_key_file'   => './private.key',
    ],
    
    'credentials' => new JwtWithPrivateKeyAuthentication(
        "ajexxxxxxxxx","ajeyyyyyyyyy",'./private.key')
        
];

$ydb = new Ydb($config);
```
## JWT + JSON file

Create [a service account](https://cloud.yandex.com/docs/iam/operations/sa/create) with the `editor` role.

Create a service account [JSON file](https://cloud.yandex.com/docs/iam/operations/iam-token/create-for-sa#via-cli), save it in your project as `sa_name.json`.

Connect to your database:

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',
    'discovery'   => false,
    'iam_config'  => [
        'temp_dir'       => './tmp', // Temp directory
        'root_cert_file' => './CA.pem', // Root CA file (dedicated server only!)

        // Service account JSON file authentication
        'service_file'   => './sa_name.json',
    ],
];

$ydb = new Ydb($config);
```

or:
```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\JwtWithJsonAuthentication;

$config = [
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',
    'discovery'   => false,
    'iam_config'  => [
        'temp_dir'       => './tmp', // Temp directory
        'root_cert_file' => './CA.pem', // Root CA file (dedicated server only!)
    ],
            
    'credentials' => new JwtWithJsonAuthentication('./jwtjson.json')
];

$ydb = new Ydb($config);
```
## Metadata URL

When you deploy a project to VM or function at Yandex.Cloud, you are able to connect to the database using [Metadata URL](https://cloud.yandex.com/docs/compute/operations/vm-connect/auth-inside-vm). Before you start, you should link your service account to an existing or new VM or function.

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [

    // Database path
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',

    // Database endpoint
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'temp_dir'     => './tmp', // Temp directory
        'use_metadata' => true,
    ],
];

$ydb = new Ydb($config);
```
or
```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\MetadataAuthentication;

$config = [

    // Database path
    'database'    => '/ru-central1/b1glxxxxxxxxxxxxxxxx/etn0xxxxxxxxxxxxxxxx',

    // Database endpoint
    'endpoint'    => 'ydb.serverless.yandexcloud.net:2135',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'temp_dir'     => './tmp', // Temp directory
    ],
    'credentials' => new MetadataAuthentication()
];

$ydb = new Ydb($config);
```

## Anonymous

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [

    // Database path
    'database'    => '/local',

    // Database endpoint
    'endpoint'    => 'localhost:2136',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'anonymous' => true,
        'insecure' => true,
    ],
];

$ydb = new Ydb($config);
```

or:
```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;

$config = [

    // Database path
    'database'    => '/local',

    // Database endpoint
    'endpoint'    => 'localhost:2136',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'insecure' => true,
    ],
    
    'credentials' => new AnonymousAuthentication()
];

$ydb = new Ydb($config);
```

## Determined by environment variables

```php
<?php

use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Auth\Implement\EnvironCredentials;

$config = [

    // Database path
    'database'    => '/local',

    // Database endpoint
    'endpoint'    => 'localhost:2136',

    // Auto discovery (dedicated server only)
    'discovery'   => false,

    // IAM config
    'iam_config'  => [
        'insecure' => true,
    ],
    
    'credentials' => new EnvironCredentials()
];

$ydb = new Ydb($config);
```

The following algorithm that is the same for YDB-PHP-SDK applies:

1. If the value of the `YDB_SERVICE_ACCOUNT_KEY_FILE_CREDENTIALS` environment variable is set, the **System Account Key** authentication mode is used and the key is taken from the file whose name is specified in this variable.
2. Otherwise, if the value of the `YDB_ANONYMOUS_CREDENTIALS` environment variable is set to 1, the anonymous authentication mode is used.
3. Otherwise, if the value of the `YDB_METADATA_CREDENTIALS` environment variable is set to 1, the **Metadata** authentication mode is used.
4. Otherwise, if the value of the `YDB_ACCESS_TOKEN_CREDENTIALS` environment variable is set, the **Access token** authentication mode is used, where the this variable value is passed.
5. Otherwise, the **Metadata** authentication mode is used.

# Usage

You should initialize a session from the Table service to start querying with retry.

```php
<?php

use YdbPlatform\Ydb\Ydb;

$config = [
    // ...
];

$ydb = new Ydb($config);

// obtaining the Table service
$table = $ydb->table();

$result = $table->retryTransaction(function(Session $session){
    // making a query
    return $session->query('select * from `users` limit 10;');
}, true);

$users_count = $result->rowCount();
$users = $result->rows();

$columns = $result->columns();

```

As soon as your script is finished, the session will be destroyed.

## Customizing queries

Normally, a regular query through the `query()` method is sufficient, but in exceptional cases, you may need to fine-tune the query settings. You could do it using the query builder:

```php
<?php

$result = $table->retryTransaction(function(Session $session){

    // creating a new query builder instance
    $query = $session->newQuery('select * from `users` limit 10;');
    
    // a setting to keep in cache
    $query->keepInCache();
    
    // a setting to begin a transaction with the given mode
    $query->beginTx('stale');
    
    return $query->execute();
}, true);
```

Methods of the query builder:

- `keepInCache(bool $value)` - keep in cache (default: `true`)
- `collectStats(int $value)` - collect stats (default: 1)
- `parameters(array $parameters)` - parameters
- `operationParams(\Ydb\Operations\OperationParams $operation_params)` - operation params
- `beginTx(string $mode)` - begin a transaction with the given [mode](https://ydb.tech/en/docs/concepts/transactions):
    - stale
    - online
    - online_inconsistent
    - serializable
- `txControl(\Ydb\Table\TransactionControl $tx_control)` - transaction control with custom settings

You can chain these methods for convenience.
