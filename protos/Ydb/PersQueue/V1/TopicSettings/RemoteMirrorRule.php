<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_persqueue_v1.proto

namespace Ydb\PersQueue\V1\TopicSettings;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Message for remote mirror rule description.
 *
 * Generated from protobuf message <code>Ydb.PersQueue.V1.TopicSettings.RemoteMirrorRule</code>
 */
class RemoteMirrorRule extends \Google\Protobuf\Internal\Message
{
    /**
     * Source cluster endpoint in format server:port.
     *
     * Generated from protobuf field <code>string endpoint = 1;</code>
     */
    protected $endpoint = '';
    /**
     * Source topic that we want to mirror.
     *
     * Generated from protobuf field <code>string topic_path = 2;</code>
     */
    protected $topic_path = '';
    /**
     * Source consumer for reading source topic.
     *
     * Generated from protobuf field <code>string consumer_name = 3;</code>
     */
    protected $consumer_name = '';
    /**
     * Credentials for reading source topic by source consumer.
     *
     * Generated from protobuf field <code>.Ydb.PersQueue.V1.Credentials credentials = 4;</code>
     */
    protected $credentials = null;
    /**
     * All messages with smaller timestamp of write will be skipped.
     *
     * Generated from protobuf field <code>int64 starting_message_timestamp_ms = 5 [(.Ydb.value) = ">= 0"];</code>
     */
    protected $starting_message_timestamp_ms = 0;
    /**
     * Database
     *
     * Generated from protobuf field <code>string database = 6;</code>
     */
    protected $database = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $endpoint
     *           Source cluster endpoint in format server:port.
     *     @type string $topic_path
     *           Source topic that we want to mirror.
     *     @type string $consumer_name
     *           Source consumer for reading source topic.
     *     @type \Ydb\PersQueue\V1\Credentials $credentials
     *           Credentials for reading source topic by source consumer.
     *     @type int|string $starting_message_timestamp_ms
     *           All messages with smaller timestamp of write will be skipped.
     *     @type string $database
     *           Database
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbPersqueueV1::initOnce();
        parent::__construct($data);
    }

    /**
     * Source cluster endpoint in format server:port.
     *
     * Generated from protobuf field <code>string endpoint = 1;</code>
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Source cluster endpoint in format server:port.
     *
     * Generated from protobuf field <code>string endpoint = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setEndpoint($var)
    {
        GPBUtil::checkString($var, True);
        $this->endpoint = $var;

        return $this;
    }

    /**
     * Source topic that we want to mirror.
     *
     * Generated from protobuf field <code>string topic_path = 2;</code>
     * @return string
     */
    public function getTopicPath()
    {
        return $this->topic_path;
    }

    /**
     * Source topic that we want to mirror.
     *
     * Generated from protobuf field <code>string topic_path = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setTopicPath($var)
    {
        GPBUtil::checkString($var, True);
        $this->topic_path = $var;

        return $this;
    }

    /**
     * Source consumer for reading source topic.
     *
     * Generated from protobuf field <code>string consumer_name = 3;</code>
     * @return string
     */
    public function getConsumerName()
    {
        return $this->consumer_name;
    }

    /**
     * Source consumer for reading source topic.
     *
     * Generated from protobuf field <code>string consumer_name = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setConsumerName($var)
    {
        GPBUtil::checkString($var, True);
        $this->consumer_name = $var;

        return $this;
    }

    /**
     * Credentials for reading source topic by source consumer.
     *
     * Generated from protobuf field <code>.Ydb.PersQueue.V1.Credentials credentials = 4;</code>
     * @return \Ydb\PersQueue\V1\Credentials
     */
    public function getCredentials()
    {
        return isset($this->credentials) ? $this->credentials : null;
    }

    public function hasCredentials()
    {
        return isset($this->credentials);
    }

    public function clearCredentials()
    {
        unset($this->credentials);
    }

    /**
     * Credentials for reading source topic by source consumer.
     *
     * Generated from protobuf field <code>.Ydb.PersQueue.V1.Credentials credentials = 4;</code>
     * @param \Ydb\PersQueue\V1\Credentials $var
     * @return $this
     */
    public function setCredentials($var)
    {
        GPBUtil::checkMessage($var, \Ydb\PersQueue\V1\Credentials::class);
        $this->credentials = $var;

        return $this;
    }

    /**
     * All messages with smaller timestamp of write will be skipped.
     *
     * Generated from protobuf field <code>int64 starting_message_timestamp_ms = 5 [(.Ydb.value) = ">= 0"];</code>
     * @return int|string
     */
    public function getStartingMessageTimestampMs()
    {
        return $this->starting_message_timestamp_ms;
    }

    /**
     * All messages with smaller timestamp of write will be skipped.
     *
     * Generated from protobuf field <code>int64 starting_message_timestamp_ms = 5 [(.Ydb.value) = ">= 0"];</code>
     * @param int|string $var
     * @return $this
     */
    public function setStartingMessageTimestampMs($var)
    {
        GPBUtil::checkInt64($var);
        $this->starting_message_timestamp_ms = $var;

        return $this;
    }

    /**
     * Database
     *
     * Generated from protobuf field <code>string database = 6;</code>
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Database
     *
     * Generated from protobuf field <code>string database = 6;</code>
     * @param string $var
     * @return $this
     */
    public function setDatabase($var)
    {
        GPBUtil::checkString($var, True);
        $this->database = $var;

        return $this;
    }

}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(RemoteMirrorRule::class, \Ydb\PersQueue\V1\TopicSettings_RemoteMirrorRule::class);

