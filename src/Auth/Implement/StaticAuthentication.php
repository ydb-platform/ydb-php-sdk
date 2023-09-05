<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\Auth;
use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Auth\UseConfigInterface;
use YdbPlatform\Ydb\AuthService;
use YdbPlatform\Ydb\Ydb;

class StaticAuthentication extends Auth implements UseConfigInterface
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
        $expiresIn = 12*60*60;
        $ratio = $this->getRefreshTokenRatio();

        return new TokenInfo($this->token, time()+$expiresIn, $ratio);
    }

    public function getName(): string
    {
        return "Static";
    }

    public function setYdbConnectionConfig(array $config)
    {
        unset($config['credentials']);
        $config['credentials'] = new AccessTokenAuthentication('');
        $this->ydb = new Ydb($config, $this->logger);
    }
}
