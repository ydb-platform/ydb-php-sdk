<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use Ydb\Table\OnlineModeSettings;
use Ydb\Table\SnapshotModeSettings;
use Ydb\Table\StaleModeSettings;

class CheckTxSettingsTest extends TestCase
{

    protected function testCheckParseTxModeTes()
    {
        $tests = [
            ['mode' => 'stale_read_only', 'result' => ['stale_read_only' => new StaleModeSettings]],
            ['mode' => 'stale', 'result' => ['stale_read_only' => new StaleModeSettings]],
            ['mode' => 'online_read_only', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => false,
            ])]],
            ['mode' => 'online', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => false,
            ])]],
            ['mode' => 'inconsistent_reads', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => true,
            ])]],
            ['mode' => 'online_inconsistent', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => true,
            ])]],
            ['mode' => 'online_inconsistent_read', 'result' => ['online_read_only' => new OnlineModeSettings([
                'allow_inconsistent_reads' => true,
            ])]],
            ['mode' => 'snapshot', 'result' => ['snapshot_read_only' => new SnapshotModeSettings]],
            ['mode' => 'snapshot_read_only', 'result' => ['snapshot_read_only' => new SnapshotModeSettings]],
        ];
        foreach ($tests as $i => $test){
            self::assertEquals($test["result"], Session::parseTxMode($test["mode"]));
        }
        self::expectException('Exception');
        Session::parseTxMode(null);
    }
}
class Session extends \YdbPlatform\Ydb\Session {
    public static function parseTxMode(string $mode): array
    {
        return parent::parseTxMode($mode);
    }
}
