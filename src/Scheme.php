<?php

namespace YdbPlatform\Ydb;

use Psr\Log\LoggerInterface;
use Ydb\Scheme\Permissions;
use Ydb\Scheme\PermissionsAction;
use Ydb\Scheme\V1\SchemeServiceClient as ServiceClient;

class Scheme
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
     * @param string $path
     * @return bool|mixed|void|null
     */
    public function makeDirectory($path = '')
    {
        $path = rtrim($this->database . '/' . $path, '/');
        $result = $this->request('MakeDirectory', [
            'path' => $path,
        ]);

        $this->logger()->info('YDB: New directory [' . $path . '] created.');

        return $result;
    }

    /**
     * @param string $path
     * @return bool|mixed|void|null
     */
    public function removeDirectory($path = '')
    {
        $path = rtrim($this->database . '/' . $path, '/');
        $result = $this->request('RemoveDirectory', [
            'path' => $path,
        ]);

        $this->logger()->info('YDB: Directory [' . $path . '] removed.');

        return $result;
    }

    /**
     * @param string $path
     * @return array|mixed|null
     */
    public function listDirectory($path = '')
    {
        $result = $this->request('ListDirectory', [
            'path' => rtrim($this->database . '/' . $path, '/'),
        ]);

        return $this->parseResult($result, 'children', []);
    }

    /**
     * @param string $path
     * @return array|mixed|null
     */
    public function describePath($path = '')
    {
        $result = $this->request('DescribePath', [
            'path' => rtrim($this->database . '/' . $path, '/'),
        ]);

        return $this->parseResult($result, 'self', []);
    }

    /**
     * @param string $path
     * @param array $actions
     * @return bool|mixed|void|null
     */
    public function modifyPermissions($path = '', array $actions)
    {
        $path = rtrim($this->database . '/' . $path, '/');
        $result = $this->request('ModifyPermissions', [
            'path' => $path,
            'actions' => [
                new PermissionsAction(array_filter([
                    'grant' => $this->permissionAction($actions, 'grant') ?? null,
                    'revoke' => $this->permissionAction($actions, 'revoke') ?? null,
                    'set' => $this->permissionAction($actions, 'set') ?? null,
                    'change_owner' => $actions['change_owner'] ?? null,
                ])),
            ],
        ]);

        return $result;
    }

    /**
     * @param array $actions
     * @param string $key
     * @return Permissions|null
     */
    protected function permissionAction($actions, $key)
    {
        $action = $action[$key] ?? [];
        if (!empty($action['subject']) && !empty($action['permission_names']))
        {
            return new Permissions([
                'subject' => $action['subject'],
                'permission_names' => (array)$action['permission_names'],
            ]);
        }
        return null;
    }

    /**
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws \YdbPlatform\Ydb\Exception
     */
    protected function request($method, array $data = [])
    {
        return $this->doRequest('Scheme', $method, $data);
    }

}
