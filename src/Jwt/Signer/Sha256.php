<?php

namespace YandexCloud\Ydb\Jwt\Signer;

use Lcobucci\JWT\Signer\Key as SignerKey;
use Lcobucci\JWT\Signer\Rsa as SignerRsa;

use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\PublicKeyLoader;

class Sha256 extends SignerRsa
{
    /**
     * @param string $payload
     * @param SignerKey $key
     * @return string
     */
    public function createHash($payload, SignerKey $key)
    {
        $private = PublicKeyLoader::load($key->getContent());

        $signature = $private
            ->withPadding(RSA::SIGNATURE_PSS)
            ->sign($payload);

        return $signature;
    }

    /**
     * @return string
     */
    public function getAlgorithmId()
    {
        return 'PS256';
    }

    /**
     * @return int
     */
    public function getAlgorithm()
    {
        return OPENSSL_ALGO_SHA256;
    }
}
