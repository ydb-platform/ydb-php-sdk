<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Import\V1;

/**
 */
class ImportServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Imports data from S3.
     * Method starts an asynchronous operation that can be cancelled while it is in progress.
     * @param \Ydb\Import\ImportFromS3Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ImportFromS3(\Ydb\Import\ImportFromS3Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Import.V1.ImportService/ImportFromS3',
        $argument,
        ['\Ydb\Import\ImportFromS3Response', 'decode'],
        $metadata, $options);
    }

    /**
     * Imports data from file system.
     * Method starts an asynchronous operation that can be cancelled while it is in progress.
     * @param \Ydb\Import\ImportFromFsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ImportFromFs(\Ydb\Import\ImportFromFsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Import.V1.ImportService/ImportFromFs',
        $argument,
        ['\Ydb\Import\ImportFromFsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * List objects from existing export stored in S3 bucket
     * @param \Ydb\Import\ListObjectsInS3ExportRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListObjectsInS3Export(\Ydb\Import\ListObjectsInS3ExportRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Import.V1.ImportService/ListObjectsInS3Export',
        $argument,
        ['\Ydb\Import\ListObjectsInS3ExportResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * List objects from existing export stored in FS
     * @param \Ydb\Import\ListObjectsInFsExportRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListObjectsInFsExport(\Ydb\Import\ListObjectsInFsExportRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Import.V1.ImportService/ListObjectsInFsExport',
        $argument,
        ['\Ydb\Import\ListObjectsInFsExportResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Writes data to a table.
     * Method accepts serialized data in the selected format and writes it non-transactionally.
     * @param \Ydb\Import\ImportDataRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ImportData(\Ydb\Import\ImportDataRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Import.V1.ImportService/ImportData',
        $argument,
        ['\Ydb\Import\ImportDataResponse', 'decode'],
        $metadata, $options);
    }

}
