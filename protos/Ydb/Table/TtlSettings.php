<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_table.proto

namespace Ydb\Table;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Ydb.Table.TtlSettings</code>
 */
class TtlSettings extends \Google\Protobuf\Internal\Message
{
    protected $mode;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Ydb\Table\DateTypeColumnModeSettings $date_type_column
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbTable::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.DateTypeColumnModeSettings date_type_column = 1;</code>
     * @return \Ydb\Table\DateTypeColumnModeSettings
     */
    public function getDateTypeColumn()
    {
        return $this->readOneof(1);
    }

    public function hasDateTypeColumn()
    {
        return $this->hasOneof(1);
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.DateTypeColumnModeSettings date_type_column = 1;</code>
     * @param \Ydb\Table\DateTypeColumnModeSettings $var
     * @return $this
     */
    public function setDateTypeColumn($var)
    {
        GPBUtil::checkMessage($var, \Ydb\Table\DateTypeColumnModeSettings::class);
        $this->writeOneof(1, $var);

        return $this;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->whichOneof("mode");
    }

}

