<?php

namespace YdbPlatform\Ydb\Auth;

class TokenInfo
{
    /**
     * @var string
     */
    protected $token;
    /**
     * @var int
     */
    protected $expiresAt;

    public function __construct(string $token, int $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
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
}
