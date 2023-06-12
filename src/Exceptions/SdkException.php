<?php
namespace YdbPlatform\Ydb\Exceptions;

class SdkException extends \YdbPlatform\Ydb\Exception
{
    /**
     * @var string
     */
    protected $errorCode;

    /**
     * @var bool
     */
    protected $retryable;

    /**
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return bool
     */
    public function isRetryable()
    {
        return $this->retryable;
    }
    public function setErrorCode($errorCode){
        $this->errorCode = $errorCode;
    }
}
