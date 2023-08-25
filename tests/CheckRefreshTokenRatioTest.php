<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class CheckRefreshTokenRatioTest extends TestCase
{
    public function testRefreshTokenRatio()
    {
        $awaitException = [0, 1];
        $awaitNormal = [0.05, 0.9];
        $config = [

            // Database path
            'database' => '/local',

            // Database endpoint
            'endpoint' => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery' => false,

            // IAM config
            'iam_config' => [
                'anonymous' => true,
                'insecure' => true
            ],
        ];

        $ydb = new Ydb($config);
        self::assertEquals(0.1,
            $ydb->iam()->config('credentials')->getRefreshTokenRatio()
        );

        foreach ($awaitNormal as $ratio){
            $config['iam_config']['refresh_token_ratio'] = $ratio;
            $ydb = new Ydb($config);
            self::assertEquals($ratio,
                $ydb->iam()->config('credentials')->getRefreshTokenRatio()
            );
        }

        foreach ($awaitException as $ratio) {
            $config['iam_config']['refresh_token_ratio'] = $ratio;
            $this->expectExceptionObject(new \Exception("Refresh token ratio. Expected number between 0 and 1."));

            $ydb = new Ydb($config);
            $ydb->iam()->config('credentials')->getRefreshTokenRatio();
        }

    }
}
