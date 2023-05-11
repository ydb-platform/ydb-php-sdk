<?php

namespace YdbPlatform\Ydb\Auth;

use DateTimeImmutable;
use YdbPlatform\Ydb\Iam;
use YdbPlatform\Ydb\Jwt\Jwt;

abstract class JwtAuth extends IamAuth
{

    protected $key_id;
    protected $private_key;
    protected $service_account_id;

    /**
     * @return string
     */
    protected function getJwtToken()
    {
        $now = new DateTimeImmutable;

        $token = (new Jwt($this->private_key, $this->key_id))
            ->issuedBy($this->service_account_id)
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->permittedFor(Iam::IAM_TOKEN_API_URL)
            ->getToken();
        return $token;
    }

}
