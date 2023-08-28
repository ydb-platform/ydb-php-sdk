<?php

namespace YdbPlatform\Ydb\Auth;

class ReadFromTextFileCredentials extends Auth
{

    protected $fileName;
    protected $readInterval;

    /**
     * @param string $fileName
     * @param int $readInterval
     */
    public function __construct(string $fileName = "token.json", int $readInterval = 600)
    {
        if(!file_exists($fileName)){
            throw new \Exception("File $fileName is not exists");
        }
        $this->fileName = $fileName;
        $this->readInterval = $readInterval;
    }

    public function getTokenInfo(): TokenInfo
    {
        if(!file_exists($this->fileName)){
            throw new \Exception("File $this->fileName is not exists");
        }
        $token = preg_filter('/\s+|\n/', "", file_get_contents($this->fileName));
        return new TokenInfo($token, $this->readInterval, 1);
    }

    public function getName(): string
    {
        return "from file";
    }
}
