<?php

namespace YdbPlatform\Ydb\Jwt\Signer;

use Lcobucci\JWT\Signer\Key as SignerKey;
use Lcobucci\JWT\Signer\OpenSSL;

use phpseclib\Crypt\RSA as LegacyRSA;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\PublicKeyLoader;

class Sha256 extends OpenSSL
{
    /**
     * @param string $payload
     * @param SignerKey $key
     * @return string
     */
    final public function sign(string $payload, SignerKey $key): string
    {
        return $this->createHash($payload, $key);
    }

    /**
     * @param string $expected
     * @param string $payload
     * @param SignerKey $key
     * @return bool
     */
    final public function verify(string $expected, string $payload, SignerKey $key): bool
    {
        return $this->verifySignature($expected, $payload, $key->contents());
    }

    /**
     * @return int
     */
    final public function keyType(): int
    {
        return OPENSSL_KEYTYPE_RSA;
    }

    /**
     * @param string $payload
     * @param SignerKey $key
     * @return string
     */
    public function createHash($payload, SignerKey $key): string
    {
        $keyContent = $key->contents();

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
    public function algorithmId(): string
    {
        return 'PS256';
    }

    /**
     * @return int
     */
    public function algorithm(): int
    {
        return OPENSSL_ALGO_SHA256;
    }
}
