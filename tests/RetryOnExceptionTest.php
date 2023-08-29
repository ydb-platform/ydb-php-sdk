<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Exceptions\Grpc\ResourceExhaustedException;
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

    public function testRetryOnExceptionInRetry(){

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
        $this->oldSessionId = SessionManager::getSessionId($session);
        $session->delete();

        $this->retryTest($table);

    }


    private function retryTest(Table $table)
    {
        $i = 0;
        $table->retrySession(function (Session $session) use (&$i){
            $i++;
            if($i==1)SessionManager::setSessionId($session, $this->oldSessionId);
            $tres = $session->query('select 1 as res')->rows()[0]['res'];
            self::assertEquals(
                1,
                $tres
            );
        }, true, new RetryParams(2000));
        $i = 0;
        $table->retryTransaction(function (Session $session) use (&$i) {
            if($i == 0){
                throw new ResourceExhaustedException('Test exception');
            }
            self::assertEquals(5, $i);
        }, null, null, [
            'idempotent' => true,
            'callback_on_error' => function (\Exception $exception) use (&$i) {$i=5;}
        ]);
    }
}
