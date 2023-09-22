<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Logger\SimpleStdLogger;
use YdbPlatform\Ydb\Ydb;

class CheckTxSettingsTest extends TestCase
{

    /**
     * @var Ydb
     */
    protected $ydb;
    /**
     * @var \YdbPlatform\Ydb\Table
     */
    protected $table;
    /**
     * @var \YdbPlatform\Ydb\Session|null
     */
    protected $session;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
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
        $this->ydb = new Ydb($config, new SimpleStdLogger(SimpleStdLogger::DEBUG));
        $this->table = $this->ydb->table();
        $this->session = $this->table->session();
    }

    public function testSerializableTxConfig(){
        $this->checkTx('serializable', 'serializable_read_write');
    }

    public function testSnapshotTxConfig(){
        $this->checkTx('snapshot', 'snapshot_read_only');
    }
    public function testStaleTxConfig(){
        $this->checkTx('stale', 'stale_read_only');
    }
    public function testOnlineTxConfig(){
        $this->checkTx('online', 'online_read_only');
    }

    protected function checkTx(string $mode, string $value)
    {
        $query= $this->session->newQuery("SELECT 1;")
            ->beginTx($mode);
        self::assertEquals($value, $query->getRequestData()['tx_control']->getBeginTx()->getTxMode());
        $query->execute();
    }
}
