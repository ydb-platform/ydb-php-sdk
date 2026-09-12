<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Secret\V1;

/**
 */
class SecretServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Describe secret command.
     * @param \Ydb\Secret\DescribeSecretRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeSecret(\Ydb\Secret\DescribeSecretRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Secret.V1.SecretService/DescribeSecret',
        $argument,
        ['\Ydb\Secret\DescribeSecretResponse', 'decode'],
        $metadata, $options);
    }

}
