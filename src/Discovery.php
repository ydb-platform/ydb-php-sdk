<?php

namespace YdbPlatform\Ydb;

use Psr\Log\LoggerInterface;
use Ydb\Discovery\V1\DiscoveryServiceClient as ServiceClient;

class Discovery
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
     * @var string
     */
    protected $database;

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

        $this->database = $ydb->database();

        $this->logger = $logger;
    }

    /**
     * @return array|mixed|null
     */
    public function listEndpoints()
    {
        $result = $this->request('ListEndpoints', [
            'database' => $this->database,
        ]);
        return $this->parseResult($result, 'endpoints', []);
    }

    /**
     * @return string
     */
    public function whoAmI()
    {
        $result = $this->request('WhoAmI');
        $user = (string)$result->getUser();

        $this->logger()->info('YDB: WhoAmI [' . $user . ']');

        return $user;
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function request($method, array $data = [])
    {
        return $this->doRequest('Discovery', $method, $data);
    }
}