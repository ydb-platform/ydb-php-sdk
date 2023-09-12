<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\IamAuth;
use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Auth\UseConfigInterface;
use YdbPlatform\Ydb\Jwt\Jwt;
use YdbPlatform\Ydb\Ydb;

class StaticAuthentication extends IamAuth implements UseConfigInterface
{
    protected $user;
    protected $password;
    protected $token;
    /**
     * @var Ydb
     */
    protected $ydb;

    public function __construct(string $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function getTokenInfo(): TokenInfo
    {
        $this->token = $this->ydb->auth()->getToken($this->user, $this->password);
        $jwtData = Jwt::decodeHeaderAndPayload($this->token);
        $expiresIn = $this->convertExpiresAt($jwtData['payload']['exp']);
        $ratio = $this->getRefreshTokenRatio();

        return new TokenInfo($this->token, $expiresIn, $ratio);
    }

    public function getName(): string
    {
        return "Static";
    }

    public function setYdbConnectionConfig(array $config)
    {
        unset($config['credentials']);
        $config['credentials'] = new AnonymousAuthentication();
        $this->ydb = new Ydb($config, $this->logger);
    }
}
