<?php

namespace YdbPlatform\Ydb\Auth;

use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Iam;

abstract class IamAuth extends Auth
{
    /**
     * @return mixed|null
     * @throws Exception
     */
    public function requestToken($request_data)
    {
        $this->logger()->info('YDB: Obtaining new IAM token...');

        $curl = curl_init(Iam::IAM_TOKEN_API_URL);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 0,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $result = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status === 200) {
            $token = json_decode($result);

            if (isset($token->iamToken)) {
                $this->logger()->info('YDB: Obtained new IAM token [...' . substr($token->iamToken, -6) . '].');
                return $token;
            } else {
                $this->logger()->error('YDB: Failed to obtain new IAM token', [
                    'status' => $status,
                    'result' => $token,
                ]);
                throw new Exception('Failed to obtain new iamToken: no token was received.');
            }
        } else {
            $this->logger()->error('YDB: Failed to obtain new IAM token', [
                'status' => $status,
                'result' => $result,
            ]);
            throw new Exception('Failed to obtain new iamToken: response status is ' . $status);
        }
    }
}
