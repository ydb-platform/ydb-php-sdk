<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Topic\V1;

/**
 */
class TopicServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Create Write Session
     * Pipeline example:
     * client                  server
     *         InitRequest(Topic, MessageGroupID, ...)
     *        ---------------->
     *         InitResponse(Partition, MaxSeqNo, ...)
     *        <----------------
     *         WriteRequest(data1, seqNo1)
     *        ---------------->
     *         WriteRequest(data2, seqNo2)
     *        ---------------->
     *         WriteResponse(seqNo1, offset1, ...)
     *        <----------------
     *         WriteRequest(data3, seqNo3)
     *        ---------------->
     *         WriteResponse(seqNo2, offset2, ...)
     *        <----------------
     *         [something went wrong] (status != SUCCESS, issues not empty)
     *        <----------------
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function StreamWrite($metadata = [], $options = []) {
        return $this->_bidiRequest('/Ydb.Topic.V1.TopicService/StreamWrite',
        ['\Ydb\Topic\StreamWriteMessage\FromServer','decode'],
        $metadata, $options);
    }

    /**
     * Create Read Session
     * Pipeline:
     * client                  server
     *         InitRequest(Topics, ClientId, ...)
     *        ---------------->
     *         InitResponse(SessionId)
     *        <----------------
     *         ReadRequest
     *        ---------------->
     *         ReadRequest
     *        ---------------->
     *         StartPartitionSessionRequest(Topic1, Partition1, PartitionSessionID1, ...)
     *        <----------------
     *         StartPartitionSessionRequest(Topic2, Partition2, PartitionSessionID2, ...)
     *        <----------------
     *         StartPartitionSessionResponse(PartitionSessionID1, ...)
     *             client must respond with this message to actually start recieving data messages from this partition
     *        ---------------->
     *         StopPartitionSessionRequest(PartitionSessionID1, ...)
     *        <----------------
     *         StopPartitionSessionResponse(PartitionSessionID1, ...)
     *             only after this response server will give this parittion to other session.
     *        ---------------->
     *         StartPartitionSessionResponse(PartitionSession2, ...)
     *        ---------------->
     *         ReadResponse(data, ...)
     *        <----------------
     *         CommitRequest(PartitionCommit1, ...)
     *        ---------------->
     *         CommitResponse(PartitionCommitAck1, ...)
     *        <----------------
     *         [something went wrong] (status != SUCCESS, issues not empty)
     *        <----------------
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function StreamRead($metadata = [], $options = []) {
        return $this->_bidiRequest('/Ydb.Topic.V1.TopicService/StreamRead',
        ['\Ydb\Topic\StreamReadMessage\FromServer','decode'],
        $metadata, $options);
    }

    /**
     * Create topic command.
     * @param \Ydb\Topic\CreateTopicRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateTopic(\Ydb\Topic\CreateTopicRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Topic.V1.TopicService/CreateTopic',
        $argument,
        ['\Ydb\Topic\CreateTopicResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Describe topic command.
     * @param \Ydb\Topic\DescribeTopicRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeTopic(\Ydb\Topic\DescribeTopicRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Topic.V1.TopicService/DescribeTopic',
        $argument,
        ['\Ydb\Topic\DescribeTopicResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Describe topic's consumer command.
     * @param \Ydb\Topic\DescribeConsumerRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeConsumer(\Ydb\Topic\DescribeConsumerRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Topic.V1.TopicService/DescribeConsumer',
        $argument,
        ['\Ydb\Topic\DescribeConsumerResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Alter topic command.
     * @param \Ydb\Topic\AlterTopicRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AlterTopic(\Ydb\Topic\AlterTopicRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Topic.V1.TopicService/AlterTopic',
        $argument,
        ['\Ydb\Topic\AlterTopicResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Drop topic command.
     * @param \Ydb\Topic\DropTopicRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DropTopic(\Ydb\Topic\DropTopicRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Topic.V1.TopicService/DropTopic',
        $argument,
        ['\Ydb\Topic\DropTopicResponse', 'decode'],
        $metadata, $options);
    }

}
