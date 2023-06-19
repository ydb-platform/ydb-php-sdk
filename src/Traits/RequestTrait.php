<?php

namespace YdbPlatform\Ydb\Traits;

use Grpc\Status;
use Ydb\StatusIds\StatusCode;

use YdbPlatform\Ydb\Exceptions\Grpc\CanceledException;
use YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException;
use YdbPlatform\Ydb\Exceptions\Grpc\InvalidArgumentException;
use YdbPlatform\Ydb\Exceptions\Grpc\UnknownException;
use YdbPlatform\Ydb\Exceptions\Ydb\AbortedException;
use YdbPlatform\Ydb\Exceptions\Ydb\AlreadyExistsException;
use YdbPlatform\Ydb\Exceptions\Ydb\BadRequestException;
use YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException;
use YdbPlatform\Ydb\Exceptions\Ydb\ClientResourceExhaustedException;
use YdbPlatform\Ydb\Exceptions\Ydb\OverloadException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException;
use YdbPlatform\Ydb\Exceptions\Ydb\SessionExpiredException;
use YdbPlatform\Ydb\Exceptions\Ydb\TimeoutException;
use YdbPlatform\Ydb\Exceptions\Ydb\UnavailableException;
use YdbPlatform\Ydb\Issue;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\QueryResult;

trait RequestTrait
{
    /**
     * @var string
     */
    protected $last_request_service;

    /**
     * @var string
     */
    protected $last_request_method;

    /**
     * @var array
     */
    protected $last_request_data;

    /**
     * @var int
     */
    protected $last_request_try_count = 0;

    /**
     * Make a request to the service with the given method.
     *
     * @param string $service
     * @param string $method
     * @param array $data
     * @return bool|mixed|void|null
     * @throws Exception
     */
    protected function doRequest($service, $method, array $data = [])
    {
        $this->meta['x-ydb-auth-ticket'] = [$this->credentials->token()];

        $this->saveLastRequest($service, $method, $data);

        $requestClass = '\\Ydb\\' . $service . '\\' . $method . 'Request';

        switch ($method) {
            case 'BulkUpsert':
            case 'CommitTransaction':
            case 'RollbackTransaction':
                $resultClass = null;
                break;

            case 'PrepareDataQuery':
                $resultClass = '\\Ydb\\' . $service . '\\PrepareQueryResult';
                break;

            case 'ExecuteDataQuery':
                $resultClass = '\\Ydb\\' . $service . '\\ExecuteQueryResult';
                break;

            case 'ExplainDataQuery':
                $resultClass = '\\Ydb\\' . $service . '\\ExplainQueryResult';
                break;

            default:
                $resultClass = '\\Ydb\\' . $service . '\\' . $method . 'Result';
        }

        $request = new $requestClass($data);

        $this->logger()->debug(
            'YDB: Sending API request [' . $requestClass . '].',
            json_decode($request->serializeToJsonString(), true)
        );

        $call = $this->client->$method($request, $this->meta);

        if (method_exists($call, 'wait')) {
            list($response, $status) = $call->wait();

            $this->checkStatus($service, $method, $status);

            return $this->processResponse($service, $method, $response, $resultClass);
        }

        return null;
    }

    /**
     * Make a stream request to the service with the given method.
     *
     * @param string $service
     * @param string $method
     * @param array $data
     * @return \Generator
     * @throws Exception
     */
    protected function doStreamRequest($service, $method, $data = [])
    {
        if (method_exists($this, 'take')) {
            $this->take();
        }

        $requestClass = '\\Ydb\\' . $service . '\\' . $method . 'Request';

        switch ($method) {
            case 'StreamReadTable':
                $requestClass = '\\Ydb\\' . $service . '\\ReadTableRequest';
                $resultClass = '\\Ydb\\' . $service . '\\ReadTableResult';
                break;

            case 'StreamExecuteScanQuery':
                $requestClass = '\\Ydb\\' . $service . '\\ExecuteScanQueryRequest';
                $resultClass = '\\Ydb\\' . $service . '\\ExecuteScanQueryPartialResult';
                break;

            default:
                $resultClass = '\\Ydb\\' . $service . '\\' . $method . 'Result';
        }

        $request = new $requestClass($data);

        $call = $this->client->$method($request, $this->meta);

        if (method_exists($call, 'responses')) {
            // $status = $call->getStatus();
            // $this->checkStatus($service, $method, $status);

            foreach ($call->responses() as $response) {
                $result = $this->processResponse($service, $method, $response, $resultClass);
                yield $result ? new QueryResult($result) : true;
            }
        }

        if (method_exists($this, 'release')) {
            $this->release();
        }
    }

