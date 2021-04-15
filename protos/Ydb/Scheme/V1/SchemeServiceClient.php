<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\Scheme\V1;

/**
 * Every YDB Database Instance has set of objects organized a tree.
 * SchemeService provides some functionality to browse and modify
 * this tree.
 *
 * SchemeService provides a generic tree functionality, to create specific
 * objects like YDB Table or Persistent Queue use corresponding services.
 *
 */
class SchemeServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Make Directory.
     * @param \Ydb\Scheme\MakeDirectoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function MakeDirectory(\Ydb\Scheme\MakeDirectoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scheme.V1.SchemeService/MakeDirectory',
        $argument,
        ['\Ydb\Scheme\MakeDirectoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Remove Directory.
     * @param \Ydb\Scheme\RemoveDirectoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RemoveDirectory(\Ydb\Scheme\RemoveDirectoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scheme.V1.SchemeService/RemoveDirectory',
        $argument,
        ['\Ydb\Scheme\RemoveDirectoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Returns information about given directory and objects inside it.
     * @param \Ydb\Scheme\ListDirectoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListDirectory(\Ydb\Scheme\ListDirectoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scheme.V1.SchemeService/ListDirectory',
        $argument,
        ['\Ydb\Scheme\ListDirectoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Returns information about object with given path.
     * @param \Ydb\Scheme\DescribePathRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribePath(\Ydb\Scheme\DescribePathRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scheme.V1.SchemeService/DescribePath',
        $argument,
        ['\Ydb\Scheme\DescribePathResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Modify permissions.
     * @param \Ydb\Scheme\ModifyPermissionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ModifyPermissions(\Ydb\Scheme\ModifyPermissionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.Scheme.V1.SchemeService/ModifyPermissions',
        $argument,
        ['\Ydb\Scheme\ModifyPermissionsResponse', 'decode'],
        $metadata, $options);
    }

}
