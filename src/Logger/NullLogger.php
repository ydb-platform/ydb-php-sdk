<?php

namespace YdbPlatform\Ydb\Logger;

use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger as BaseNullLogger;

class NullLogger extends BaseNullLogger
{
    use LoggerTrait;
}