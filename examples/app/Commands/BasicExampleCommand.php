<?php

namespace App\Commands;

use App\AppService;
use YdbPlatform\Ydb\YdbTable;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BasicExampleCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'basic_example_v1';

    /**
     * @var AppService
     */
    protected $appService;

    /**
     * @var YdbPlatform\Ydb\Ydb
     */
    protected $ydb;

    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct()
    {
        $this->appService = new AppService;

        $this->ydb = $this->appService->initYdb();

        parent::__construct();
    }


    protected function configure()
    {
        $this->setDescription('Run the Basic Example.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->runExample();

        return Command::SUCCESS;
    }

    /**
     * @param mixed $value
     */
    protected function print($value)
    {
        if (is_array($value))
        {
            $this->table($value);
        }
        else
        {
            $this->output->writeln($value);
        }
    }

    /**
     * @param array $value
     */
    protected function table($array)
    {
        if ($array)
        {
            $table = new Table($this->output);
            $table
                ->setHeaders(array_keys($array[0]))
                ->setRows($array)
            ;
            $table->render();
        }
    }

    protected function runExample()
    {
        $this->runQuery('Create tables',
            function() { $this->createTables(); });

        $this->runQuery('Describe table',
            function() { $this->describeTable('seasons'); });

        $this->runQuery('Fill tables with data',
            function() { $this->fillTablesWithData(); });

        $this->runQuery('Select simple transaction',
            function() { $this->selectSimple(); });

        $this->runQuery('Upsert simple transaction',
            function() { $this->upsertSimple(); });

        $this->runQuery('Bulk upsert',
            function() { $this->bulkUpsert(); });

        $this->runQuery('Select prepared',
            function() {
                $this->selectPrepared(2, 3, 7);
                $this->selectPrepared(2, 3, 8);
            });

        $this->runQuery('Explicit TCL',
            function() { $this->explicitTcl(2, 6, 1); });

        $this->runQuery('Select prepared',
            function() { $this->selectPrepared(2, 6, 1); });
    }

    /**
     * @param string $header
     * @param callable $closure
     */
    protected function runQuery($header, $closure)
    {
        $this->print('<info>> ' . $header . ':</info>');
        $this->print('');
        $closure();
        $this->print('');
    }

    protected function createTables()
    {
        $session = $this->ydb->table()->session();

        $session->createTable(
            'series',
            YdbTable::make()
                ->addColumn('series_id', 'UINT64')
                ->addColumn('title', 'UTF8')
                ->addColumn('series_info', 'UTF8')
                ->addColumn('release_date', 'UINT64')
                ->primaryKey('series_id')
        );

        $this->print('Table `series` has been created.');

        $session->createTable(
            'seasons',
            YdbTable::make()
                ->addColumn('series_id', 'UINT64')
                ->addColumn('season_id', 'UINT64')
                ->addColumn('title', 'UTF8')
                ->addColumn('first_aired', 'UINT64')
                ->addColumn('last_aired', 'UINT64')
                ->primaryKey(['series_id', 'season_id'])
        );

        $this->print('Table `seasons` has been created.');

        $session->createTable(
            'episodes',
            YdbTable::make()
                ->addColumn('series_id', 'UINT64')
                ->addColumn('season_id', 'UINT64')
                ->addColumn('episode_id', 'UINT64')
                ->addColumn('title', 'UTF8')
                ->addColumn('air_date', 'UINT64')
                ->primaryKey(['series_id', 'season_id', 'episode_id'])
        );

        $this->print('Table `episodes` has been created.');
    }

    /**
     * @param string $table
     */
    protected function describeTable($table)
    {
        $data = $this->ydb->table()->session()->describeTable($table);

        $columns = [];

        foreach ($data['columns'] as $column)
        {
            if (isset($column['type']['optionalType']['item']['typeId']))
            {
                $columns[] = [
                    'Name' => $column['name'],
                    'Type' => $column['type']['optionalType']['item']['typeId'],
                ];
            }
        }

        $this->print('Table `' . $table . '`');
        $this->print($columns);
        $this->print('');
        $this->print('Primary key: ' . implode(', ', (array)$data['primaryKey']));

        // print_r($columns);
    }

    protected function fillTablesWithData()
    {
        $session = $this->ydb->table()->session();

        $prepared_query = $session->prepare($this->getFillDataQuery());

        $session->transaction(function() use ($prepared_query) {
            $prepared_query->execute([
                'seriesData' => $this->getSeriesData(),
                'seasonsData' => $this->getSeasonsData(),
                'episodesData' => $this->getEpisodesData(),
            ]);
        });

        $this->print('Finished.');
    }

    protected function selectSimple()
    {
        $session = $this->ydb->table()->session();

        $result = $session->transaction(function($session) {
            return $session->query('
                $format = DateTime::Format("%Y-%m-%d");
                SELECT
                    series_id,
                    title,
                    $format(DateTime::FromSeconds(CAST(release_date AS Uint32))) AS release_date
                FROM series
                WHERE series_id = 1;');
        });
        $this->print($result->rows());
    }

    protected function upsertSimple()
    {
        $session = $this->ydb->table()->session();

        $session->transaction(function($session) {
            return $session->query('
                UPSERT INTO episodes (series_id, season_id, episode_id, title)
                VALUES (2, 6, 1, "TBD");');
        });

        $this->print('Finished.');
    }

    protected function bulkUpsert()
    {
        $table = $this->ydb->table();

        $table->bulkUpsert(
            'episodes',
            $this->getEpisodesDataForBulkUpsert(),
            [
                'series_id' => 'Uint64',
                'season_id' => 'Uint64',
                'episode_id' => 'Uint64',
                'title' => 'Utf8',
                'air_date' => 'Uint64',
            ]
        );

        $this->print('Finished.');
    }

    /**
     * @param int $series_id
     * @param int $season_id
     * @param int $episode_id
     */
    protected function selectPrepared($series_id, $season_id, $episode_id)
    {
        $session = $this->ydb->table()->session();

        $prepared_query = $session->prepare('
            DECLARE $series_id AS Uint64;
            DECLARE $season_id AS Uint64;
            DECLARE $episode_id AS Uint64;

            $format = DateTime::Format("%Y-%m-%d");
            SELECT
                title AS `Episode title`,
                $format(DateTime::FromSeconds(CAST(air_date AS Uint32))) AS `Air date`
            FROM episodes
            WHERE series_id = $series_id AND season_id = $season_id AND episode_id = $episode_id;');

        $result = $session->transaction(function($session) use ($prepared_query, $series_id, $season_id, $episode_id) {
            return $prepared_query->execute(compact(
                'series_id',
                'season_id',
                'episode_id'
            ));
        });

        $this->print($result->rows());
    }

    /**
     * @param int $series_id
     * @param int $season_id
     * @param int $episode_id
     */
    protected function explicitTcl($series_id, $season_id, $episode_id)
    {
        $session = $this->ydb->table()->session();

        $prepared_query = $session->prepare('
            DECLARE $today AS Uint64;
            DECLARE $series_id AS Uint64;
            DECLARE $season_id AS Uint64;
            DECLARE $episode_id AS Uint64;

            UPDATE episodes
            SET air_date = $today
            WHERE series_id = $series_id AND season_id = $season_id AND episode_id = $episode_id;');

        $session->beginTransaction();

        $today = strtotime('today');

        $prepared_query->execute(compact(
            'series_id',
            'season_id',
            'episode_id',
            'today'
        ));

        $session->commitTransaction();

        $this->print('Finished.');
    }


    /**
     * @param int $series_id
     * @param string $title
     * @param int $release_date
     * @param string $series_info
     */
    protected function newSeries($series_id, $title, $release_date, $series_info)
    {
        $release_date = strtotime($release_date);
        return compact('series_id', 'title', 'release_date', 'series_info');
    }

    /**
     * @return array
     */
    protected function getSeriesData()
    {
        return [
            $this->newSeries(
                1,
                'IT Crowd',
                '2006-02-03',
                'The IT Crowd is a British sitcom produced by Channel 4, written by Graham Linehan, produced by Ash Atalla and starring Chris O\'Dowd, Richard Ayoade, Katherine Parkinson, and Matt Berry.',
            ),
            $this->newSeries(
                2,
                'Silicon Valley',
                '2014-04-06',
                'Silicon Valley is an American comedy television series created by Mike Judge, John Altschuler and Dave Krinsky. The series focuses on five young men who founded a startup company in Silicon Valley.',
            ),
        ];
    }

    /**
     * @param int $series_id
     * @param int $season_id
     * @param string $title
     * @param int $first_aired
     * @param int $last_aired
     */
    protected function newSeason($series_id, $season_id, $title, $first_aired, $last_aired)
    {
        $first_aired = strtotime($first_aired);
        $last_aired = strtotime($last_aired);
        return compact('series_id', 'season_id', 'title', 'first_aired', 'last_aired');
    }

    /**
     * @return array
     */
    protected function getSeasonsData()
    {
        return [
            $this->newSeason(1, 1, 'Season 1', '2006-02-03', '2006-03-03'),
            $this->newSeason(1, 2, 'Season 2', '2007-08-24', '2007-09-28'),
            $this->newSeason(1, 3, 'Season 3', '2008-11-21', '2008-12-26'),
            $this->newSeason(1, 4, 'Season 4', '2010-06-25', '2010-07-30'),
            $this->newSeason(2, 1, 'Season 1', '2014-04-06', '2014-06-01'),
            $this->newSeason(2, 2, 'Season 2', '2015-04-12', '2015-06-14'),
            $this->newSeason(2, 3, 'Season 3', '2016-04-24', '2016-06-26'),
            $this->newSeason(2, 4, 'Season 4', '2017-04-23', '2017-06-25'),
            $this->newSeason(2, 5, 'Season 5', '2018-03-25', '2018-05-13'),
        ];
    }

    /**
     * @param int $series_id
     * @param int $season_id
     * @param int $episode_id
     * @param string $title
     * @param int $air_date
     */
    protected function newEpisode($series_id, $season_id, $episode_id, $title, $air_date)
    {
        $air_date = strtotime($air_date);
        return compact('series_id', 'season_id', 'episode_id', 'title', 'air_date');
    }

    /**
     * @return array
     */
    protected function getEpisodesData()
    {
        return [
            $this->newEpisode(1, 1, 1, 'Yesterday\'s Jam', '2006-02-03'),
            $this->newEpisode(1, 1, 2, 'Calamity Jen', '2006-02-03'),
            $this->newEpisode(1, 1, 3, 'Fifty-Fifty', '2006-02-10'),
            $this->newEpisode(1, 1, 4, 'The Red Door', '2006-02-17'),
            $this->newEpisode(1, 1, 5, 'The Haunting of Bill Crouse', '2006-02-24'),
            $this->newEpisode(1, 1, 6, 'Aunt Irma Visits', '2006-03-03'),
            $this->newEpisode(1, 2, 1, 'The Work Outing', '2006-08-24'),
            $this->newEpisode(1, 2, 2, 'Return of the Golden Child', '2007-08-31'),
            $this->newEpisode(1, 2, 3, 'Moss and the German', '2007-09-07'),
            $this->newEpisode(1, 2, 4, 'The Dinner Party', '2007-09-14'),
            $this->newEpisode(1, 2, 5, 'Smoke and Mirrors', '2007-09-21'),
            $this->newEpisode(1, 2, 6, 'Men Without Women', '2007-09-28'),
            $this->newEpisode(1, 3, 1, 'From Hell', '2008-11-21'),
            $this->newEpisode(1, 3, 2, 'Are We Not Men?', '2008-11-28'),
            $this->newEpisode(1, 3, 3, 'Tramps Like Us', '2008-12-05'),
            $this->newEpisode(1, 3, 4, 'The Speech', '2008-12-12'),
            $this->newEpisode(1, 3, 5, 'Friendface', '2008-12-19'),
            $this->newEpisode(1, 3, 6, 'Calendar Geeks', '2008-12-26'),
            $this->newEpisode(1, 4, 1, 'Jen The Fredo', '2010-06-25'),
            $this->newEpisode(1, 4, 2, 'The Final Countdown', '2010-07-02'),
            $this->newEpisode(1, 4, 3, 'Something Happened', '2010-07-09'),
            $this->newEpisode(1, 4, 4, 'Italian For Beginners', '2010-07-16'),
            $this->newEpisode(1, 4, 5, 'Bad Boys', '2010-07-23'),
            $this->newEpisode(1, 4, 6, 'Reynholm vs Reynholm', '2010-07-30'),
        ];
    }

    /**
     * @return array
     */
    protected function getEpisodesDataForBulkUpsert()
    {
        return [
            $this->newEpisode(2, 1, 1, 'Minimum Viable Product', '2014-04-06'),
            $this->newEpisode(2, 1, 2, 'The Cap Table', '2014-04-13'),
            $this->newEpisode(2, 1, 3, 'Articles of Incorporation', '2014-04-20'),
            $this->newEpisode(2, 1, 4, 'Fiduciary Duties', '2014-04-27'),
            $this->newEpisode(2, 1, 5, 'Signaling Risk', '2014-05-04'),
            $this->newEpisode(2, 1, 6, 'Third Party Insourcing', '2014-05-11'),
            $this->newEpisode(2, 1, 7, 'Proof of Concept', '2014-05-18'),
            $this->newEpisode(2, 1, 8, 'Optimal Tip-to-Tip Efficiency', '2014-06-01'),
            $this->newEpisode(2, 2, 1, 'Sand Hill Shuffle', '2015-04-12'),
            $this->newEpisode(2, 2, 2, 'Runaway Devaluation', '2015-04-19'),
            $this->newEpisode(2, 2, 3, 'Bad Money', '2015-04-26'),
            $this->newEpisode(2, 2, 4, 'The Lady', '2015-05-03'),
            $this->newEpisode(2, 2, 5, 'Server Space', '2015-05-10'),
            $this->newEpisode(2, 2, 6, 'Homicide', '2015-05-17'),
            $this->newEpisode(2, 2, 7, 'Adult Content', '2015-05-24'),
            $this->newEpisode(2, 2, 8, 'White Hat/Black Hat', '2015-05-31'),
            $this->newEpisode(2, 2, 9, 'Binding Arbitration', '2015-06-07'),
            $this->newEpisode(2, 2, 10, 'Two Days of the Condor', '2015-06-14'),
            $this->newEpisode(2, 3, 1, 'Founder Friendly', '2016-04-24'),
            $this->newEpisode(2, 3, 2, 'Two in the Box', '2016-05-01'),
            $this->newEpisode(2, 3, 3, 'Meinertzhagen\'s Haversack', '2016-05-08'),
            $this->newEpisode(2, 3, 4, 'Maleant Data Systems Solutions', '2016-05-15'),
            $this->newEpisode(2, 3, 5, 'The Empty Chair', '2016-05-22'),
            $this->newEpisode(2, 3, 6, 'Bachmanity Insanity', '2016-05-29'),
            $this->newEpisode(2, 3, 7, 'To Build a Better Beta', '2016-06-05'),
            $this->newEpisode(2, 3, 8, 'Bachman\'s Earnings Over-Ride', '2016-06-12'),
            $this->newEpisode(2, 3, 9, 'Daily Active Users', '2016-06-19'),
            $this->newEpisode(2, 3, 10, 'The Uptick', '2016-06-26'),
            $this->newEpisode(2, 4, 1, 'Success Failure', '2017-04-23'),
            $this->newEpisode(2, 4, 2, 'Terms of Service', '2017-04-30'),
            $this->newEpisode(2, 4, 3, 'Intellectual Property', '2017-05-07'),
            $this->newEpisode(2, 4, 4, 'Teambuilding Exercise', '2017-05-14'),
            $this->newEpisode(2, 4, 5, 'The Blood Boy', '2017-05-21'),
            $this->newEpisode(2, 4, 6, 'Customer Service', '2017-05-28'),
            $this->newEpisode(2, 4, 7, 'The Patent Troll', '2017-06-04'),
            $this->newEpisode(2, 4, 8, 'The Keenan Vortex', '2017-06-11'),
            $this->newEpisode(2, 4, 9, 'Hooli-Con', '2017-06-18'),
            $this->newEpisode(2, 4, 10, 'Server Error', '2017-06-25'),
            $this->newEpisode(2, 5, 1, 'Grow Fast or Die Slow', '2018-03-25'),
            $this->newEpisode(2, 5, 2, 'Reorientation', '2018-04-01'),
            $this->newEpisode(2, 5, 3, 'Chief Operating Officer', '2018-04-08'),
            $this->newEpisode(2, 5, 4, 'Tech Evangelist', '2018-04-15'),
            $this->newEpisode(2, 5, 5, 'Facial Recognition', '2018-04-22'),
            $this->newEpisode(2, 5, 6, 'Artificial Emotional Intelligence', '2018-04-29'),
            $this->newEpisode(2, 5, 7, 'Initial Coin Offering', '2018-05-06'),
            $this->newEpisode(2, 5, 8, 'Fifty-One Percent', '2018-05-13'),
        ];
    }


    /**
     * @return string
     */
    protected function getFillDataQuery()
    {
        return <<<'EOT'
DECLARE $seriesData AS List<Struct<
    series_id: Uint64,
    title: Utf8,
    series_info: Utf8,
    release_date: Uint64>>;
DECLARE $seasonsData AS List<Struct<
    series_id: Uint64,
    season_id: Uint64,
    title: Utf8,
    first_aired: Uint64,
    last_aired: Uint64>>;
DECLARE $episodesData AS List<Struct<
    series_id: Uint64,
    season_id: Uint64,
    episode_id: Uint64,
    title: Utf8,
    air_date: Uint64>>;
REPLACE INTO series
SELECT
    series_id,
    title,
    series_info,
    release_date
FROM AS_TABLE($seriesData);
REPLACE INTO seasons
SELECT
    series_id,
    season_id,
    title,
    first_aired,
    last_aired
FROM AS_TABLE($seasonsData);
REPLACE INTO episodes
SELECT
    series_id,
    season_id,
    episode_id,
    title,
    air_date
FROM AS_TABLE($episodesData);
EOT;
    }

}
