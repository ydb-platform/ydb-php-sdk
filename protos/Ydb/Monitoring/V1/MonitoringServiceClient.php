<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Monitoring\V1;

/**
 */
class MonitoringServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Gets the health status of the database.
     * @param \Ydb\Monitoring\SelfCheckRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SelfCheck(\Ydb\Monitoring\SelfCheckRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Monitoring.V1.MonitoringService/SelfCheck',
        $argument,
        ['\Ydb\Monitoring\SelfCheckResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Checks current node health
     * @param \Ydb\Monitoring\NodeCheckRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function NodeCheck(\Ydb\Monitoring\NodeCheckRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Monitoring.V1.MonitoringService/NodeCheck',
        $argument,
        ['\Ydb\Monitoring\NodeCheckResponse', 'decode'],
        $metadata, $options);
    }

}
