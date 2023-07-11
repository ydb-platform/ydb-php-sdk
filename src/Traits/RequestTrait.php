<?php

namespace YdbPlatform\Ydb\Traits;

use Ydb\StatusIds\StatusCode;

use YdbPlatform\Ydb\Issue;
use YdbPlatform\Ydb\Exception;
use YdbPlatform\Ydb\QueryResult;
use YdbPlatform\Ydb\Ydb;

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
     * @var Ydb
     */
    protected $ydb;

    /**
     * @var int
     */
    protected $lastDiscovery = 0;

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
        $this->checkDiscovery();

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

            $this->handleGrpcStatus($service, $method, $status);

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
        $this->checkDiscovery();

        $this->meta['x-ydb-auth-ticket'] = [$this->credentials->token()];

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
    protected function handleGrpcStatus($service, $method, $status)
    {
        if (isset($status->code) && $status->code !== 0) {
            $message = 'YDB ' . $service . ' ' . $method . ' (status code GRPC_'.
                (isset(self::$grpcExceptions[$status->code])?self::$grpcNames[$status->code]:$status->code)
                .' ' . $status->code . '): ' . ($status->details ?? 'no details');
            $this->logger->error($message);
            if ($this->ydb->needDiscovery()){
                try{
                    $this->ydb->discover();
                }catch (\Exception $e){}
            }
            $endpoint = $this->ydb->endpoint();
            if ($this->ydb->needDiscovery()){
                $endpoint = $this->ydb->cluster()->all()[array_rand($this->ydb->cluster()->all())]->endpoint();
            }
            $this->client = new $this->client($endpoint,[
                'credentials' => $this->ydb->iam()->getCredentials()
            ]);
            if (isset(self::$grpcExceptions[$status->code])) {
                throw new self::$grpcExceptions[$status->code]($message);
            } else {
                throw new \Exception($message);
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

        if ($statusCode == StatusCode::SUCCESS) {
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
        if (isset(self::$ydbExceptions[$statusCode])) {
            throw new self::$ydbExceptions[$statusCode]($msg);
        } else {
            throw new \Exception($msg);
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

    protected function checkDiscovery(){
        if ($this->ydb->needDiscovery() && time()-$this->lastDiscovery>$this->ydb->discoveryInterval()){
            try{
                $this->lastDiscovery = time();
                $this->ydb->discover();
            } catch (\Exception $e){

            }
        }
    }

    private static $ydbExceptions = [
        StatusCode::STATUS_CODE_UNSPECIFIED => \YdbPlatform\Ydb\Exceptions\Ydb\StatusCodeUnspecified::class,
        StatusCode::BAD_REQUEST => \YdbPlatform\Ydb\Exceptions\Ydb\BadRequestException::class,
        StatusCode::UNAUTHORIZED => \YdbPlatform\Ydb\Exceptions\Ydb\UnauthorizedException::class,
        StatusCode::INTERNAL_ERROR => \YdbPlatform\Ydb\Exceptions\Ydb\InternalErrorException::class,
        StatusCode::ABORTED => \YdbPlatform\Ydb\Exceptions\Ydb\AbortedException::class,
        StatusCode::UNAVAILABLE => \YdbPlatform\Ydb\Exceptions\Ydb\UnavailableException::class,
        StatusCode::OVERLOADED => \YdbPlatform\Ydb\Exceptions\Ydb\OverloadedException::class,
        StatusCode::SCHEME_ERROR => \YdbPlatform\Ydb\Exceptions\Ydb\SchemeErrorException::class,
        StatusCode::GENERIC_ERROR => \YdbPlatform\Ydb\Exceptions\Ydb\GenericErrorException::class,
        StatusCode::TIMEOUT => \YdbPlatform\Ydb\Exceptions\Ydb\TimeoutException::class,
        StatusCode::BAD_SESSION => \YdbPlatform\Ydb\Exceptions\Ydb\BadSessionException::class,
        StatusCode::PRECONDITION_FAILED => \YdbPlatform\Ydb\Exceptions\Ydb\PreconditionFailedException::class,
        StatusCode::ALREADY_EXISTS => \YdbPlatform\Ydb\Exceptions\Ydb\AlreadyExistsException::class,
        StatusCode::NOT_FOUND => \YdbPlatform\Ydb\Exceptions\Ydb\NotFoundException::class,
        StatusCode::SESSION_EXPIRED => \YdbPlatform\Ydb\Exceptions\Ydb\SessionExpiredException::class,
        StatusCode::CANCELLED => \YdbPlatform\Ydb\Exceptions\Ydb\CancelledException::class,
        StatusCode::UNDETERMINED => \YdbPlatform\Ydb\Exceptions\Ydb\UndeterminedException::class,
        StatusCode::UNSUPPORTED => \YdbPlatform\Ydb\Exceptions\Ydb\UnsupportedException::class,
        StatusCode::SESSION_BUSY => \YdbPlatform\Ydb\Exceptions\Ydb\SessionBusyException::class,
    ];

    private static $grpcExceptions = [
        1 => \YdbPlatform\Ydb\Exceptions\Grpc\CanceledException::class,
        2 => \YdbPlatform\Ydb\Exceptions\Grpc\UnknownException::class,
        3 => \YdbPlatform\Ydb\Exceptions\Grpc\InvalidArgumentException::class,
        4 => \YdbPlatform\Ydb\Exceptions\Grpc\DeadlineExceededException::class,
        5 => \YdbPlatform\Ydb\Exceptions\Grpc\NotFoundException::class,
        6 => \YdbPlatform\Ydb\Exceptions\Grpc\AlreadyExistsException::class,
        7 => \YdbPlatform\Ydb\Exceptions\Grpc\PermissionDeniedException::class,
        8 => \YdbPlatform\Ydb\Exceptions\Grpc\ResourceExhaustedException::class,
        9 => \YdbPlatform\Ydb\Exceptions\Grpc\FailedPreconditionException::class,
        10 => \YdbPlatform\Ydb\Exceptions\Grpc\AbortedException::class,
        11 => \YdbPlatform\Ydb\Exceptions\Grpc\OutOfRangeException::class,
        12 => \YdbPlatform\Ydb\Exceptions\Grpc\UnimplementedException::class,
        13 => \YdbPlatform\Ydb\Exceptions\Grpc\InternalException::class,
        14 => \YdbPlatform\Ydb\Exceptions\Grpc\UnavailableException::class,
        15 => \YdbPlatform\Ydb\Exceptions\Grpc\DataLossException::class,
        16 => \YdbPlatform\Ydb\Exceptions\Grpc\UnauthenticatedException::class
    ];

    private static $grpcNames = [
        1 => "CANCELLED",
        2 => "UNKNOWN",
        3 => "INVALID_ARGUMENT",
        4 => "DEADLINE_EXCEEDED",
        5 => "NOT_FOUND",
        6 => "ALREADY_EXISTS",
        7 => "PERMISSION_DENIED",
        8 => "RESOURCE_EXHAUSTED",
        9 => "FAILED_PRECONDITION",
        10 => "ABORTED",
        11 => "OUT_OF_RANGE",
        12 => "UNIMPLEMENTED",
        13 => "INTERNAL",
        14 => "UNAVAILABLE",
        15 => "DATA_LOSS",
        16 => "UNAUTHENTICATED"
    ];
}
