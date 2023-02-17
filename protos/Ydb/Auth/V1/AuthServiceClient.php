<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Auth\V1;

/**
 */
class AuthServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Perform login using built-in auth system
     * @param \Ydb\Auth\LoginRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Login(\Ydb\Auth\LoginRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Auth.V1.AuthService/Login',
        $argument,
        ['\Ydb\Auth\LoginResponse', 'decode'],
        $metadata, $options);
    }

}
