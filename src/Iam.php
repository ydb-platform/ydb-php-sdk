<?php

namespace YandexCloud\Ydb;

use DateTime;
use Exception;
use Lcobucci\JWT;
use DateTimeImmutable;
use Grpc\ChannelCredentials;
use Psr\Log\LoggerInterface;
use YandexCloud\Ydb\Jwt\Signer\Sha256;
use YandexCloud\Ydb\Contracts\IamTokenContract;

class Iam implements IamTokenContract
{
    use Traits\LoggerTrait;

    const IAM_TOKEN_API_URL = 'https://iam.api.cloud.yandex.net/iam/v1/tokens';

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
            $this->config = $config;
        }

        $this->initConfig();

        $this->logger = $logger;
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

        if ($this->config('private_key'))
        {
            $token = $this->getJwtToken();

            $request_data = [
                'jwt' => $token->toString(),
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
                $this->logger()->info('YDB: Obtained new IAM token [' . $token->iamToken . '].');
                $this->saveToken($token);
                return $token->iamToken;
            }
            else
            {
                $this->logger()->error('YDB: Failed to obtain new IAM token', [
                    'status' => $status,
                    'result' => $token,
                ]);
                throw new Exception('Failed to obtain new iamToken');
            }
        }
        else
        {
            $this->logger()->error('YDB: Failed to obtain new IAM token', [
                'status' => $status,
                'result' => $result,
            ]);
            throw new Exception('Failed to obtain new iamToken');
        }
    }

    /**
     * @return ChannelCredentials
     */
    public function getCredentials()
    {
        $root_pem_file = $this->config('root_cert_file');

        if ($root_pem_file && is_file($root_pem_file))
        {
            $pem = file_get_contents($root_pem_file);
        }

        return ChannelCredentials::createSsl($pem ?? null);
    }

    /**
     * @return void
     */
    protected function initConfig()
    {
        if (empty($this->config['temp_dir']))
        {
            $this->config['temp_dir'] = sys_get_temp_dir();
        }

        if (!empty($this->config['service_file']) && is_file($this->config['service_file']))
        {
            $service = json_decode(file_get_contents($this->config['service_file']));

            $this->config['key_id'] = $service->id ?? null;
            $this->config['private_key'] = $service->private_key ?? null;
            $this->config['service_account_id'] = $service->service_account_id ?? null;
        }
        else if (!empty($this->config['private_key_file']) && is_file($this->config['private_key_file']))
        {
            $this->config['private_key'] = file_get_contents($this->config['private_key_file']);
        }
    }

    /**
     * @return JWT\Token
     */
    protected function getJwtToken()
    {
        $now = new DateTimeImmutable;

        $key = '';

        if ($this->config('private_key'))
        {
            $key = JWT\Signer\Key\InMemory::plainText($this->config('private_key'));
        }
        else if ($this->config('private_key_file'))
        {
            $key = JWT\Signer\Key\LocalFileReference::file($this->config('private_key_file'));
        }

        $config = JWT\Configuration::forSymmetricSigner(
            new Sha256,
            $key
        );

        $token = $config->builder()
            ->issuedBy($this->config('service_account_id'))
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->permittedFor(static::IAM_TOKEN_API_URL)
            ->withHeader('typ', 'JWT')
            ->withHeader('kid', $this->config('key_id'))
            ->getToken($config->signer(), $config->signingKey());

        return $token;
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
                $this->logger()->info('YDB: Reused IAM token [' . $this->iam_token . '].');
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
        $this->expires_at = (new DateTime($token->expiresAt))->format('U');

        file_put_contents($tokenFile, json_encode([
            'iamToken' => $this->iam_token,
            'expiresAt' => $this->expires_at,
        ]));
    }

}
