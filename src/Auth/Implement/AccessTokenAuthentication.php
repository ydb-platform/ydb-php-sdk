<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\TokenInfo;

class AccessTokenAuthentication extends \YdbPlatform\Ydb\Auth\Auth
{
    /**
     * @var string
     */
    protected $access_token;

    public function __construct(string $access_token)
    {
        $this->access_token = $access_token;
    }

    public function getTokenInfo(): TokenInfo
    {
        return new TokenInfo($this->access_token, time()+24*60*60);
    }

    public function getName(): string
    {
        return 'Access token';
    }
}
