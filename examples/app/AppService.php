<?php

namespace App;

use YdbPlatform\Ydb\Ydb;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class AppService
{
    const DEFAULT_ENDPOINT = 'ydb.serverless.yandexcloud.net:2135';

    /**
     * @var array
     */
    protected $config = [
        'db' => [
            'database'    => null,
            'endpoint'    => null,
            'discovery'   => false,
            'iam_config'  => [
                'use_metadata'       => false,
                'key_id'             => null,
                'service_account_id' => null,
                'private_key_file'   => null,
                'service_file'       => null,
                'oauth_token'        => null,
                'root_cert_file'     => null,
                'temp_dir'           => null,
            ],
        ],
        'use_logger' => false,
    ];

    public function __construct()
    {
        $this->config['db']['database']  = $this->getEnv('DB_DATABASE', null);
        $this->config['db']['endpoint']  = $this->getEnv('DB_ENDPOINT', static::DEFAULT_ENDPOINT);
        $this->config['db']['discovery'] = $this->getEnv('DB_DISCOVERY', false);

        $this->config['db']['iam_config'] = [
//            'use_metadata'       => $this->getEnv('USE_METADATA', false),
            'key_id'             => $this->getEnv('SA_ACCESS_KEY_ID', null),
            'service_account_id' => $this->getEnv('SA_ID', null),
            'private_key_file'   => $this->getEnv('SA_PRIVATE_KEY_FILE', null),
            'service_file'       => $this->getEnv('SA_SERVICE_FILE', null),
            'oauth_token'        => $this->getEnv('DB_OAUTH_TOKEN', null),
            'root_cert_file'     => $this->getEnv('YDB_SSL_ROOT_CERTIFICATES_FILE', null),
            'anonymous'          => $this->getEnv('YDB_ANONYMOUS', false),
            'insecure'           => $this->getEnv('YDB_INSECURE', false),
            'temp_dir'           => '/tmp',
        ];

        $this->config['use_logger'] = $this->getEnv('USE_LOGGER', false);
//        print_r($_ENV);
//        print("\n\nconfig:\n");
//        print_r($this->config);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function config($key)
    {
        return $this->config[$key] ?? null;
    }

    /**
     * @return Logger|null
     */
    public function getLogger()
    {
        if ($this->config('use_logger'))
        {
            $logger = new Logger('ydb');
            $logger->pushHandler(new StreamHandler('./logs/ydb-' . date('Y-m-d') . '.log'));
            return $logger;
        }
        return null;
    }

    public function initYdb()
    {
        return new Ydb($this->config('db'), $this->getLogger());
    }

    protected function getEnv($var, $default = null)
    {
        return $_ENV[$var] ?? getenv($var) ?? $default;
    }
}
