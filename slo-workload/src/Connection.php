<?php

namespace YdbPlatform\Ydb\Slo;

/**
 * Where the workload connects to.
 */
class Connection
{
    /** @var string endpoint, e.g. `grpc://ydb:2136` */
    public $endpoint;
    /** @var string database path, e.g. `/Root/testdb` */
    public $database;

    public function __construct(string $endpoint, string $database)
    {
        $this->endpoint = $endpoint;
        $this->database = $database;
    }
}
