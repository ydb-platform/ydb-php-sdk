<?php
namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\ReadFromJsonCredentials;
use YdbPlatform\Ydb\Ydb;
use YdbPlatform\Ydb\YdbTable;

class CheckReadTokenFileTest extends TestCase{
    public function testReadTokenFile(){
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            'credentials'  => new ReadFromJsonCredentials("./tests/token.json")
        ];

        $ydb = new Ydb($config);
        self::assertEquals('test-example-token',
            $ydb->iam()->token()
        );


    }

    private function getYdbResult(array $config) : array
    {
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
