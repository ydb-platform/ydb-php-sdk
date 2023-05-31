<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Ydb;

class PreparedQueryTest extends TestCase
{
    public function test(){
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            // IAM config
            'iam_config'  => [
                'insecure' => true,
            ],
            'credentials' => new AnonymousAuthentication()
        ];
        $ydb = new Ydb($config);
        $table = $ydb->table();

        $session = $table->createSession();

        $prepared_query = $session->prepare('
declare $pk as Int64;
select $pk;');
        $x = 2;
        $result = $session->transaction(function($session) use ($prepared_query, $x){
            return $prepared_query->execute([
                'pk' => $x,
            ]);
        });
        self::assertEquals($x,
            $result->rows()[0]['column0']
        );
    }
}
