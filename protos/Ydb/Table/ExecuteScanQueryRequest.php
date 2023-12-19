<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: protos/ydb_table.proto

namespace Ydb\Table;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Ydb.Table.ExecuteScanQueryRequest</code>
 */
class ExecuteScanQueryRequest extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.Ydb.Table.Query query = 3;</code>
     */
    protected $query = null;
    /**
     * Generated from protobuf field <code>map<string, .Ydb.TypedValue> parameters = 4;</code>
     */
    private $parameters;
    /**
     * Generated from protobuf field <code>.Ydb.Table.ExecuteScanQueryRequest.Mode mode = 6;</code>
     */
    protected $mode = 3;
    /**
     * Generated from protobuf field <code>.Ydb.Table.QueryStatsCollection.Mode collect_stats = 8;</code>
     */
    protected $collect_stats = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \Ydb\Table\Query $query
     *     @type array|\Google\Protobuf\Internal\MapField $parameters
     *     @type int $mode
     *     @type int $collect_stats
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Protos\YdbTable::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.Query query = 3;</code>
     * @return \Ydb\Table\Query|null
     */
    public function getQuery()
    {
        return $this->query;
    }

    public function hasQuery()
    {
        return isset($this->query);
    }

    public function clearQuery()
    {
        unset($this->query);
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.Query query = 3;</code>
     * @param \Ydb\Table\Query $var
     * @return $this
     */
    public function setQuery($var)
    {
        GPBUtil::checkMessage($var, \Ydb\Table\Query::class);
        $this->query = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>map<string, .Ydb.TypedValue> parameters = 4;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Generated from protobuf field <code>map<string, .Ydb.TypedValue> parameters = 4;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setParameters($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::MESSAGE, \Ydb\TypedValue::class);
        $this->parameters = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.ExecuteScanQueryRequest.Mode mode = 6;</code>
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.ExecuteScanQueryRequest.Mode mode = 6;</code>
     * @param int $var
     * @return $this
     */
    public function setMode($var)
    {
        GPBUtil::checkEnum($var, \Ydb\Table\ExecuteScanQueryRequest\Mode::class);
        $this->mode = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.QueryStatsCollection.Mode collect_stats = 8;</code>
     * @return int
     */
    public function getCollectStats()
    {
        return $this->collect_stats;
    }

    /**
     * Generated from protobuf field <code>.Ydb.Table.QueryStatsCollection.Mode collect_stats = 8;</code>
     * @param int $var
     * @return $this
     */
    public function setCollectStats($var)
    {
        GPBUtil::checkEnum($var, \Ydb\Table\QueryStatsCollection\Mode::class);
        $this->collect_stats = $var;

        return $this;
    }

}

