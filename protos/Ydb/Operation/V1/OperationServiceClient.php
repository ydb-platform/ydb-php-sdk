<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Operation\V1;

/**
 * All rpc calls to YDB are allowed to be asynchronous. Response message
 * of an rpc call contains Operation structure and OperationService
 * is used for polling operation completion.
 *
 * Operation has a field 'ready' to notify client if operation has been
 * completed or not. If result is ready a client has to handle 'result' field,
 * otherwise it is expected that client continues polling result via
 * GetOperation rpc of OperationService. Polling is made via unique
 * operation id provided in 'id' field of Operation.
 *
 * Note: Currently some operations have synchronous implementation and their result
 * is available when response is obtained. But a client must not make any
 * assumptions about synchronous or asynchronous nature of any operation and
 * be ready to poll operation status.
 *
 */
class OperationServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Check status for a given operation.
     * @param \Ydb\Operations\GetOperationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetOperation(\Ydb\Operations\GetOperationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Operation.V1.OperationService/GetOperation',
        $argument,
        ['\Ydb\Operations\GetOperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Starts cancellation of a long-running operation,
     * Clients can use GetOperation to check whether the cancellation succeeded
     * or whether the operation completed despite cancellation.
     * @param \Ydb\Operations\CancelOperationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CancelOperation(\Ydb\Operations\CancelOperationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Operation.V1.OperationService/CancelOperation',
        $argument,
        ['\Ydb\Operations\CancelOperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Forgets long-running operation. It does not cancel the operation and returns
     * an error if operation was not completed.
     * @param \Ydb\Operations\ForgetOperationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ForgetOperation(\Ydb\Operations\ForgetOperationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Operation.V1.OperationService/ForgetOperation',
        $argument,
        ['\Ydb\Operations\ForgetOperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Lists operations that match the specified filter in the request.
     * @param \Ydb\Operations\ListOperationsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListOperations(\Ydb\Operations\ListOperationsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Operation.V1.OperationService/ListOperations',
        $argument,
        ['\Ydb\Operations\ListOperationsResponse', 'decode'],
        $metadata, $options);
    }

}
