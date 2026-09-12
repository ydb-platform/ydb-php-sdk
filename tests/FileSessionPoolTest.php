<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Retry\Retry;
use YdbPlatform\Ydb\Sessions\FileSessionPool;
use YdbPlatform\Ydb\Ydb;

// FileSessionPool wasn't multiprocess-safe: no locking, no atomic write. See ydb-platform/ydb-php-sdk#53.
class FileSessionPoolTest extends TestCase
{
    private function makeTable()
    {
        $config = [
            'database' => '/local',
            'endpoint' => 'localhost:2136',
            'discovery' => false,
            'iam_config' => ['insecure' => true],
            'credentials' => new AnonymousAuthentication(),
        ];

        return (new Ydb($config))->table();
    }

    private function makeRetry(): Retry
    {
        $logger = new class extends \Psr\Log\AbstractLogger {
            public function log($level, $message, array $context = []): void
            {
            }
        };
        return new Retry($logger);
    }

    private function seedPool(string $poolFile, int $count): void
    {
        $sessions = [];
        for ($i = 0; $i < $count; $i++) {
            $sessions[] = ['id' => 'fake-session-' . $i, 'taken' => false];
        }
        file_put_contents($poolFile, json_encode($sessions));
    }

    public function testGetIdleSessionSkipsAlreadyTakenSessions(): void
    {
        $poolFile = tempnam(sys_get_temp_dir(), 'ydb-pool-');
        $this->seedPool($poolFile, 2);

        $table = $this->makeTable();
        $retry = $this->makeRetry();
        $pool = new FileSessionPool(['table' => $table, 'filepath' => $poolFile], $retry);
        $table->sessionPool($pool);

        $first = $pool->getIdleSession();
        self::assertNotNull($first);
        self::assertTrue($first->isBusy(), 'getIdleSession() must persist the taken mark itself, not rely on a later take() call.');

        $second = $pool->getIdleSession();
        self::assertNotNull($second);
        self::assertNotSame($first->id(), $second->id());

        self::assertNull($pool->getIdleSession(), 'both sessions are now taken - nothing left to return.');

        unlink($poolFile);
    }

    public function testLoadDoesNotCrashOnATruncatedFile(): void
    {
        $poolFile = tempnam(sys_get_temp_dir(), 'ydb-pool-');
        file_put_contents($poolFile, '');

        $table = $this->makeTable();
        $retry = $this->makeRetry();
        $pool = new FileSessionPool(['table' => $table, 'filepath' => $poolFile], $retry);
        $table->sessionPool($pool);

        self::assertNull($pool->getIdleSession());

        unlink($poolFile);
    }

    // Two real, separate processes race to drain the same pool - reproduces #53 directly.
    public function testConcurrentProcessesNeverTakeTheSameSessionTwice(): void
    {
        $poolFile = tempnam(sys_get_temp_dir(), 'ydb-pool-');
        $resultsFile = tempnam(sys_get_temp_dir(), 'ydb-pool-results-');
        $workerScript = tempnam(sys_get_temp_dir(), 'ydb-pool-worker-') . '.php';

        $sessionCount = 40;
        $this->seedPool($poolFile, $sessionCount);
        file_put_contents($resultsFile, '');
        file_put_contents($workerScript, $this->workerScriptSource());

        $autoloadPath = __DIR__ . '/../vendor/autoload.php';

        // Real files, not pipes - a full OS pipe buffer would deadlock this wait loop.
        $processes = [];
        $stderrPaths = [];
        foreach (['A', 'B'] as $tag) {
            $stderrPaths[$tag] = tempnam(sys_get_temp_dir(), 'ydb-pool-stderr-');
            $descriptors = [
                1 => ['file', $stderrPaths[$tag], 'w'],
                2 => ['file', $stderrPaths[$tag], 'w'],
            ];
            // Array command support in proc_open() needs PHP 7.4+ - this SDK's
            // stated minimum is 7.2, so build an escaped string command instead.
            $cmd = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $workerScript, $autoloadPath, $tag, $poolFile, $resultsFile]));
            $proc = proc_open($cmd, $descriptors, $pipes);
            self::assertIsResource($proc, "failed to start worker $tag");
            $processes[$tag] = $proc;
        }

        foreach ($processes as $tag => $proc) {
            $status = proc_close($proc);
            $stderr = file_get_contents($stderrPaths[$tag]);
            unlink($stderrPaths[$tag]);
            self::assertSame(0, $status, "worker $tag exited with status $status, output: $stderr");
        }

        $lines = array_filter(explode("\n", file_get_contents($resultsFile)));
        $taken = array_map(static function ($line) {
            return json_decode($line, true)['session'];
        }, $lines);

        self::assertCount($sessionCount, $taken, 'every seeded session should have been taken exactly once in total.');
        self::assertCount($sessionCount, array_unique($taken), 'no session id should appear twice - that would mean two processes took the same session.');

        unlink($poolFile);
        unlink($resultsFile);
        unlink($workerScript);
    }

    private function workerScriptSource(): string
    {
        return <<<'PHP'
<?php
error_reporting(E_ALL & ~E_DEPRECATED);
[$script, $autoloadPath, $workerTag, $poolFile, $resultsFile] = $argv;
require $autoloadPath;

$config = [
    'database' => '/local',
    'endpoint' => 'localhost:2136',
    'discovery' => false,
    'iam_config' => ['insecure' => true],
    'credentials' => new \YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication(),
];

$table = (new \YdbPlatform\Ydb\Ydb($config))->table();
$logger = new class extends \Psr\Log\AbstractLogger {
    public function log($level, $message, array $context = []): void {}
};
$retry = new \YdbPlatform\Ydb\Retry\Retry($logger);

$pool = new \YdbPlatform\Ydb\Sessions\FileSessionPool(['table' => $table, 'filepath' => $poolFile], $retry);
$table->sessionPool($pool);

$results = fopen($resultsFile, 'a');
while (true) {
    $session = $table->takeSession();
    if ($session === null) {
        break;
    }
    fwrite($results, json_encode(['worker' => $workerTag, 'session' => $session->id()]) . "\n");
}
fclose($results);
PHP;
    }
}
