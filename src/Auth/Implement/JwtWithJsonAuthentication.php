<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Exception;

class JwtWithJsonAuthentication extends \YdbPlatform\Ydb\Auth\JwtAuth
{

    public function __construct(string $serviceFile)
    {
        if (is_file($serviceFile)) {
            $service = json_decode(file_get_contents($serviceFile));

            if (is_object($service)
                && isset($service->id)
                && isset($service->private_key)
                && isset($service->service_account_id)) {
                $this->key_id = $service->id;
                $this->private_key = $service->private_key;
                $this->service_account_id = $service->service_account_id;
            } else {
                throw new Exception('Service file [' . $serviceFile . '] is broken.');
            }
        } else {
            throw new Exception('Service file [' . $serviceFile . '] is missing.');
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
        return "SA JSON key";
    }
}
