<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Verseles\Flyclone\TemporaryPath;

class TemporaryPathTest extends TestCase
{
    #[Test]
    public function private_directory_is_unique_and_owner_only(): void
    {
        $dir = TemporaryPath::directory('permission check');

        try {
            self::assertDirectoryExists($dir);
            self::assertSame(0700, fileperms($dir) & 0777);
        } finally {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    #[Test]
    public function remote_name_is_unique_and_rclone_safe(): void
    {
        $first = TemporaryPath::remoteName('local temp upload');
        $second = TemporaryPath::remoteName('local temp upload');

        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression('/^LOCAL_TEMP_UPLOAD_[A-F0-9]+$/', $first);
        self::assertMatchesRegularExpression('/^LOCAL_TEMP_UPLOAD_[A-F0-9]+$/', $second);
    }
}
