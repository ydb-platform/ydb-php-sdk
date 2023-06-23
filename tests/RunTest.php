<?php
namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class RunTest extends TestCase{
    public function testAnonymousConnection(){
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


        self::assertEquals(
            [1, 6, 1, 2, "TBD"],
            $this->getYdbResult($config)
        );

    }

    private function getYdbResult(array $config) : array
    {
        $ydb = new Ydb($config);
        $table = $ydb->table();

        $session = $table->createSession();

        $session->createTable(
            'episodes',
            YdbTable::make()
                ->addColumn('series_id', 'UINT64')
                ->addColumn('title', 'UTF8')
                ->addColumn('episode_id', 'UINT64')
                ->addColumn('season_id', 'UINT64')
                ->primaryKey('series_id')
        );

        $session->transaction(function($session) {
            return $session->query('
        UPSERT INTO `episodes` (series_id, season_id, episode_id, title)
        VALUES (2, 6, 1, "TBD");');
        });

        $result = $session->query('select `series_id`, `season_id`, `episode_id`, `title` from `episodes`;');
        return [$result->rowCount(), $result->rows()[0]["season_id"],
            $result->rows()[0]["episode_id"],
            $result->rows()[0]["series_id"],
            $result->rows()[0]["title"]];
    }
}
