<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Session;

class KeepInCacheTest extends TestCase
{
    protected $sampleParams = ["x", 1];

    public function testIsNeedSetKeepQueryInCache()
    {
        $tests = [
            ["flag" => null, "params" => null, "result" => false],
            ["flag" => null, "params" => [], "result" => false],
            ["flag" => null, "params" => $this->sampleParams, "result" => true],
            ["flag" => false, "params" => null, "result" => false],
            ["flag" => false, "params" => [], "result" => false],
            ["flag" => false, "params" => $this->sampleParams, "result" => false],
            ["flag" => true, "params" => null, "result" => true],
            ["flag" => true, "params" => [], "result" => true],
            ["flag" => true, "params" => $this->sampleParams, "result" => true],
        ];
        foreach ($tests as $i => $test){
            self::assertEquals($test["result"], YdbQuery::isNeedSetKeepQueryInCache($test["flag"], $test["params"]));
        }
    }
}
class YdbQuery extends \YdbPlatform\Ydb\YdbQuery{
    public static function isNeedSetKeepQueryInCache(?bool $userFlag, ?array $queryDeclaredParams): bool
    {
        return parent::isNeedSetKeepQueryInCache($userFlag, $queryDeclaredParams);
    }
}