    /**
     * Check response status.
     *
     * @param string $service
     * @param string $method
     * @param object $status
     * @throws Exception
     */
    protected function checkStatus($service, $method, $status)
    {
        if (isset($status->code) && $status->code !== 0) {
            $message = 'YDB ' . $service . ' ' . $method . ' (status code GRPC_' . $status->code . '): ' . ($status->details ?? 'no details');
            switch ($status->code){
                case 1:
                    throw new CanceledException($message);
                case 2:
                    throw new UnknownException($message);
                case 3:
                    throw new InvalidArgumentException($message);
                case 4:
                    throw new DeadlineExceededException($message);
                default:
                    throw new Exception($message);
            }
        }
    }

    /**
     * Process a response from the service.
     *
     * @param string $service
     * @param string $method
     * @param object $response
     * @param string $resultClass
     * @return bool|mixed|void
     * @throws Exception
     */
    protected function processResponse($service, $method, $response, $resultClass)
    {
        if (method_exists($response, 'getOperation')) {
            $response = $response->getOperation();
        }

        if (!method_exists($response, 'getStatus') || !method_exists($response, 'getResult')) {
            return $response;
        }

        $statusCode = $response->getStatus();

        if ($statusCode == StatusCode::STATUS_CODE_UNSPECIFIED) {
            return true;
        } elseif ($statusCode == StatusCode::SUCCESS) {
            $result = $response->getResult();

            if ($result === null) {
                return true;
            }

            if (is_object($result)) {
                if ($resultClass && class_exists($resultClass)) {
                    $jsonResult = $result->serializeToJsonString();

                    $this->logger()->debug('YDB: Received API response [' . $resultClass . '].', json_decode($jsonResult, true));

                    $result = new $resultClass;
                    $result->mergeFromJsonString($jsonResult);
                }
            }

            $this->resetLastRequest();

            return $result;
        }
        $statusName = StatusCode::name($statusCode);

        $issues = [];
        foreach ($response->getIssues() as $issue) {
            $issues[] = (new Issue($issue))->toString();
        }

        $message = implode("\n", $issues);

        $this->logger()->error(
            'YDB: Service [' . $service . '] method [' . $method . '] Failed to receive a valid response.',
            [
                'status' => $statusCode . ' (' . $statusName . ')',
                'message' => $message,
            ]
        );

        $msg = 'YDB ' . $service . ' ' . $method . ' (YDB_' . $statusCode . ' ' . $statusName . '): ' . $message;
        switch ($statusCode) {
            case StatusCode::ABORTED:
                throw new AbortedException($msg);
            case StatusCode::SESSION_BUSY:
                throw new SessionBusyException($msg);
            case StatusCode::SESSION_EXPIRED:
                throw new SessionExpiredException($msg);
            case StatusCode::ALREADY_EXISTS:
                throw new AlreadyExistsException($msg);
            case StatusCode::BAD_REQUEST:
                throw new BadRequestException($msg);
            case StatusCode::BAD_SESSION:
                throw new BadSessionException($msg);
            case StatusCode::OVERLOADED:
                throw new OverloadException($msg);
//            case StatusCode::CLIENT_RESOURCE_EXHAUSTED:
//                throw new ClientResourceExhaustedException($msg);
            case StatusCode::ABORTED:
                throw new AbortedException($msg);
            case StatusCode::UNAVAILABLE:
                throw new UnavailableException($msg);
//                 case StatusCode::TRANSPORT_UNAVAILABLE:
            case StatusCode::TIMEOUT: // ?
                throw new TimeoutException($message);
            default:
                throw new Exception($message);
        }
    }

    /**
     * Retry the last request.
     *
     * @param int $sleep
     * @throws Exception
     */
    protected function retryLastRequest($sleep = 100)
    {
        if ($this->last_request_service && $this->last_request_method) {
            $this->logger()->info('Going to retry the last request!');

            usleep(max($this->last_request_try_count, 1) * $sleep * 1000); // waiting 100 ms more
            return $this->doRequest($this->last_request_service, $this->last_request_method, $this->last_request_data);
        }
    }

    /**
     * Save the last request to perform a retry.
     *
     * @param string $service
     * @param method $method
     * @param array $data
     */
    protected function saveLastRequest($service, $method, array $data = [])
    {
        $this->last_request_service = $service;
        $this->last_request_method = $method;
        $this->last_request_data = $data;
        $this->last_request_try_count++;
    }

    /**
     * Reset the last saved request.
     */
    protected function resetLastRequest()
    {
        $this->last_request_service = null;
        $this->last_request_method = null;
        $this->last_request_data = null;
        $this->last_request_try_count = 0;
    }

}
