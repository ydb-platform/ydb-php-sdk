<?php

namespace YdbPlatform\Ydb\Logger;

class SampleStdLogger implements \Psr\Log\LoggerInterface
{
    const DEBUG = 7;
    const INFO = 6;
    const NOTICE = 5;
    const WARNING = 4;
    const ERROR = 3;
    const CRITICAL = 2;
    const ALERT = 1;
    const EMERGENCY = 0;

    protected static $levels = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    protected $level;
    public function __construct(int $level)
    {
        $this->level = $level;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        if ($level>$this->level) return;
        fwrite(STDERR,
            date("d/m/y H:i")." ".$level. " ".$message." ".json_encode($context)
        );
    }
}
