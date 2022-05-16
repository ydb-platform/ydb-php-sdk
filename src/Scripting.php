<?php

namespace YdbPlatform\Ydb;

use Psr\Log\LoggerInterface;
use Ydb\Scripting\V1\ScriptingServiceClient as ServiceClient;

class Scripting
{
    use Traits\RequestTrait;
    use Traits\ParseResultTrait;
    use Traits\LoggerTrait;

    /**
     * @var ServiceClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $meta;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Ydb $ydb
     * @param LoggerInterface|null $logger
     */
    public function __construct(Ydb $ydb, LoggerInterface $logger = null)
    {
        $this->client = new ServiceClient($ydb->endpoint(), [
            'credentials' => $ydb->iam()->getCredentials(),
        ]);

        $this->meta = $ydb->meta();

        $this->logger = $logger;
    }

    /**
     * @param string $script
     * @return bool|QueryResult
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function exec($script)
    {
        $result = $this->request('ExecuteYql', [
            'script' => $script,
        ]);

        return $result ? new QueryResult($result) : true;
    }

    /**
     * @param string $script
     * @return bool|mixed|void|null
     */
    public function explain($script)
    {
        $result = $this->request('ExplainYql', [
            'script' => $script,
        ]);

        return $result;
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function request($method, array $data = [])
    {
        return $this->doRequest('Scripting', $method, $data);
    }

}
