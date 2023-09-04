<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\Auth;
use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\AuthService;

class StaticAuthentication extends Auth
{
    protected $user;
    protected $password;
    protected $token;
    /**
     * @var AuthService
     */
    protected $auth;

    public function __construct(string $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function getTokenInfo(): TokenInfo
    {
        $this->token = $this->auth->getToken($this->user, $this->password);
        $expiresIn = 12*60*60;
        $ratio = $this->getRefreshTokenRatio();

        return new TokenInfo($this->token, time()+$expiresIn, $ratio);
    }

    public function getName(): string
    {
        return "Static";
    }

    public function setAuthService(AuthService $auth){
        $this->auth = $auth;
    }
}
