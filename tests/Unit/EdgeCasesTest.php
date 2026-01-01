<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\ProcessManager;
use Verseles\Flyclone\StatsParser;
use Verseles\Flyclone\ProgressParser;
use Verseles\Flyclone\Providers\LocalProvider;
use Symfony\Component\Process\Process;

class EdgeCasesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flyclone_edge_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    #[Test]
    public function empty_file_operations(): void
    {
        $subDir = $this->tempDir . '/empty_test';
        mkdir($subDir);
        $provider = new LocalProvider('edge', ['root' => $subDir]);
        $rclone = new Rclone($provider);

        $emptyFile = $subDir . '/empty.txt';
        file_put_contents($emptyFile, '');

        $result = $rclone->ls($subDir);
        $this->assertCount(1, $result);
        $this->assertEquals(0, $result[0]->Size);
    }

    #[Test]
    public function special_characters_in_filename(): void
    {
        $subDir = $this->tempDir . '/special_test';
        mkdir($subDir);
        $provider = new LocalProvider('edge', ['root' => $subDir]);
        $rclone = new Rclone($provider);

        $specialFile = $subDir . '/file with spaces.txt';
        file_put_contents($specialFile, 'content');

        $result = $rclone->ls($subDir);
        $this->assertCount(1, $result);
        $this->assertEquals('file with spaces.txt', $result[0]->Name);
    }

    #[Test]
    public function unicode_characters_in_filename(): void
    {
        $subDir = $this->tempDir . '/unicode_test';
        mkdir($subDir);
        $provider = new LocalProvider('edge', ['root' => $subDir]);
        $rclone = new Rclone($provider);

        $unicodeFile = $subDir . '/文件.txt';
        file_put_contents($unicodeFile, 'unicode content');

        $result = $rclone->ls($subDir);
        $this->assertCount(1, $result);
        $this->assertEquals('文件.txt', $result[0]->Name);
    }

    #[Test]
    public function stats_parser_handles_empty_output(): void
    {
        $stats = StatsParser::parse('');

        $this->assertEquals(0, $stats->bytes);
        $this->assertEquals(0, $stats->files);
        $this->assertEquals(0, $stats->errors);
    }

    #[Test]
    public function stats_parser_handles_partial_output(): void
    {
        $output = "Transferred:   100 B / 100 B, 100%, 0 B/s, ETA -\n";
        $stats = StatsParser::parse($output);

        $this->assertEquals(100, $stats->bytes);
    }

    #[Test]
    public function progress_parser_handles_empty_output(): void
    {
        $parser = new ProgressParser();
        $parser->parse(Process::OUT, '');
        $progress = $parser->getProgress();

        $this->assertEquals(0, $progress->sent);
        $this->assertEquals('0 B', $progress->dataSent);
    }

    #[Test]
    public function progress_parser_handles_malformed_output(): void
    {
        $parser = new ProgressParser();
        $parser->parse(Process::OUT, 'not a valid progress line');
        $progress = $parser->getProgress();

        $this->assertEquals(0, $progress->sent);
    }

    #[Test]
    public function progress_parser_reset_clears_state(): void
    {
        $parser = new ProgressParser();
        $parser->parse(Process::OUT, "Transferred:   1 MiB / 10 MiB, 10%, 100 KiB/s, ETA 1m30s\n");

        $parser->reset();
        $progress = $parser->getProgress();

        $this->assertEquals(0, $progress->sent);
        $this->assertEquals('0 B', $progress->dataSent);
    }

    #[Test]
    public function size_conversion_edge_cases(): void
    {
        $this->assertEquals(0, StatsParser::convertSizeToBytes('0'));
        $this->assertEquals(0, StatsParser::convertSizeToBytes('0 B'));
        $this->assertEquals(1024, StatsParser::convertSizeToBytes('1 KiB'));
        $this->assertEquals(1048576, StatsParser::convertSizeToBytes('1 MiB'));
        $this->assertEquals(1073741824, StatsParser::convertSizeToBytes('1 GiB'));
    }

    #[Test]
    public function duration_conversion_edge_cases(): void
    {
        $this->assertEquals(0, StatsParser::convertDurationToSeconds('0s'));
        $this->assertEquals(1, StatsParser::convertDurationToSeconds('1s'));
        $this->assertEquals(60, StatsParser::convertDurationToSeconds('1m0s'));
        $this->assertEquals(3600, StatsParser::convertDurationToSeconds('1h0m0s'));
        $this->assertEquals(3661, StatsParser::convertDurationToSeconds('1h1m1s'));
    }

    #[Test]
    public function format_bytes_edge_cases(): void
    {
        $this->assertEquals('0 B', StatsParser::formatBytes(0));
        $this->assertEquals('1 B', StatsParser::formatBytes(1));
        $this->assertEquals('1 KiB', StatsParser::formatBytes(1024));
        $this->assertEquals('1 MiB', StatsParser::formatBytes(1048576));
    }

    #[Test]
    public function deeply_nested_directory_structure(): void
    {
        $provider = new LocalProvider('edge');
        $rclone = new Rclone($provider);

        $deepPath = $this->tempDir . '/a/b/c/d/e';
        mkdir($deepPath, 0755, true);
        file_put_contents($deepPath . '/deep.txt', 'deep content');

        $result = $rclone->ls($deepPath);
        $this->assertCount(1, $result);
        $this->assertEquals('deep.txt', $result[0]->Name);
    }

    #[Test]
    public function hidden_files_are_listed(): void
    {
        $hiddenDir = $this->tempDir . '/hidden_test';
        mkdir($hiddenDir, 0755, true);
        
        $provider = new LocalProvider('edge');
        $rclone = new Rclone($provider);

        file_put_contents($hiddenDir . '/.hidden', 'hidden content');

        $result = $rclone->ls($hiddenDir);
        $this->assertCount(1, $result);
        $this->assertEquals('.hidden', $result[0]->Name);
    }
}
