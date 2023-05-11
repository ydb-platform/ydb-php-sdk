<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Exception;

class JwtWithPrivateKeyAuthentication extends \YdbPlatform\Ydb\Auth\JwtAuth
{

    public function __construct(string $key_id, string $service_account_id, string $privateKeyFile)
    {
        if (is_file($privateKeyFile))
        {
            $this->key_id = $key_id;
            $this->private_key = file_get_contents($privateKeyFile);
            $this->service_account_id = $service_account_id;
        }
        else
        {
            throw new Exception('Private key [' . $privateKeyFile . '] is missing.');
        }
    }

    public function getTokenInfo(): TokenInfo
    {
        $jwt_token = $this->getJwtToken();
        $request_data = [
            'jwt' => $jwt_token,
        ];
        $token = $this->requestToken($request_data);
        return new TokenInfo($token->iamToken, $this->convertExpiresAt($token->expiresAt));
    }

    public function getName(): string
    {
        return "Private key";
    }
}
