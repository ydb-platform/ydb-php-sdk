<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_query_stats.proto

namespace Ydb\TableStats;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Ydb.TableStats.QueryPhaseStats</code>
 */
class QueryPhaseStats extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>uint64 duration_us = 1;</code>
     */
    protected $duration_us = 0;
    /**
     * Generated from protobuf field <code>repeated .Ydb.TableStats.TableAccessStats table_access = 2;</code>
     */
    private $table_access;
    /**
     * Generated from protobuf field <code>uint64 cpu_time_us = 3;</code>
     */
    protected $cpu_time_us = 0;
    /**
     * Generated from protobuf field <code>uint64 affected_shards = 4;</code>
     */
    protected $affected_shards = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int|string $duration_us
     *     @type \Ydb\TableStats\TableAccessStats[]|\Google\Protobuf\Internal\RepeatedField $table_access
     *     @type int|string $cpu_time_us
     *     @type int|string $affected_shards
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbQueryStats::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>uint64 duration_us = 1;</code>
     * @return int|string
     */
    public function getDurationUs()
    {
        return $this->duration_us;
    }

    /**
     * Generated from protobuf field <code>uint64 duration_us = 1;</code>
     * @param int|string $var
     * @return $this
     */
    public function setDurationUs($var)
    {
        GPBUtil::checkUint64($var);
        $this->duration_us = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>repeated .Ydb.TableStats.TableAccessStats table_access = 2;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getTableAccess()
    {
        return $this->table_access;
    }

    /**
     * Generated from protobuf field <code>repeated .Ydb.TableStats.TableAccessStats table_access = 2;</code>
     * @param \Ydb\TableStats\TableAccessStats[]|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setTableAccess($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Ydb\TableStats\TableAccessStats::class);
        $this->table_access = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 cpu_time_us = 3;</code>
     * @return int|string
     */
    public function getCpuTimeUs()
    {
        return $this->cpu_time_us;
    }

    /**
     * Generated from protobuf field <code>uint64 cpu_time_us = 3;</code>
     * @param int|string $var
     * @return $this
     */
    public function setCpuTimeUs($var)
    {
        GPBUtil::checkUint64($var);
        $this->cpu_time_us = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint64 affected_shards = 4;</code>
     * @return int|string
     */
    public function getAffectedShards()
    {
        return $this->affected_shards;
    }

    /**
     * Generated from protobuf field <code>uint64 affected_shards = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setAffectedShards($var)
    {
        GPBUtil::checkUint64($var);
        $this->affected_shards = $var;

        return $this;
    }

}

