<?php

namespace YdbPlatform\Ydb\Auth\Implement;

use YdbPlatform\Ydb\Auth\TokenInfo;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Iam;

class MetadataAuthentication extends \YdbPlatform\Ydb\Auth\Auth
{

    public function getTokenInfo(): TokenInfo
    {
        $token = $this->requestTokenFromMetadata();
        return new TokenInfo($token->iamToken, $this->convertExpiresAt($token->expiresAt));
    }

    public function getName(): string
    {
        return "Metadata URL";
    }

    /**
     * @return string|null
     * @throws Exception
     */
    protected function requestTokenFromMetadata()
    {
        $curl = curl_init(Iam::METADATA_URL);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Metadata-Flavor:Google',
            ],
        ]);

        $result = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status === 200)
        {
            $rawToken = json_decode($result);

            if (isset($rawToken->access_token))
            {
                $token = (object)[
                    'iamToken' => $rawToken->access_token,
                ];
                if (isset($rawToken->expires_in))
                {
                    $token->expiresAt = time() + $rawToken->expires_in;
                }
                $this->logger()->info('YDB: Obtained new IAM token from Metadata [...' . substr($token->iamToken, -6) . '].');
                return $token;
            }
            else
            {
                $this->logger()->error('YDB: Failed to obtain new IAM token from Metadata', [
                    'status' => $status,
                    'result' => $result,
                ]);
                throw new Exception('Failed to obtain new iamToken from Metadata: no token was received.');
            }
        }
        else
        {
            $this->logger()->error('YDB: Failed to obtain new IAM token from Metadata', [
                'status' => $status,
                'result' => $result,
            ]);
            throw new Exception('Failed to obtain new iamToken from Metadata: response status is ' . $status);
        }
    }
}
