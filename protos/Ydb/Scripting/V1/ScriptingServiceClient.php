<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Scripting\V1;

/**
 */
class ScriptingServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Ydb\Scripting\ExecuteYqlRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExecuteYql(\Ydb\Scripting\ExecuteYqlRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scripting.V1.ScriptingService/ExecuteYql',
        $argument,
        ['\Ydb\Scripting\ExecuteYqlResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Executes yql request with streaming result.
     * @param \Ydb\Scripting\ExecuteYqlRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function StreamExecuteYql(\Ydb\Scripting\ExecuteYqlRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/Ydb.Scripting.V1.ScriptingService/StreamExecuteYql',
        $argument,
        ['\Ydb\Scripting\ExecuteYqlPartialResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Ydb\Scripting\ExplainYqlRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExplainYql(\Ydb\Scripting\ExplainYqlRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scripting.V1.ScriptingService/ExplainYql',
        $argument,
        ['\Ydb\Scripting\ExplainYqlResponse', 'decode'],
        $metadata, $options);
    }

}
