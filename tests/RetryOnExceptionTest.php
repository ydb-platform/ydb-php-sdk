<?php

namespace YdbPlatform\Ydb\Test;
error_reporting(E_ALL^E_DEPRECATED);
use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Table;
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

class RetryOnExceptionTest extends TestCase
{
    /**
     * @var string
     */
    private $oldSessionId;

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

        $ydb = new Ydb($config, new SimpleStdLogger(7));
        $table = $ydb->table();

        $session = $table->createSession();
        $this->oldSessionId = SessionManager::getSessionId($session);
        $session->delete();

        $this->retryTest($table);

    }


    private function retryTest(Table $table)
    {
        $i = 0;
        $table->retryTransaction(function (Session $session) use ($table, &$i){
            $i++;
            if($i==1) {
                $newSessionId = SessionManager::getSessionId($session);
                SessionManager::setSessionId($session, $this->oldSessionId);
                $table->syncSession($newSessionId);
            }
            $tres = $session->query('select 1 as res')->rows()[0]['res'];
            self::assertEquals(
                1,
                $tres
            );
        }, true, new RetryParams(2000));
    }
}
