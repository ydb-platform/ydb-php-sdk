<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Ydb\RateLimiter\V1;

/**
 * Service that implements distributed rate limiting.
 *
 * To use rate limiter functionality you need an existing coordination node.
 *
 */
class RateLimiterServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Create a new resource in existing coordination node.
     * @param \Ydb\RateLimiter\CreateResourceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateResource(\Ydb\RateLimiter\CreateResourceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.RateLimiter.V1.RateLimiterService/CreateResource',
        $argument,
        ['\Ydb\RateLimiter\CreateResourceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Update a resource in coordination node.
     * @param \Ydb\RateLimiter\AlterResourceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AlterResource(\Ydb\RateLimiter\AlterResourceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.RateLimiter.V1.RateLimiterService/AlterResource',
        $argument,
        ['\Ydb\RateLimiter\AlterResourceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Delete a resource from coordination node.
     * @param \Ydb\RateLimiter\DropResourceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DropResource(\Ydb\RateLimiter\DropResourceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.RateLimiter.V1.RateLimiterService/DropResource',
        $argument,
        ['\Ydb\RateLimiter\DropResourceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * List resources in given coordination node.
     * @param \Ydb\RateLimiter\ListResourcesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListResources(\Ydb\RateLimiter\ListResourcesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.RateLimiter.V1.RateLimiterService/ListResources',
        $argument,
        ['\Ydb\RateLimiter\ListResourcesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Describe properties of resource in coordination node.
     * @param \Ydb\RateLimiter\DescribeResourceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeResource(\Ydb\RateLimiter\DescribeResourceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.RateLimiter.V1.RateLimiterService/DescribeResource',
        $argument,
        ['\Ydb\RateLimiter\DescribeResourceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Take units for usage of a resource in coordination node.
     * @param \Ydb\RateLimiter\AcquireResourceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AcquireResource(\Ydb\RateLimiter\AcquireResourceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/Ydb.RateLimiter.V1.RateLimiterService/AcquireResource',
        $argument,
        ['\Ydb\RateLimiter\AcquireResourceResponse', 'decode'],
        $metadata, $options);
    }

}
