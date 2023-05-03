<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\TokenInfo;

class OAuthTokenAuthentication extends \YdbPlatform\Ydb\Auth\IamAuth
{
    /**
     * @var string
     */
    protected $oauth_token;

    public function __construct(string $oauth_token)
    {
        $this->oauth_token = $oauth_token;
    }

    public function getTokenInfo(): TokenInfo
    {
        $request_data = [
            'yandexPassportOauthToken' => $this->oauth_token,
        ];
        $token = $this->requestToken($request_data);
        return new TokenInfo($token->iamToken, $this->convertExpiresAt($token->expiresAt));
    }

    public function getName(): string
    {
        return 'OAuth token';
    }
}
