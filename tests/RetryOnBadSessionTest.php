<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Ydb;

class SessionManager extends \YdbPlatform\Ydb\Session{
    public static function setSessionId(\YdbPlatform\Ydb\Session $session, string $id){
        $session->session_id = $id;
        return $session;
    }
    public static function getSessionId(\YdbPlatform\Ydb\Session $session){
        return $session->session_id;
    }
}

class RetryOnBadSessionTest extends TestCase
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
        $session = $table->session();
        $oldSessionId = SessionManager::getSessionId($session);
        $session->delete();
        $session = $table->session();
        SessionManager::setSessionId($session,$oldSessionId);
        print_r($session->query('select 1 as res'));
        self::assertEquals(
            1,
            $session->query('select 1 as res')->rows()[0]['res']
        );
    }
}
