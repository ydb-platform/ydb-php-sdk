<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\FederationDiscovery\V1;

/**
 */
class FederationDiscoveryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Get list of databases.
     * @param \Ydb\FederationDiscovery\ListFederationDatabasesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListFederationDatabases(\Ydb\FederationDiscovery\ListFederationDatabasesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.FederationDiscovery.V1.FederationDiscoveryService/ListFederationDatabases',
        $argument,
        ['\Ydb\FederationDiscovery\ListFederationDatabasesResponse', 'decode'],
        $metadata, $options);
    }

}
