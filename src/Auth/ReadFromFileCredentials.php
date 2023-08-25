<?php

namespace YdbPlatform\Ydb\Auth;

class ReadFromFileCredentials extends Auth
{

    protected $fileName;
    protected $readInterval;

    /**
     * @param string $fileName
     * @param int $readInterval
     */
    public function __construct(string $fileName = "token.txt", int $readInterval = 600)
    {
        $this->fileName = $fileName;
        $this->readInterval = $readInterval;
    }

    public function getTokenInfo(): TokenInfo
    {
        return new TokenInfo(file_get_contents($this->fileName), $this->readInterval, 1);
    }

    public function getName(): string
    {
        return "from file";
    }
}
