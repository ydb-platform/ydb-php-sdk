<?php

namespace YdbPlatform\Ydb\Auth;

use YdbPlatform\Ydb\Auth\Implement\AccessTokenAuthentication;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Auth\Implement\JwtWithJsonAuthentication;
use YdbPlatform\Ydb\Auth\Implement\MetadataAuthentication;

class EnvironCredentials extends \YdbPlatform\Ydb\Auth\Auth
{
    /**
     * @var Auth
     */
    protected $auth;
    public function __construct()
    {
        if ($jsonfile = getenv("YDB_SERVICE_ACCOUNT_KEY_FILE_CREDENTIALS")){
            $this->auth = new JwtWithJsonAuthentication($jsonfile);
        } elseif (getenv("YDB_ANONYMOUS_CREDENTIALS") == 1){
            $this->auth = new AnonymousAuthentication();
        } elseif (getenv("YDB_METADATA_CREDENTIALS") == 1){
            $this->auth = new MetadataAuthentication();
        } elseif ($token = getenv("YDB_ACCESS_TOKEN_CREDENTIALS")){
            $this->auth = new AccessTokenAuthentication($token);
        } else {
            $this->auth = new MetadataAuthentication();
        }
    }

    public function getTokenInfo(): TokenInfo
    {
        return $this->auth->getTokenInfo();
    }

    public function getName(): string
    {
        return $this->auth->getName();
    }

    public function logger()
    {
        return $this->auth->logger();
    }

    public function setLogger($logger)
    {
        $this->auth->setLogger($logger);
    }
}
