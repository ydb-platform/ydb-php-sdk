<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_persqueue_v1.proto

namespace Ydb\PersQueue\V1\StreamingReadClientMessageNew;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Message that represents client readiness for receiving more data.
 *
 * Generated from protobuf message <code>Ydb.PersQueue.V1.StreamingReadClientMessageNew.ReadRequest</code>
 */
class ReadRequest extends \Google\Protobuf\Internal\Message
{
    /**
     * Client acquired this amount of free bytes more for buffer. Server can send more data at most of this uncompressed size.
     * Subsequent messages with 5 and 10 request_uncompressed_size are treated by server that it can send messages for at most 15 bytes.
     *
     * Generated from protobuf field <code>int64 request_uncompressed_size = 1;</code>
     */
    protected $request_uncompressed_size = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int|string $request_uncompressed_size
     *           Client acquired this amount of free bytes more for buffer. Server can send more data at most of this uncompressed size.
     *           Subsequent messages with 5 and 10 request_uncompressed_size are treated by server that it can send messages for at most 15 bytes.
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbPersqueueV1::initOnce();
        parent::__construct($data);
    }

    /**
     * Client acquired this amount of free bytes more for buffer. Server can send more data at most of this uncompressed size.
     * Subsequent messages with 5 and 10 request_uncompressed_size are treated by server that it can send messages for at most 15 bytes.
     *
     * Generated from protobuf field <code>int64 request_uncompressed_size = 1;</code>
     * @return int|string
     */
    public function getRequestUncompressedSize()
    {
        return $this->request_uncompressed_size;
    }

    /**
     * Client acquired this amount of free bytes more for buffer. Server can send more data at most of this uncompressed size.
     * Subsequent messages with 5 and 10 request_uncompressed_size are treated by server that it can send messages for at most 15 bytes.
     *
     * Generated from protobuf field <code>int64 request_uncompressed_size = 1;</code>
     * @param int|string $var
     * @return $this
     */
    public function setRequestUncompressedSize($var)
    {
        GPBUtil::checkInt64($var);
        $this->request_uncompressed_size = $var;

        return $this;
    }

}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(ReadRequest::class, \Ydb\PersQueue\V1\StreamingReadClientMessageNew_ReadRequest::class);

