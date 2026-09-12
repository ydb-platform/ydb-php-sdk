<?php

namespace YdbPlatform\Ydb\Test;

use PHPUnit\Framework\TestCase;
use YdbPlatform\Ydb\Auth\Implement\AnonymousAuthentication;
use YdbPlatform\Ydb\Iam;

class IamTestable extends Iam
{
    public function callGetTokenTempFile()
    {
        return $this->getTokenTempFile();
    }
}

// A directory needs the executable bit to be entered/traversed, not just read/write -
// see ydb-platform/ydb-php-sdk#128.
class IamTempDirPermissionsTest extends TestCase
{
    private $tempDir;

    protected function tearDown(): void
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testCreatedTempDirIsTraversable(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ydb-sdk-test-' . uniqid();
        // assertDirectoryDoesNotExist() only exists from PHPUnit 9.1 - this SDK's
        // stated PHP 7.2 minimum resolves PHPUnit 8.5, which lacks it.
        self::assertFalse(is_dir($this->tempDir));

        $iam = new IamTestable([
            'temp_dir' => $this->tempDir,
            'credentials' => new AnonymousAuthentication(),
        ]);
        $iam->callGetTokenTempFile();

        self::assertDirectoryExists($this->tempDir);
        self::assertSame('0700', substr(sprintf('%o', fileperms($this->tempDir)), -4));
    }
}
