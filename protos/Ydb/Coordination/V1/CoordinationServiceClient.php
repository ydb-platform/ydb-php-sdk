<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Coordination\V1;

/**
 */
class CoordinationServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * *
     * Bidirectional stream used to establish a session with a coordination node
     *
     * Relevant APIs for managing semaphores, distributed locking, creating or
     * restoring a previously established session are described using nested
     * messages in SessionRequest and SessionResponse. Session is established
     * with a specific coordination node (previously created using CreateNode
     * below) and semaphores are local to that coordination node.
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function Session($metadata = [], $options = []) {
        return $this->_bidiRequest('/Ydb.Coordination.V1.CoordinationService/Session',
        ['\Ydb\Coordination\SessionResponse','decode'],
        $metadata, $options);
    }

    /**
     * Creates a new coordination node
     * @param \Ydb\Coordination\CreateNodeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateNode(\Ydb\Coordination\CreateNodeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Coordination.V1.CoordinationService/CreateNode',
        $argument,
        ['\Ydb\Coordination\CreateNodeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Modifies settings of a coordination node
     * @param \Ydb\Coordination\AlterNodeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AlterNode(\Ydb\Coordination\AlterNodeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Coordination.V1.CoordinationService/AlterNode',
        $argument,
        ['\Ydb\Coordination\AlterNodeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Drops a coordination node
     * @param \Ydb\Coordination\DropNodeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DropNode(\Ydb\Coordination\DropNodeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Coordination.V1.CoordinationService/DropNode',
        $argument,
        ['\Ydb\Coordination\DropNodeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Describes a coordination node
     * @param \Ydb\Coordination\DescribeNodeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeNode(\Ydb\Coordination\DescribeNodeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Coordination.V1.CoordinationService/DescribeNode',
        $argument,
        ['\Ydb\Coordination\DescribeNodeResponse', 'decode'],
        $metadata, $options);
    }

}
