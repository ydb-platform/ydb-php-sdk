<?php

namespace YdbPlatform\Ydb\Auth;

class TokenInfo
{
    const _PRIVATE_REFRESH_RATIO = 0.1;
    /**
     * @var string
     */
    protected $token;
    /**
     * @var int
     */
    protected $expiresAt;

    private $refreshAt;

    public function __construct(string $token, int $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->refreshAt = time() + round(TokenInfo::_PRIVATE_REFRESH_RATIO*($this->expiresAt-time()),0);
    }

    /**
     * @return int
     */
    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    public function getRefreshAt(): int
    {
        return $this->refreshAt;
    }
}
