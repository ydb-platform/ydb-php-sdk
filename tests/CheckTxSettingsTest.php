<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use Ydb\Table\OnlineModeSettings;
use Ydb\Table\SerializableModeSettings;
use Ydb\Table\SnapshotModeSettings;
use Ydb\Table\StaleModeSettings;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Ydb;
use function YdbPlatform\Ydb\parseTxMode;

require_once '../src/Session.php';

class CheckTxSettingsTest extends TestCase
{

    public function testParseTxMode(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $config = [

            // Database path
            'database'    => '/local',

            // Database endpoint
            'endpoint'    => 'localhost:2136',

            // Auto discovery (dedicated server only)
            'discovery'   => false,

            // IAM config
            'iam_config'  => [
                'insecure' => true,
            ],
            'credentials' => new AnonymousAuthentication()
        ];
        $ydb = new Ydb($config, new SimpleStdLogger(SimpleStdLogger::DEBUG));
        $table = $ydb->table();
        $session = $table->createSession();

        $testsQuery = [
            ['mode' => 'stale_read_only', 'result' => ['stale_read_only' => new StaleModeSettings], 'interactive' => false],
            ['mode' => 'stale', 'result' => ['stale_read_only' => new StaleModeSettings], 'interactive' => false],
            ['mode' => 'online_read_only', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => false,
            ])], 'interactive' => false],
            ['mode' => 'online', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => false,
            ])], 'interactive' => false],
            ['mode' => 'inconsistent_reads', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => true,
            ])], 'interactive' => false],
            ['mode' => 'online_inconsistent', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => true,
            ])], 'interactive' => false],
            ['mode' => 'online_inconsistent_reads', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => true,
            ])], 'interactive' => false],
            ['mode' => 'snapshot', 'result' => ['snapshot_read_only' => new SnapshotModeSettings], 'interactive' => true],
            ['mode' => 'snapshot_read_only', 'result' => ['snapshot_read_only' => new SnapshotModeSettings], 'interactive' => true],
            ['mode' => 'serializable', 'result' => ['serializable_read_write' => new SerializableModeSettings], 'interactive' => true],
            ['mode' => 'serializable_read_write', 'result' => ['serializable_read_write' => new SerializableModeSettings], 'interactive' => true],
        ];
        foreach ($testsQuery as $i => $test){
            self::assertEquals($test["result"], parseTxMode($test["mode"]));
            $query= $session->newQuery("SELECT 1;")
                ->beginTx($test['mode']);
            $query->execute();
            if ($test['interactive']){
                $table->transaction(function (){}, $test['mode']);
            }
        }
    }
}
