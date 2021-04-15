<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_clickhouse_internal.proto

namespace Ydb\ClickhouseInternal;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Ydb.ClickhouseInternal.GetShardLocationsRequest</code>
 */
class GetShardLocationsRequest extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.Ydb.Operations.OperationParams operation_params = 1;</code>
     */
    protected $operation_params = null;
    /**
     * Generated from protobuf field <code>repeated uint64 tablet_ids = 2;</code>
     */
    private $tablet_ids;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Ydb\Operations\OperationParams $operation_params
     *     @type int[]|string[]|\Google\Protobuf\Internal\RepeatedField $tablet_ids
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbClickhouseInternal::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.Ydb.Operations.OperationParams operation_params = 1;</code>
     * @return \Ydb\Operations\OperationParams
     */
    public function getOperationParams()
    {
        return isset($this->operation_params) ? $this->operation_params : null;
    }

    public function hasOperationParams()
    {
        return isset($this->operation_params);
    }

    public function clearOperationParams()
    {
        unset($this->operation_params);
    }

    /**
     * Generated from protobuf field <code>.Ydb.Operations.OperationParams operation_params = 1;</code>
     * @param \Ydb\Operations\OperationParams $var
     * @return $this
     */
    public function setOperationParams($var)
    {
        GPBUtil::checkMessage($var, \Ydb\Operations\OperationParams::class);
        $this->operation_params = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated uint64 tablet_ids = 2;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getTabletIds()
    {
        return $this->tablet_ids;
    }

    /**
     * Generated from protobuf field <code>repeated uint64 tablet_ids = 2;</code>
     * @param int[]|string[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setTabletIds($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::UINT64);
        $this->tablet_ids = $arr;

        return $this;
    }

}

