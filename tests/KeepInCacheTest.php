<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Session;

class KeepInCacheTest extends TestCase
{
    protected $sampleParams = ["x", 1];

    public function testPreparedQueryWithParamsWithoutConfig()
    {
        self::assertEquals(true, YdbQuery::isNeedSetKeepQueryInCache(null, $this->sampleParams));
    }

    public function testPreparedQueryWithParamsWithConfig()
    {
        self::assertEquals(false, YdbQuery::isNeedSetKeepQueryInCache(false, $this->sampleParams));
    }

    public function testPreparedQueryWithoutParamsWithoutConfig()
    {
        self::assertEquals(false, YdbQuery::isNeedSetKeepQueryInCache(null, null));
    }

    public function testPreparedQueryWithoutParamsWithConfig()
    {
        self::assertEquals(false, YdbQuery::isNeedSetKeepQueryInCache(null, null));
    }
}
class YdbQuery extends \YdbPlatform\Ydb\YdbQuery{
    public static function isNeedSetKeepQueryInCache(?bool $currentParams, ?array $queryDeclaredParams): bool
    {
        return parent::isNeedSetKeepQueryInCache($currentParams, $queryDeclaredParams);
    }
}
