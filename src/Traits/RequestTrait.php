<?php

namespace YdbPlatform\Ydb\Traits;

use Ydb\StatusIds\StatusCode;

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
        $this->saveLastRequest($service, $method, $data);

        $requestClass = '\\Ydb\\' . $service . '\\' . $method . 'Request';

        switch ($method)
        {
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

        if (method_exists($call, 'wait'))
        {
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
        if (method_exists($this, 'take'))
        {
            $this->take();
        }

        $requestClass = '\\Ydb\\' . $service . '\\' . $method . 'Request';

        switch ($method)
        {
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

        if (method_exists($call, 'responses'))
        {
            // $status = $call->getStatus();
            // $this->checkStatus($service, $method, $status);

            foreach ($call->responses() as $response)
            {
                $result = $this->processResponse($service, $method, $response, $resultClass);
                yield $result ? new QueryResult($result) : true;
            }
        }

        if (method_exists($this, 'release'))
        {
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
        if (isset($status->code) && $status->code !== 0)
        {
            throw new Exception('YDB ' . $service . ' ' . $method . ' (status code ' . $status->code . '): ' . ($status->details ?? 'no details'));
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
        if (method_exists($response, 'getOperation'))
        {
            $response = $response->getOperation();
        }

        if (!method_exists($response, 'getStatus') || !method_exists($response, 'getResult'))
        {
            return $response;
        }

        $statusCode = $response->getStatus();

        switch ($statusCode)
        {
            case StatusCode::STATUS_CODE_UNSPECIFIED:
                return true;
                break;

            case StatusCode::SUCCESS:
                $result = $response->getResult();

                if ($result === null)
                {
                    return true;
                }

                if (is_object($result))
                {
                    if ($resultClass && class_exists($resultClass))
                    {
                        $jsonResult = $result->serializeToJsonString();

                        $this->logger()->debug('YDB: Received API response [' . $resultClass . '].', json_decode($jsonResult, true));

                        $result = new $resultClass;
                        $result->mergeFromJsonString($jsonResult);
                    }
                }

                $this->resetLastRequest();

                return $result;

                break;

            case StatusCode::BAD_SESSION:
                if (method_exists($this, 'refresh'))
                {
                    $session = $this->refresh();

                    if (isset($this->last_request_data['session_id']))
                    {
                        $this->last_request_data['session_id'] = $session->id();
                    }

                    // only 10 retries are allowed!
                    if ($this->last_request_try_count < 10)
                    {
                        return $this->retryLastRequest($sleep ?? 100);
                    }
                }
                else
                {
                    $this->logger()->error('YDB: Service [' . $service . '] method [' . $method . '] Failed to receive a valid response.', [
                        'status' => 'BAD_SESSION',
                    ]);

                    throw new Exception('YDB ' . $service . ' ' . $method . ' (' . $statusCode . ' BAD_SESSION)');
                }
                break;

            case StatusCode::OVERLOADED:
            // case StatusCode::CLIENT_RESOURCE_EXHAUSTED: // ?
                $sleep = 200; // wait 200 ms

            case StatusCode::ABORTED:
            case StatusCode::UNAVAILABLE:
            // case StatusCode::TRANSPORT_UNAVAILABLE:
            case StatusCode::TIMEOUT: // ?
                $statusName = StatusCode::name($statusCode);

                $issues = [];
                foreach ($response->getIssues() as $issue)
                {
                    $issues[] = (new Issue($issue))->toString();
                }

                $message = implode("\n", $issues);

                $this->logger()->warning('YDB: Service [' . $service . '] method [' . $method . '] Failed to receive a valid response.', [
                    'status' => $statusCode . ' (' . $statusName . ')',
                    'message' => $message,
                    'try_count' => $this->last_request_try_count,
                ]);

                // only 10 retries are allowed!
                if ($this->last_request_try_count < 10)
                {
                    return $this->retryLastRequest($sleep ?? 100);
                }

                // break; // no need to break here, it must proceed to default section

            default:
                $statusName = StatusCode::name($statusCode);

                $issues = [];
                foreach ($response->getIssues() as $issue)
                {
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

                throw new Exception('YDB ' . $service . ' ' . $method . ' (' . $statusCode . ' ' . $statusName . '): ' . $message);
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
        if ($this->last_request_service && $this->last_request_method)
        {
            $this->logger()->info('Going to retry the last request!');

            usleep(max($this->last_request_try_count, 1) * $sleep * 1000); // waiting 100 ms more
            $this->doRequest($this->last_request_service, $this->last_request_method, $this->last_request_data);
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
