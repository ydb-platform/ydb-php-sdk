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
     * Drops any object-valued property (logger, StaticAuthentication's
     * nested Ydb instance, etc.) before serialize() - none of them are part
     * of a config's identity, and PSR loggers commonly aren't serializable
     * at all. See ydb-platform/ydb-php-sdk#143.
     */
    public function __sleep()
    {
        $properties = [];

        foreach ((new \ReflectionObject($this))->getProperties() as $property)
        {
            if ($property->isStatic())
            {
                continue;
            }

            $property->setAccessible(true);

            // ReflectionProperty::isInitialized() only exists from PHP 7.4 - guard it so
            // this still runs on this SDK's stated PHP 7.2 minimum. No property in this
            // hierarchy is a typed property under <7.4 anyway (that syntax wouldn't parse).
            if ((method_exists($property, 'isInitialized') && !$property->isInitialized($this))
                || is_object($property->getValue($this)))
            {
                continue;
            }

            $properties[] = $property->getName();
        }

        return $properties;
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
