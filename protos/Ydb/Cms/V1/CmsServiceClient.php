<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Cms\V1;

/**
 * CMS stands for Cluster Management System. CmsService provides some
 * functionality for managing cluster, i.e. managing YDB Database
 * instances for example.
 *
 */
class CmsServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Create a new database.
     * @param \Ydb\Cms\CreateDatabaseRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateDatabase(\Ydb\Cms\CreateDatabaseRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Cms.V1.CmsService/CreateDatabase',
        $argument,
        ['\Ydb\Cms\CreateDatabaseResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Get current database's status.
     * @param \Ydb\Cms\GetDatabaseStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetDatabaseStatus(\Ydb\Cms\GetDatabaseStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Cms.V1.CmsService/GetDatabaseStatus',
        $argument,
        ['\Ydb\Cms\GetDatabaseStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Alter database resources.
     * @param \Ydb\Cms\AlterDatabaseRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AlterDatabase(\Ydb\Cms\AlterDatabaseRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Cms.V1.CmsService/AlterDatabase',
        $argument,
        ['\Ydb\Cms\AlterDatabaseResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * List all databases.
     * @param \Ydb\Cms\ListDatabasesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListDatabases(\Ydb\Cms\ListDatabasesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Cms.V1.CmsService/ListDatabases',
        $argument,
        ['\Ydb\Cms\ListDatabasesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Remove database.
     * @param \Ydb\Cms\RemoveDatabaseRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RemoveDatabase(\Ydb\Cms\RemoveDatabaseRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Cms.V1.CmsService/RemoveDatabase',
        $argument,
        ['\Ydb\Cms\RemoveDatabaseResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Describe supported database options.
     * @param \Ydb\Cms\DescribeDatabaseOptionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeDatabaseOptions(\Ydb\Cms\DescribeDatabaseOptionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Cms.V1.CmsService/DescribeDatabaseOptions',
        $argument,
        ['\Ydb\Cms\DescribeDatabaseOptionsResponse', 'decode'],
        $metadata, $options);
    }

}
