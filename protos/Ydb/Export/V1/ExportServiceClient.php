<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Export\V1;

/**
 */
class ExportServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Exports data to YT.
     * Method starts an asynchronous operation that can be cancelled while it is in progress.
     * @param \Ydb\Export\ExportToYtRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExportToYt(\Ydb\Export\ExportToYtRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Export.V1.ExportService/ExportToYt',
        $argument,
        ['\Ydb\Export\ExportToYtResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Exports data to S3.
     * Method starts an asynchronous operation that can be cancelled while it is in progress.
     * @param \Ydb\Export\ExportToS3Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExportToS3(\Ydb\Export\ExportToS3Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Export.V1.ExportService/ExportToS3',
        $argument,
        ['\Ydb\Export\ExportToS3Response', 'decode'],
        $metadata, $options);
    }

}
