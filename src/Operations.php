<?php

namespace YdbPlatform\Ydb;

use Psr\Log\LoggerInterface;
use Ydb\Operations\V1\OperationServiceClient as ServiceClient;

class Operations
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
     * @param string $id
     * @return bool|mixed|void
     */
    public function get(string $id)
    {
        return $this->request('GetOperation', [
            'id' => $id,
        ]);
    }

    /**
     * @param string $id
     * @return bool|mixed|void
     */
    public function cancel(string $id)
    {
        return $this->request('CancelOperation', [
            'id' => $id,
        ]);
    }

    /**
     * @param string $id
     * @return bool|mixed|void
     */
    public function forget(string $id)
    {
        return $this->request('ForgetOperation', [
            'id' => $id,
        ]);
    }

    /**
     * @param $kind
     * @param int $page_size
     * @param string $page_token
     * @return bool|mixed|void
     */
    public function list($kind, int $page_size = 0, string $page_token = '')
    {
        return $this->request('ListOperations', [
            'kind' => $kind,
            'page_size' => $page_size,
            'page_token' => $page_token,
        ]);
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void
     */
    protected function request(string $method, array $data = [])
    {
        return $this->doRequest('Operations', $method, $data);
    }

}
