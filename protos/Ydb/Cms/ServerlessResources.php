<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_cms.proto

namespace Ydb\Cms;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Ydb.Cms.ServerlessResources</code>
 */
class ServerlessResources extends \Google\Protobuf\Internal\Message
{
    /**
     * Full path to shared database's home dir whose resources will be used.
     *
     * Generated from protobuf field <code>string shared_database_path = 1;</code>
     */
    protected $shared_database_path = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $shared_database_path
     *           Full path to shared database's home dir whose resources will be used.
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbCms::initOnce();
        parent::__construct($data);
    }

    /**
     * Full path to shared database's home dir whose resources will be used.
     *
     * Generated from protobuf field <code>string shared_database_path = 1;</code>
     * @return string
     */
    public function getSharedDatabasePath()
    {
        return $this->shared_database_path;
    }

    /**
     * Full path to shared database's home dir whose resources will be used.
     *
     * Generated from protobuf field <code>string shared_database_path = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setSharedDatabasePath($var)
    {
        GPBUtil::checkString($var, True);
        $this->shared_database_path = $var;

        return $this;
    }

}

