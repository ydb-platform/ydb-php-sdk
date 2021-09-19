<?php

namespace YandexCloud\Ydb;

use Exception as BaseException;

class Exception extends BaseException
{
    const ERROR_NON_200_STATUS_CODE = 100;
    const ERROR_MALFORMED_RESPONSE = 101;
}
