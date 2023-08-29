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
    public function __construct(string $fileName = "token.txt", int $readInterval = 60)
    {
        if(file_get_contents($fileName)===false){
            throw new \Exception("Error reading the file '$fileName'");
        }
        $this->fileName = $fileName;
        $this->readInterval = $readInterval;
    }

    public function getTokenInfo(): TokenInfo
    {
        $token = file_get_contents($this->fileName);
        if($token===false){
            throw new \Exception("Error reading the file '$this->fileName'");
        }
        $token = trim($token);
        return new TokenInfo($token, $this->readInterval, 1);
    }

    public function getName(): string
    {
        return "from file";
    }
}
