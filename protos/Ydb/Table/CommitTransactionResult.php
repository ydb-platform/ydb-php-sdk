<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: kikimr/public/api/protos/ydb_table.proto

namespace Ydb\Table;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Ydb.Table.CommitTransactionResult</code>
 */
class CommitTransactionResult extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.Ydb.TableStats.QueryStats query_stats = 1;</code>
     */
    protected $query_stats = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Ydb\TableStats\QueryStats $query_stats
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Kikimr\PBPublic\Api\Protos\YdbTable::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.Ydb.TableStats.QueryStats query_stats = 1;</code>
     * @return \Ydb\TableStats\QueryStats
     */
    public function getQueryStats()
    {
        return isset($this->query_stats) ? $this->query_stats : null;
    }

    public function hasQueryStats()
    {
        return isset($this->query_stats);
    }

    public function clearQueryStats()
    {
        unset($this->query_stats);
    }

    /**
     * Generated from protobuf field <code>.Ydb.TableStats.QueryStats query_stats = 1;</code>
     * @param \Ydb\TableStats\QueryStats $var
     * @return $this
     */
    public function setQueryStats($var)
    {
        GPBUtil::checkMessage($var, \Ydb\TableStats\QueryStats::class);
        $this->query_stats = $var;

        return $this;
    }

}

