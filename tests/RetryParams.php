<?php

namespace YdbPlatform\Ydb\Test;

use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\Exceptions\Grpc\AbortedException;
use YdbPlatform\Ydb\Exceptions\Grpc\AlreadyExistsException;
use YdbPlatform\Ydb\Exceptions\Grpc\CanceledException;
use YdbPlatform\Ydb\Exceptions\Grpc\DataLossException;
use YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException;
use YdbPlatform\Ydb\Exceptions\Grpc\FailedPreconditionException;
use YdbPlatform\Ydb\Exceptions\Grpc\InternalException;
use YdbPlatform\Ydb\Exceptions\Grpc\InvalidArgumentException;
use YdbPlatform\Ydb\Exceptions\Grpc\NotFoundException;
use YdbPlatform\Ydb\Exceptions\Grpc\OutOfRangeException;
use YdbPlatform\Ydb\Exceptions\Grpc\ResourceExhaustedException;
use YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException;
use YdbPlatform\Ydb\Exceptions\Grpc\UnimplementedException;
use YdbPlatform\Ydb\Exceptions\Grpc\UnknownException;
use YdbPlatform\Ydb\Exceptions\RetryableException;
use YdbPlatform\Ydb\Exceptions\Ydb\BadRequestException;
use YdbPlatform\Ydb\Exceptions\Ydb\InternalErrorException;
use YdbPlatform\Ydb\Exceptions\Ydb\StatusCodeUnspecified;
use YdbPlatform\Ydb\Exceptions\Ydb\UnauthorizedException;
use YdbPlatform\Ydb\Retry\Backoff;
use YdbPlatform\Ydb\Retry\RetryParams;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Table;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\Retry\Retry;

class RetrySubclass extends Retry{
    public function canRetry(Exception $e, bool $idempotent)
    {
        return parent::canRetry($e, $idempotent);
    }
    public function backoffType(string $e): Backoff
    {
        return parent::backoffType($e);
    }
}
class TableSubclass extends Table{

    public function deleteSession(string $exception): bool
    {
        return parent::deleteSession($exception);
    }
}

class RetryTest2 extends \PHPUnit\Framework\TestCase
{
    protected const FAST = 5;
    protected const SLOW = 20;
    protected const BACKOFF_TYPE = [
        0           => "noBackoff",
        self::FAST  => "fastBackoff",
        self::SLOW  => "slowBackoff"
    ];
    protected $errsToCheck = [
        [
            "class"         => DeadlineExceededException::class,
            "deleteSession" => true,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => CanceledException::class,
            "deleteSession" => true,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => UnknownException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => InvalidArgumentException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => NotFoundException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => AlreadyExistsException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => ResourceExhaustedException::class,
            "deleteSession" => false,
            "backoffTime"   => self::SLOW,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
        [
            "class"         => FailedPreconditionException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => AbortedException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
        [
            "class"         => OutOfRangeException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => UnimplementedException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => InternalException::class,
            "deleteSession" => true,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => UnavailableException::class,
            "deleteSession" => true,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => DataLossException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => DataLossException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => StatusCodeUnspecified::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => BadRequestException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => UnauthorizedException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => InternalErrorException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\AbortedException::class,
            "deleteSession" => false,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\UnavailableException::class,
            "deleteSession" => false,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\OverloadedException::class,
            "deleteSession" => false,
            "backoffTime"   => self::SLOW,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\SchemeErrorException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\GenericErrorException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\TimeoutException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\PreconditionFailedException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\AlreadyExistsException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\NotFoundException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\SessionExpiredException::class,
            "deleteSession" => true,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\CancelledException::class,
            "deleteSession" => false,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\UndeterminedException::class,
            "deleteSession" => false,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\UnsupportedException::class,
            "deleteSession" => false,
            "backoffTime"   => 0,
            "retry"         => [
                "idempotent"    => false,
                "nonIdempotent" => false
            ]
        ],
        [
            "class"         => \YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException::class,
            "deleteSession" => true,
            "backoffTime"   => self::FAST,
            "retry"         => [
                "idempotent"    => true,
                "nonIdempotent" => true
            ]
        ],
    ];
    public function test(){

        $retryParams = new RetryParams(1000, new Backoff(6,self::FAST),
            new Backoff(6,self::SLOW));

        $retry = (new RetrySubclass())->withParams($retryParams);
        $table = new TableSubclass(new Ydb(["credentials"=>new AnonymousAuthentication()]), null, $retry);

        foreach ($this->errsToCheck as $error) {

            $resultDeleteSession = $table->deleteSession($error["class"]) ? "true" : "false";
            $wantDeleteSession = $error["deleteSession"] ? "true" : "false";
            self::assertEquals($wantDeleteSession, $resultDeleteSession,
            "{$error["class"]}: unexpected delete session status: $resultDeleteSession, want: $wantDeleteSession");

            $resultRetryIdempotent = $retry->canRetry(new $error["class"](), true) ? "true" : "false";
            $wantRetryIdempotent = $error["retry"]["idempotent"] ? "true" : "false";
            self::assertEquals($wantRetryIdempotent, $resultRetryIdempotent,
                "{$error["class"]}: unexpected must retry idempotent operation status: $resultRetryIdempotent, want: $wantRetryIdempotent");

            $resultRetryNonIdempotent = $retry->canRetry(new $error["class"](), false) ? "true" : "false";
            $wantRetryNonIdempotent = $error["retry"]["nonIdempotent"] ? "true" : "false";
            self::assertEquals($wantRetryNonIdempotent, $resultRetryNonIdempotent,
                "{$error["class"]}: unexpected must retry non-idempotent operation status: $resultDeleteSession, want: $wantDeleteSession");

            if($error["retry"]["idempotent"]){
                $resultBackoff = $retry->backoffType($error["class"])->getBackoffSlotMillis();
                $wantBackoff = $error["backoffTime"];
                self::assertEquals($wantBackoff, $resultBackoff,
                    "{$error["class"]}: unexpected backoff type: ".
                        self::BACKOFF_TYPE[$resultBackoff].", want: ".
                    self::BACKOFF_TYPE[$wantBackoff]);

            }
        }
    }
}
