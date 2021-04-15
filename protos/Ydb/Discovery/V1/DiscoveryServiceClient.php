<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Discovery\V1;

/**
 */
class DiscoveryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Ydb\Discovery\ListEndpointsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListEndpoints(\Ydb\Discovery\ListEndpointsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Discovery.V1.DiscoveryService/ListEndpoints',
        $argument,
        ['\Ydb\Discovery\ListEndpointsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Ydb\Discovery\WhoAmIRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function WhoAmI(\Ydb\Discovery\WhoAmIRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Discovery.V1.DiscoveryService/WhoAmI',
        $argument,
        ['\Ydb\Discovery\WhoAmIResponse', 'decode'],
        $metadata, $options);
    }

}
