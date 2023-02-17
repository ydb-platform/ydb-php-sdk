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
