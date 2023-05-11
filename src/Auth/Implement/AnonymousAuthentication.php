<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\TokenInfo;

class AnonymousAuthentication extends \YdbPlatform\Ydb\Auth\Auth
{

    public function __construct()
    {
    }

    public function getTokenInfo(): TokenInfo
    {
        return new TokenInfo("", time()+24*3600);
    }

    public function getName(): string
    {
        return 'Anonymous';
    }
}
