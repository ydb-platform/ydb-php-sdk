<?php

namespace YdbPlatform\Ydb\Auth;

use DateTime;
use YdbPlatform\Ydb\Iam;
use YdbPlatform\Ydb\Logger\LoggerInterface;

abstract class Auth
{
    public abstract function getTokenInfo(): TokenInfo;

    public abstract function getName(): string;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function logger(){
        return $this->logger;
    }

    public function setLogger($logger){
        $this->logger = $logger;
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
