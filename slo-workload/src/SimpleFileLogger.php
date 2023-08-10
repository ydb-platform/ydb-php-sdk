<?php

namespace YdbPlatform\Ydb\Slo;

class SimpleFileLogger implements \Psr\Log\LoggerInterface
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
    protected $filename;

    protected static function getLevelName(int $level): string
    {
        if (!isset(static::$levels[$level])) {
            throw new \Exception('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
        }

        return static::$levels[$level];
    }

    protected $level;

    public function __construct(int $level, string $filename)
    {
        $this->level = $level;
        $this->filename = $filename;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        if ($level>$this->level) return;
        file_put_contents($this->filename,
            date("d/m/y H:i:s")." ".self::getLevelName($level). " ".$message." ".json_encode($context)."\n",
        FILE_APPEND);
    }
}

