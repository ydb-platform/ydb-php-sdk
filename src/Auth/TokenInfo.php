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

    private $refreshAt;

    public function __construct(string $token, int $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->refreshAt = time() + ceil(0.1*($this->expiresAt-time()));
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
