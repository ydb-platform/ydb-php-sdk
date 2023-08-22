<?php

namespace YdbPlatform\Ydb\Auth;

use DateTime;
use YdbPlatform\Ydb\Iam;

abstract class Auth
{
    public abstract function getTokenInfo(): TokenInfo;

    public abstract function getName(): string;

    protected $logger;

    protected $refreshTokenRatio;

    public function logger(){
        return $this->logger;
    }

    public function setLogger($logger){
        $this->logger = $logger;
    }

    /**
     * @return float
     */
    public function getRefreshTokenRatio(): float
    {
        return $this->refreshTokenRatio;
    }

    /**
     * @param float $refreshTokenRatio
     */
    public function setRefreshTokenRatio($refreshTokenRatio): void
    {
        if($refreshTokenRatio<=0||$refreshTokenRatio>=1){
            throw new \Exception("Refresh token ratio. Expected number between 0 and 1.");
        }
        $this->refreshTokenRatio = $refreshTokenRatio;
    }

    /**
     * @param string $expiresAt
     * @return int
     */
    protected function convertExpiresAt($expiresAt)
    {
        if (is_int($expiresAt)) {
            return $expiresAt;
        }

        $time = time() + 60 * 60 * Iam::DEFAULT_TOKEN_EXPIRES_AT;
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d+)?(.*)$/', $expiresAt, $matches)) {
            $time = new DateTime($matches[1] . $matches[2]);
            $time = (int)$time->format('U');
        }
        return $time;
    }
}
