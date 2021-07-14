<?php

namespace YandexCloud\Ydb\Jwt\Signer;

use Lcobucci\JWT\Signer\Key as SignerKey;
use Lcobucci\JWT\Signer\Rsa as SignerRsa;

use phpseclib\Crypt\RSA as LegacyRSA;
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
        $keyContent = $key->getContent();

        if (class_exists(LegacyRSA::class))
        {
            $rsa = new LegacyRSA;
            $rsa->loadKey($keyContent);
            $rsa->setHash('sha256');
            $rsa->setMGFHash('sha256');
            $rsa->setSignatureMode(LegacyRSA::SIGNATURE_PSS);

            return $rsa->sign($payload);
        }
        else
        {
            $rsa = PublicKeyLoader::load($keyContent);
            $rsa->withPadding(RSA::SIGNATURE_PSS);

            return $rsa->sign($payload);
        }
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
