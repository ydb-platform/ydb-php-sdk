<?php
namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Enums\CollectQueryStatsMode;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class QueryStatsTest extends TestCase{
    /**
     * @var \YdbPlatform\Ydb\Table
     */
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            // IAM config
            'iam_config'  => [
                'anonymous' => true,
                'insecure' => true,
            ],
        ];

        $ydb = new Ydb($config);
        $this->table = $ydb->table();
    }
    public function testGetNullQueryStatsWithBasicParameter(){
        $table = $this->table;

        $table->retryTransaction(function (Session $session){
             $result = $session->query('SELECT 1;', null, [
               'collectStats' => CollectQueryStatsMode::STATS_COLLECTION_BASIC
            ]);
             self::assertNotNull($result->getQueryStats());
        }, true);

    }
    public function testGetNullQueryStatsWithFullParameter(){
        $table = $this->table;

        $table->retryTransaction(function (Session $session){
             $result = $session->query('SELECT 1;', null, [
               'collectStats' => CollectQueryStatsMode::STATS_COLLECTION_FULL
            ]);
             self::assertNotNull($result->getQueryStats());
             self::assertNotEquals(0, count($result->getQueryStats()->getQueryPhases()));
            self::assertNotNull($result->getQueryStats()->getCompilation());
            self::assertNotEquals("", $result->getQueryStats()->getQueryPlan());
        }, true);

    }

    public function testGetNullQueryStatsWithoutParameter(){
        $this->table->retryTransaction(function (Session $session){
            $result = $session->query('SELECT 1;', null);
            self::assertNull($result->getQueryStats());
        }, true);
    }

    public function testGetNullQueryStatsWithNoneParameter(){
        $this->table->retryTransaction(function (Session $session){
            $result = $session->query('SELECT 1;', null, [
                'collectStats' => CollectQueryStatsMode::STATS_COLLECTION_NONE
            ]);
            self::assertNull($result->getQueryStats());
        }, true);
    }

}
