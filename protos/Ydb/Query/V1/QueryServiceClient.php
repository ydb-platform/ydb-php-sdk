<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Query\V1;

/**
 */
class QueryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Sessions are basic primitives for communicating with YDB Query Service. The are similar to
     * connections for classic relational DBs. Sessions serve three main purposes:
     * 1. Provide a flow control for DB requests with limited number of active channels.
     * 2. Distribute load evenly across multiple DB nodes.
     * 3. Store state for volatile stateful operations, such as short-living transactions.
     * @param \Ydb\Query\CreateSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateSession(\Ydb\Query\CreateSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/CreateSession',
        $argument,
        ['\Ydb\Query\CreateSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Ydb\Query\DeleteSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteSession(\Ydb\Query\DeleteSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/DeleteSession',
        $argument,
        ['\Ydb\Query\DeleteSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Ydb\Query\AttachSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function AttachSession(\Ydb\Query\AttachSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/Ydb.Query.V1.QueryService/AttachSession',
        $argument,
        ['\Ydb\Query\SessionState', 'decode'],
        $metadata, $options);
    }

    /**
     * Short-living transactions allow transactional execution of several queries, including support
     * for interactive transactions. Transaction control can be implemented via flags in ExecuteQuery
     * call (recommended), or via explicit calls to Begin/Commit/RollbackTransaction.
     * @param \Ydb\Query\BeginTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function BeginTransaction(\Ydb\Query\BeginTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/BeginTransaction',
        $argument,
        ['\Ydb\Query\BeginTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Ydb\Query\CommitTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CommitTransaction(\Ydb\Query\CommitTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/CommitTransaction',
        $argument,
        ['\Ydb\Query\CommitTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Ydb\Query\RollbackTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RollbackTransaction(\Ydb\Query\RollbackTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/RollbackTransaction',
        $argument,
        ['\Ydb\Query\RollbackTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Execute interactive query in a specified short-living transaction.
     * YDB query can contain DML, DDL and DCL statements. Supported mix of different statement types depends
     * on the chosen transaction type.
     * In case of error, including transport errors such as interrupted stream, whole transaction
     * needs to be retried. For non-idempotent transaction, a custom client logic is required to
     * retry conditionally retriable statuses, when transaction execution state is unknown.
     * @param \Ydb\Query\ExecuteQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function ExecuteQuery(\Ydb\Query\ExecuteQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/Ydb.Query.V1.QueryService/ExecuteQuery',
        $argument,
        ['\Ydb\Query\ExecuteQueryResponsePart', 'decode'],
        $metadata, $options);
    }

    /**
     * Execute long-running script.
     * YDB scripts can contain all type of statements, including TCL statements. This way you can execute multiple
     * transactions in a single YDB script.
     * ExecuteScript call returns long-running Ydb.Operation object with:
     *   operation.metadata = ExecuteScriptMetadata
     *   operation.result = Empty
     * Script execution metadata contains all information about current execution state, including
     * execution_id, execution statistics and result sets info.
     * You can use standard operation methods such as Get/Cancel/Forget/ListOperations to work with script executions.
     * Script can be executed as persistent, in which case all execution information and results will be stored
     * persistently and available after successful or unsuccessful execution.
     * @param \Ydb\Query\ExecuteScriptRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExecuteScript(\Ydb\Query\ExecuteScriptRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/ExecuteScript',
        $argument,
        ['\Ydb\Operations\Operation', 'decode'],
        $metadata, $options);
    }

    /**
     * Fetch results for script execution using fetch_token for continuation.
     * For script with multiple result sets, parts of different results sets are interleaved in responses.
     * For persistent scripts, you can fetch results in specific position of specific result set using
     * position instead of fetch_token.
     * @param \Ydb\Query\FetchScriptResultsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function FetchScriptResults(\Ydb\Query\FetchScriptResultsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Query.V1.QueryService/FetchScriptResults',
        $argument,
        ['\Ydb\Query\FetchScriptResultsResponse', 'decode'],
        $metadata, $options);
    }

}
