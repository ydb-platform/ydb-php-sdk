<?php

namespace YdbPlatform\Ydb;

use DateTime;
use DateTimeImmutable;
use Grpc\ChannelCredentials;
use Psr\Log\LoggerInterface;
use YdbPlatform\Ydb\Contracts\IamTokenContract;

use function filter_var;

class Iam implements IamTokenContract
{
    use Traits\LoggerTrait;

    const IAM_TOKEN_API_URL = 'https://iam.api.cloud.yandex.net/iam/v1/tokens';

    const METADATA_URL = 'http://169.254.169.254/computeMetadata/v1/instance/service-accounts/default/token';

    const DEFAULT_TOKEN_EXPIRES_AT = 2; // hours

    /**
     * @var string
     */
    protected $iam_token;

    /**
     * @var int
     */
    protected $expires_at;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param array $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $config = [], LoggerInterface $logger = null)
    {
        if ($config)
        {
            $this->config = $this->parseConfig($config);
        }

        $this->logger = $logger;

        $this->initConfig();
    }

    /**
     * @param string $key
     * @param string|null $default
     * @return mixed|null
     */
    public function config($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @param bool $force
     * @return string
     * @throws Exception
     */
    public function token($force = false)
    {
        if ($force || !($token = $this->loadToken()))
        {
            $token = $this->newToken();
        }
        return $token;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    public function newToken()
    {
        $this->logger()->info('YDB: Obtaining new IAM token...');

        if ($this->config('use_metadata'))
        {
            return $this->requestTokenFromMetadata();
        }
        else if ($this->config('private_key'))
        {
            $token = $this->getJwtToken();
            $request_data = [
                'jwt' => $token,
            ];
        }
        else
        {
            $request_data = [
                'yandexPassportOauthToken' => $this->config('oauth_token'),
            ];
        }

        $curl = curl_init(static::IAM_TOKEN_API_URL);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 0,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $result = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status === 200)
        {
            $token = json_decode($result);

            if (isset($token->iamToken))
            {
                $this->logger()->info('YDB: Obtained new IAM token [...' . substr($token->iamToken, -6) . '].');
                $this->saveToken($token);
                return $token->iamToken;
            }
            else
            {
                $this->logger()->error('YDB: Failed to obtain new IAM token', [
                    'status' => $status,
                    'result' => $token,
                ]);
                throw new Exception('Failed to obtain new iamToken: no token was received.');
            }
        }
        else
        {
            $this->logger()->error('YDB: Failed to obtain new IAM token', [
                'status' => $status,
                'result' => $result,
            ]);
            throw new Exception('Failed to obtain new iamToken: response status is ' . $status);
        }
    }

    /**
     * @return ChannelCredentials
     */
    public function getCredentials()
    {
        if ($this->config('insecure'))
        {
            return ChannelCredentials::createInsecure();
        }

        $root_pem_file = $this->config('root_cert_file');

        if ($root_pem_file && is_file($root_pem_file))
        {
            $pem = file_get_contents($root_pem_file);
        }

        return ChannelCredentials::createSsl($pem ?? null);
    }

    /**
     * @param array $config
     * @return array
     */
    protected function parseConfig(array $config)
    {
        $parsedConfig = [];

        $stringParams = [
            'temp_dir',
            'root_cert_file',
            'oauth_token',
            'key_id',
            'service_account_id',
            'private_key_file',
            'service_file',
        ];

        foreach ($stringParams as $param)
        {
            $parsedConfig[$param] = (string)($config[$param] ?? '');
        }

        $boolParams = [
            'use_metadata',
            'anonymous',
            'insecure',
        ];

        foreach ($boolParams as $param)
        {
            $parsedConfig[$param] = (
                isset($config[$param])
                && filter_var($config[$param], \FILTER_VALIDATE_BOOLEAN)
            );
        }

        return $parsedConfig;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function initConfig()
    {
        if (!$this->config('temp_dir'))
        {
            $this->config['temp_dir'] = sys_get_temp_dir();
        }

        if ($this->config('anonymous'))
        {
            $this->logger()->info('YDB: Authentication method: Anonymous');
        }
        else if ($this->config('use_metadata'))
        {
            $this->logger()->info('YDB: Authentication method: Metadata URL');
        }
        else if ($serviceFile = $this->config('service_file'))
        {
            if (is_file($serviceFile))
            {
                $this->logger()->info('YDB: Authentication method: SA JSON file');

                $service = json_decode(file_get_contents($serviceFile));

                if (is_object($service)
                    && isset($service->id)
                    && isset($service->private_key)
                    && isset($service->service_account_id))
                {
                    $this->config['key_id'] = $service->id;
                    $this->config['private_key'] = $service->private_key;
                    $this->config['service_account_id'] = $service->service_account_id;
                }
                else
                {
                    throw new Exception('Service file [' . $serviceFile . '] is broken.');
                }
            }
            else
            {
                throw new Exception('Service file [' . $serviceFile . '] is missing.');
            }
        }
        else if ($privateKeyFile = $this->config('private_key_file'))
        {
            $this->logger()->info('YDB: Authentication method: Private key');

            if (is_file($privateKeyFile))
            {
                $this->config['private_key'] = file_get_contents($privateKeyFile);
            }
            else
            {
                throw new Exception('Private key [' . $privateKeyFile . '] is missing.');
            }
        }
        else if ($this->config('oauth_token'))
        {
            $this->logger()->info('YDB: Authentication method: OAuth token');
        }
        else
        {
            throw new Exception('No authentication method is used.');
        }
    }

    /**
     * @return string
     */
    protected function getJwtToken()
    {
        $now = new DateTimeImmutable;

        $token = (new Jwt\Jwt($this->config('private_key'), $this->config('key_id')))
            ->issuedBy($this->config('service_account_id'))
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->permittedFor(static::IAM_TOKEN_API_URL)
            ->getToken();

        return $token;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    protected function requestTokenFromMetadata()
    {
        $curl = curl_init(static::METADATA_URL);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Metadata-Flavor:Google',
            ],
        ]);

        $result = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status === 200)
        {
            $rawToken = json_decode($result);

            if (isset($rawToken->access_token))
            {
                $token = (object)[
                    'iamToken' => $rawToken->access_token,
                ];
                if (isset($rawToken->expires_in))
                {
                    $token->expiresAt = time() + $rawToken->expires_in;
                }
                $this->logger()->info('YDB: Obtained new IAM token from Metadata [...' . substr($token->iamToken, -6) . '].');
                $this->saveToken($token);
                return $token->iamToken;
            }
            else
            {
                $this->logger()->error('YDB: Failed to obtain new IAM token from Metadata', [
                    'status' => $status,
                    'result' => $result,
                ]);
                throw new Exception('Failed to obtain new iamToken from Metadata: no token was received.');
            }
        }
        else
        {
            $this->logger()->error('YDB: Failed to obtain new IAM token from Metadata', [
                'status' => $status,
                'result' => $result,
            ]);
            throw new Exception('Failed to obtain new iamToken from Metadata: response status is ' . $status);
        }


    }

    /**
     * @var string
     */
    protected $token_temp_file;

    /**
     * @return string
     */
    protected function getTokenTempFile()
    {
        if (empty($this->token_temp_file))
        {
            $temp_dir = $this->config('temp_dir');

            if (!is_dir($temp_dir))
            {
                mkdir($temp_dir, 0600, true);
            }

            $this->token_temp_file = $temp_dir . '/ydb-iam-' . md5(serialize($this->config)) . '.json';
        }

        return $this->token_temp_file;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    protected function loadToken()
    {
        if ($this->iam_token)
        {
            if ($this->expires_at > time())
            {
                return $this->iam_token;
            }
            return $this->newToken();
        }

        return $this->loadTokenFromFile();
    }

    /**
     * @return null
     */
    protected function loadTokenFromFile()
    {
        $tokenFile = $this->getTokenTempFile();

        if (is_file($tokenFile))
        {
            $token = json_decode(file_get_contents($tokenFile));

            if (isset($token->iamToken) && $token->expiresAt > time())
            {
                $this->iam_token = $token->iamToken;
                $this->expires_at = $token->expiresAt;
                $this->logger()->info('YDB: Reused IAM token [...' . substr($this->iam_token, -6) . '].');
                return $token->iamToken;
            }
        }

        return null;
    }

    /**
     * @param object $token
     * @throws Exception
     */
    protected function saveToken($token)
    {
        $tokenFile = $this->getTokenTempFile();

        $this->iam_token = $token->iamToken;
        $this->expires_at = $this->convertExpiresAt($token->expiresAt ?? '');

        file_put_contents($tokenFile, json_encode([
            'iamToken' => $this->iam_token,
            'expiresAt' => $this->expires_at,
        ]));
    }

    /**
     * @param string $expiresAt
     * @return int
     */
    protected function convertExpiresAt($expiresAt)
    {
        if (is_int($expiresAt))
        {
            return $expiresAt;
        }

        $time = time() + 60 * 60 * static::DEFAULT_TOKEN_EXPIRES_AT;
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d+)?(.*)$/', $expiresAt, $matches))
        {
            $time = new DateTime($matches[1] . $matches[2]);
            $time = (int)$time->format('U');
        }
        return $time;
    }

}
