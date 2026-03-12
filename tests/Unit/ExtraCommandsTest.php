<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Exception\SyntaxErrorException;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Rclone;

class ExtraCommandsTest extends AbstractProviderTest
{
    public function setUp(): void
    {
        $this->setLeftProviderName('local_extra_commands');
        $working_directory = sys_get_temp_dir() . '/flyclone_' . $this->random_string();
        if (! is_dir($working_directory)) {
            mkdir($working_directory, 0777, true);
        }
        $this->working_directory = $working_directory;
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function instantiate_left_provider(): LocalProvider
    {
        return new LocalProvider($this->getLeftProviderName());
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_about_command(Rclone $rclone): void
    {
        // about does not always work on local filesystem depending on OS/Setup, but we check if it returns an object.
        try {
            $about = $rclone->about($this->working_directory);
            self::assertIsObject($about);
            self::assertObjectHasProperty('total', $about);
            self::assertObjectHasProperty('used', $about);
            self::assertObjectHasProperty('free', $about);
        } catch (Exception $e) {
            // Some systems might not support `about` on local fs, which is acceptable.
            self::markTestSkipped('Skipping about test: rclone about failed, which can happen on some local filesystems: ' . $e->getMessage());
        }
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_tree_command(Rclone $rclone): void
    {
        $dir1 = $this->working_directory . '/tree_test';
        $file1 = $dir1 . '/file1.txt';
        $subdir1 = $dir1 . '/subdir';
        $file2 = $subdir1 . '/file2.txt';

        mkdir($subdir1, 0777, true);
        file_put_contents($file1, 'content1');
        file_put_contents($file2, 'content2');

        $treeOutput = $rclone->tree($dir1);

        self::assertStringContainsString('file1.txt', $treeOutput);
        self::assertStringContainsString('subdir', $treeOutput);
        self::assertStringContainsString('file2.txt', $treeOutput);
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_dedupe_command(Rclone $rclone): void
    {
        $dedupeDir = $this->working_directory . '/dedupe_test';
        mkdir($dedupeDir, 0777, true);
        file_put_contents($dedupeDir . '/file.txt', 'identical_content');
        file_put_contents($dedupeDir . '/file_copy.txt', 'identical_content');

        // This is a bit tricky to test without --dry-run support in runAndGetStats
        // But we can check if the command runs without error
        $result = $rclone->dedupe($dedupeDir, 'newest', ['dry-run' => true]);
        self::assertTrue($result->success);
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_cleanup_command(Rclone $rclone): void
    {
        // Cleanup is not supported by the local backend and should throw an exception.
        try {
            $rclone->cleanup($this->working_directory);
            // If it doesn't throw, the test fails.
            self::fail('Expected a SyntaxErrorException for cleanup on local backend, but none was thrown.');
        } catch (SyntaxErrorException $e) {
            // Exception is expected, so we assert that its message contains the expected text.
            self::assertStringContainsString("doesn't support cleanup", $e->getMessage());
        }
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_backend_command(Rclone $rclone): void
    {
        // Using `backend noop` to test the generic command runner as it's simple and supported by local fs.
        $noopOutput = $rclone->backend('noop', $this->working_directory, ['echo' => 'yes'], ['arg1', 'arg2']);
        $noopData = json_decode($noopOutput);

        self::assertIsObject($noopData);
        self::assertObjectHasProperty('name', $noopData);
        self::assertEquals('noop', $noopData->name);
        self::assertEquals(['arg1', 'arg2'], $noopData->arg);
    }

    #[Test]
    public function test_list_remotes_static(): void
    {
        // listRemotes returns array of configured remotes (may be empty if no rclone.conf)
        $remotes = Rclone::listRemotes();
        self::assertIsArray($remotes);
    }

    #[Test]
    public function test_config_file_static(): void
    {
        // configFile returns path to rclone config file
        $path = Rclone::configFile();
        self::assertIsString($path);
        self::assertNotEmpty($path);
        // Path should end with rclone.conf or similar
        self::assertMatchesRegularExpression('/rclone\.conf$/', $path);
    }

    #[Test]
    public function test_config_dump_static(): void
    {
        // configDump returns config as object (may be empty stdClass if no remotes)
        $config = Rclone::configDump();
        self::assertIsObject($config);
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_md5sum_command(Rclone $rclone): void
    {
        $dir = $this->working_directory . '/md5sum_test';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/file1.txt', 'hello world');
        file_put_contents($dir . '/file2.txt', 'foo bar');

        $checksums = $rclone->md5sum($dir);

        self::assertIsArray($checksums);
        self::assertCount(2, $checksums);

        // Returns associative array: [path => hash]
        foreach ($checksums as $path => $hash) {
            self::assertIsString($path);
            self::assertIsString($hash);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
        }

        // Verify specific known hashes
        self::assertArrayHasKey('file1.txt', $checksums);
        self::assertArrayHasKey('file2.txt', $checksums);
        self::assertEquals('5eb63bbbe01eeed093cb22bb8f5acdc3', $checksums['file1.txt']); // md5('hello world')
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_sha1sum_command(Rclone $rclone): void
    {
        $dir = $this->working_directory . '/sha1sum_test';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/file1.txt', 'hello world');
        file_put_contents($dir . '/file2.txt', 'foo bar');

        $checksums = $rclone->sha1sum($dir);

        self::assertIsArray($checksums);
        self::assertCount(2, $checksums);

        // Returns associative array: [path => hash]
        foreach ($checksums as $path => $hash) {
            self::assertIsString($path);
            self::assertIsString($hash);
            self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $hash);
        }

        // Verify specific known hashes
        self::assertArrayHasKey('file1.txt', $checksums);
        self::assertArrayHasKey('file2.txt', $checksums);
        self::assertEquals('2aae6c35c94fcfb415dbe95f408b9ce91ee846ed', $checksums['file1.txt']); // sha1('hello world')
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_bisync_command(Rclone $rclone): void
    {
        $dir1 = $this->working_directory . '/bisync_path1';
        $dir2 = $this->working_directory . '/bisync_path2';
        mkdir($dir1, 0777, true);
        mkdir($dir2, 0777, true);

        file_put_contents($dir1 . '/from_path1.txt', 'content from path1');
        file_put_contents($dir2 . '/from_path2.txt', 'content from path2');

        // bisync needs --resync on first run to establish baseline
        $result = $rclone->bisync($dir1, $dir2, ['resync' => true]);

        self::assertTrue($result->success);

        // After bisync, both directories should have both files
        self::assertFileExists($dir1 . '/from_path2.txt');
        self::assertFileExists($dir2 . '/from_path1.txt');
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_sync_command(Rclone $rclone): void
    {
        $sourceDir = $this->working_directory . '/sync_source';
        $destDir = $this->working_directory . '/sync_dest';
        mkdir($sourceDir, 0777, true);
        mkdir($destDir, 0777, true);

        // Create files in source
        file_put_contents($sourceDir . '/file1.txt', 'content1');
        file_put_contents($sourceDir . '/file2.txt', 'content2');

        // Create a file in dest that should be deleted by sync
        file_put_contents($destDir . '/old_file.txt', 'old content');

        $result = $rclone->sync($sourceDir, $destDir);

        self::assertTrue($result->success);
        self::assertFileExists($destDir . '/file1.txt');
        self::assertFileExists($destDir . '/file2.txt');
        // sync should delete files in dest that don't exist in source
        self::assertFileDoesNotExist($destDir . '/old_file.txt');
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_size_command(Rclone $rclone): void
    {
        $dir = $this->working_directory . '/size_test';
        mkdir($dir, 0777, true);

        // Create files with known content
        file_put_contents($dir . '/file1.txt', str_repeat('a', 1000));
        file_put_contents($dir . '/file2.txt', str_repeat('b', 500));

        $size = $rclone->size($dir);

        self::assertIsObject($size);
        self::assertObjectHasProperty('count', $size);
        self::assertObjectHasProperty('bytes', $size);
        self::assertEquals(2, $size->count);
        self::assertEquals(1500, $size->bytes);
    }

    #[Test]
    #[Depends('instantiate_with_one_provider')]
    public function test_copyto_command(Rclone $rclone): void
    {
        $sourceFile = $this->working_directory . '/copyto_source.txt';
        $destFile = $this->working_directory . '/copyto_dest.txt';

        file_put_contents($sourceFile, 'copyto content');

        $result = $rclone->copyto($sourceFile, $destFile);

        self::assertTrue($result->success);
        self::assertFileExists($destFile);
        self::assertEquals('copyto content', file_get_contents($destFile));
        // Source should still exist (copy, not move)
        self::assertFileExists($sourceFile);
    }
}
