<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Table\V1;

/**
 */
class TableServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Create new session. Implicit session creation is forbidden,
     * so user must create new session before execute any query,
     * otherwise BAD_SESSION status will be returned.
     * Simultaneous execution of requests are forbiden.
     * Sessions are volatile, can be invalidated by server, for example in case
     * of fatal errors. All requests with this session will fail with BAD_SESSION status.
     * So, client must be able to handle BAD_SESSION status.
     * @param \Ydb\Table\CreateSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateSession(\Ydb\Table\CreateSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/CreateSession',
        $argument,
        ['\Ydb\Table\CreateSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Ends a session, releasing server resources associated with it.
     * @param \Ydb\Table\DeleteSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteSession(\Ydb\Table\DeleteSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/DeleteSession',
        $argument,
        ['\Ydb\Table\DeleteSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Idle sessions can be kept alive by calling KeepAlive periodically.
     * @param \Ydb\Table\KeepAliveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function KeepAlive(\Ydb\Table\KeepAliveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/KeepAlive',
        $argument,
        ['\Ydb\Table\KeepAliveResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Creates new table.
     * @param \Ydb\Table\CreateTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateTable(\Ydb\Table\CreateTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/CreateTable',
        $argument,
        ['\Ydb\Table\CreateTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Drop table.
     * @param \Ydb\Table\DropTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DropTable(\Ydb\Table\DropTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/DropTable',
        $argument,
        ['\Ydb\Table\DropTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Modifies schema of given table.
     * @param \Ydb\Table\AlterTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AlterTable(\Ydb\Table\AlterTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/AlterTable',
        $argument,
        ['\Ydb\Table\AlterTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Creates copy of given table.
     * @param \Ydb\Table\CopyTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CopyTable(\Ydb\Table\CopyTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/CopyTable',
        $argument,
        ['\Ydb\Table\CopyTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Creates consistent copy of given tables.
     * @param \Ydb\Table\CopyTablesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CopyTables(\Ydb\Table\CopyTablesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/CopyTables',
        $argument,
        ['\Ydb\Table\CopyTablesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Creates consistent move of given tables.
     * @param \Ydb\Table\RenameTablesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RenameTables(\Ydb\Table\RenameTablesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/RenameTables',
        $argument,
        ['\Ydb\Table\RenameTablesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Returns information about given table (metadata).
     * @param \Ydb\Table\DescribeTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeTable(\Ydb\Table\DescribeTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/DescribeTable',
        $argument,
        ['\Ydb\Table\DescribeTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Explains data query.
     * SessionId of previously created session must be provided.
     * @param \Ydb\Table\ExplainDataQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExplainDataQuery(\Ydb\Table\ExplainDataQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/ExplainDataQuery',
        $argument,
        ['\Ydb\Table\ExplainDataQueryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Prepares data query, returns query id.
     * SessionId of previously created session must be provided.
     * @param \Ydb\Table\PrepareDataQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function PrepareDataQuery(\Ydb\Table\PrepareDataQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/PrepareDataQuery',
        $argument,
        ['\Ydb\Table\PrepareDataQueryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Executes data query.
     * SessionId of previously created session must be provided.
     * @param \Ydb\Table\ExecuteDataQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExecuteDataQuery(\Ydb\Table\ExecuteDataQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/ExecuteDataQuery',
        $argument,
        ['\Ydb\Table\ExecuteDataQueryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Executes scheme query.
     * SessionId of previously created session must be provided.
     * @param \Ydb\Table\ExecuteSchemeQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExecuteSchemeQuery(\Ydb\Table\ExecuteSchemeQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/ExecuteSchemeQuery',
        $argument,
        ['\Ydb\Table\ExecuteSchemeQueryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Begins new transaction.
     * @param \Ydb\Table\BeginTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function BeginTransaction(\Ydb\Table\BeginTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/BeginTransaction',
        $argument,
        ['\Ydb\Table\BeginTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Commits specified active transaction.
     * @param \Ydb\Table\CommitTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CommitTransaction(\Ydb\Table\CommitTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/CommitTransaction',
        $argument,
        ['\Ydb\Table\CommitTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Performs a rollback of the specified active transaction.
     * @param \Ydb\Table\RollbackTransactionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RollbackTransaction(\Ydb\Table\RollbackTransactionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/RollbackTransaction',
        $argument,
        ['\Ydb\Table\RollbackTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Describe supported table options.
     * @param \Ydb\Table\DescribeTableOptionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeTableOptions(\Ydb\Table\DescribeTableOptionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/DescribeTableOptions',
        $argument,
        ['\Ydb\Table\DescribeTableOptionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Streaming read table
     * @param \Ydb\Table\ReadTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function StreamReadTable(\Ydb\Table\ReadTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/Ydb.Table.V1.TableService/StreamReadTable',
        $argument,
        ['\Ydb\Table\ReadTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Upserts a batch of rows non-transactionally.
     * Returns success only when all rows were successfully upserted. In case of an error some rows might
     * be upserted and some might not.
     * @param \Ydb\Table\BulkUpsertRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function BulkUpsert(\Ydb\Table\BulkUpsertRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Table.V1.TableService/BulkUpsert',
        $argument,
        ['\Ydb\Table\BulkUpsertResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Executes scan query with streaming result.
     * @param \Ydb\Table\ExecuteScanQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function StreamExecuteScanQuery(\Ydb\Table\ExecuteScanQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/Ydb.Table.V1.TableService/StreamExecuteScanQuery',
        $argument,
        ['\Ydb\Table\ExecuteScanQueryPartialResponse', 'decode'],
        $metadata, $options);
    }

}
