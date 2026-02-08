<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Verseles\Flyclone\Exception\RcloneException;
use Verseles\Flyclone\Exception\TemporaryErrorException;
use Verseles\Flyclone\FilterBuilder;
use Verseles\Flyclone\Logger;
use Verseles\Flyclone\ProgressParser;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\RetryHandler;
use Verseles\Flyclone\SecretsRedactor;

/**
 * Tests for Feature 2: Security & DX improvements.
 */
class Feature2Test extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flyclone_feature2_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Ensure clean state
        Logger::clearLogs();
        Logger::setDebugMode(false);
        SecretsRedactor::setEnabled(true);
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

    // ==================== SecretsRedactor Tests ====================

    #[Test]
    public function secrets_redactor_redacts_passwords(): void
    {
        $message = 'Error: RCLONE_CONFIG_MYREMOTE_PASSWORD=supersecret123 failed';
        $redacted = SecretsRedactor::redact($message);

        $this->assertStringNotContainsString('supersecret123', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    #[Test]
    public function secrets_redactor_redacts_urls_with_credentials(): void
    {
        $message = 'Failed to connect to https://user:password123@example.com/path';
        $redacted = SecretsRedactor::redact($message);

        $this->assertStringNotContainsString('password123', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    #[Test]
    public function secrets_redactor_redacts_known_secrets(): void
    {
        $message = 'Connection failed with secret: my-api-key-12345';
        $redacted = SecretsRedactor::redact($message, ['my-api-key-12345']);

        $this->assertStringNotContainsString('my-api-key-12345', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    #[Test]
    public function secrets_redactor_can_be_disabled(): void
    {
        SecretsRedactor::setEnabled(false);

        $message = 'Secret: RCLONE_CONFIG_TEST_SECRET=visible';
        $result = SecretsRedactor::redact($message);

        $this->assertEquals($message, $result);

        SecretsRedactor::setEnabled(true);
    }

    // ==================== Logger Tests ====================

    #[Test]
    public function logger_stores_logs(): void
    {
        Logger::info('Test message', ['key' => 'value']);

        $logs = Logger::getLogs();
        $this->assertNotEmpty($logs);
        $this->assertEquals('Test message', $logs[0]['message']);
    }

    #[Test]
    public function logger_debug_mode_logs_commands(): void
    {
        Logger::setDebugMode(true);
        Logger::debug('Debug message');

        $logs = Logger::getLogsByLevel('debug');
        $this->assertNotEmpty($logs);

        Logger::setDebugMode(false);
    }

    #[Test]
    public function logger_clears_logs(): void
    {
        Logger::info('Test');
        $this->assertNotEmpty(Logger::getLogs());

        Logger::clearLogs();
        $this->assertEmpty(Logger::getLogs());
    }

    // ==================== RetryHandler Tests ====================

    #[Test]
    public function retry_handler_retries_on_failure(): void
    {
        $attempts = 0;

        $handler = RetryHandler::create()
            ->maxAttempts(3)
            ->baseDelay(10)
            ->retryWhen(fn ($e) => $attempts < 3);

        $result = $handler->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException('Temporary failure');
            }

            return 'success';
        });

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    #[Test]
    public function retry_handler_respects_max_attempts(): void
    {
        $attempts = 0;

        $handler = RetryHandler::create()
            ->maxAttempts(2)
            ->baseDelay(10)
            ->retryWhen(fn () => true);

        $this->expectException(RuntimeException::class);

        $handler->execute(function () use (&$attempts) {
            $attempts++;

            throw new RuntimeException('Always fails');
        });
    }

    #[Test]
    public function retry_handler_calls_on_retry_callback(): void
    {
        $retryCount = 0;

        $handler = RetryHandler::create()
            ->maxAttempts(3)
            ->baseDelay(10)
            ->retryWhen(fn () => true)
            ->onRetry(function () use (&$retryCount) {
                $retryCount++;
            });

        try {
            $handler->execute(function () {
                throw new RuntimeException('Fail');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertEquals(2, $retryCount); // 3 attempts = 2 retries
    }

    #[Test]
    public function retry_handler_can_be_disabled(): void
    {
        $attempts = 0;

        $handler = RetryHandler::create()
            ->enabled(false)
            ->maxAttempts(3);

        $this->expectException(RuntimeException::class);

        $handler->execute(function () use (&$attempts) {
            $attempts++;

            throw new RuntimeException('Fail');
        });

        $this->assertEquals(1, $attempts);
    }

    // ==================== FilterBuilder Tests ====================

    #[Test]
    public function filter_builder_creates_include_patterns(): void
    {
        $filter = FilterBuilder::create()
            ->include('*.txt')
            ->include('*.md');

        $flags = $filter->toFlags();

        $this->assertArrayHasKey('include', $flags);
        $this->assertContains('*.txt', $flags['include']);
        $this->assertContains('*.md', $flags['include']);
    }

    #[Test]
    public function filter_builder_creates_exclude_patterns(): void
    {
        $filter = FilterBuilder::create()
            ->exclude('*.tmp')
            ->excludeCommon();

        $flags = $filter->toFlags();

        $this->assertArrayHasKey('exclude', $flags);
        $this->assertContains('*.tmp', $flags['exclude']);
        $this->assertContains('node_modules/**', $flags['exclude']);
    }

    #[Test]
    public function filter_builder_handles_size_filters(): void
    {
        $filter = FilterBuilder::create()
            ->minSize('1M')
            ->maxSize('100M');

        $flags = $filter->toFlags();

        $this->assertArrayHasKey('min-size', $flags);
        $this->assertArrayHasKey('max-size', $flags);
    }

    #[Test]
    public function filter_builder_handles_age_filters(): void
    {
        $filter = FilterBuilder::create()
            ->olderThan('1d')
            ->newerThan('7d');

        $flags = $filter->toFlags();

        $this->assertArrayHasKey('min-age', $flags);
        $this->assertArrayHasKey('max-age', $flags);
    }

    #[Test]
    public function filter_builder_extension_helper(): void
    {
        $filter = FilterBuilder::create()
            ->extensions(['jpg', 'png', 'gif']);

        $flags = $filter->toFlags();

        $this->assertContains('*.jpg', $flags['include']);
        $this->assertContains('*.png', $flags['include']);
        $this->assertContains('*.gif', $flags['include']);
    }

    #[Test]
    public function filter_builder_reset_clears_all(): void
    {
        $filter = FilterBuilder::create()
            ->include('*.txt')
            ->exclude('*.tmp')
            ->minSize('1M');

        $filter->reset();

        $this->assertFalse($filter->hasFilters());
    }

    // ==================== ProgressParser Tests ====================

    #[Test]
    public function progress_parser_handles_fragmented_buffer(): void
    {
        $parser = new ProgressParser();

        // Simulate fragmented input
        $parser->parse(Process::OUT, 'Transferred:   1 MiB / 10 MiB, ');
        $parser->parse(Process::OUT, "10%, 100 KiB/s, ETA 1m30s\n");

        $progress = $parser->getProgress();
        $this->assertEquals(10, $progress->sent);
    }

    #[Test]
    public function progress_parser_flush_processes_remaining_buffer(): void
    {
        $parser = new ProgressParser();

        $parser->parse(Process::OUT, 'Transferred:   5 MiB / 10 MiB, 50%, 200 KiB/s, ETA 30s');
        $parser->flush();

        $progress = $parser->getProgress();
        $this->assertEquals(50, $progress->sent);
    }

    #[Test]
    public function progress_parser_get_percentage(): void
    {
        $parser = new ProgressParser();
        $parser->parse(Process::OUT, "Transferred:   7.5 MiB / 10 MiB, 75%, 300 KiB/s, ETA 10s\n");

        $this->assertEquals(75, $parser->getPercentage());
        $this->assertFalse($parser->isComplete());
    }

    // ==================== RcloneException Tests ====================

    #[Test]
    public function rclone_exception_has_context(): void
    {
        $exception = new RcloneException('Test error');
        $exception->setContext([
            'command' => 'rclone copy',
            'exit_code' => 5,
        ]);

        $context = $exception->getContext();
        $this->assertEquals('rclone copy', $context['command']);
        $this->assertEquals(5, $context['exit_code']);
    }

    #[Test]
    public function temporary_error_exception_is_retryable(): void
    {
        $exception = new TemporaryErrorException(
            new RuntimeException('Inner'),
            'Temporary error'
        );

        $this->assertTrue($exception->isRetryable());
    }

    #[Test]
    public function rclone_exception_detailed_message(): void
    {
        $exception = new RcloneException('Error occurred');
        $exception->setContext(['provider' => 's3', 'path' => '/bucket']);

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString('Error occurred', $detailed);
        $this->assertStringContainsString('provider', $detailed);
        $this->assertStringContainsString('s3', $detailed);
    }

    // ==================== Rclone Integration Tests ====================

    #[Test]
    public function rclone_health_check_returns_status(): void
    {
        $provider = new LocalProvider('health_test', ['root' => $this->tempDir]);
        $rclone = new Rclone($provider);

        $health = $rclone->healthCheck();

        $this->assertTrue($health->healthy);
        $this->assertIsFloat($health->latency_ms);
        $this->assertNull($health->error);
    }

    #[Test]
    public function rclone_dry_run_mode(): void
    {
        $provider = new LocalProvider('dryrun_test', ['root' => $this->tempDir]);
        $rclone = new Rclone($provider);

        $rclone->dryRun(true);

        $this->assertTrue($rclone->isDryRun());
    }

    #[Test]
    public function rclone_filter_integration(): void
    {
        $provider = new LocalProvider('filter_test', ['root' => $this->tempDir]);
        $rclone = new Rclone($provider);

        $rclone->filter()
            ->include('*.txt')
            ->exclude('*.tmp');

        // Filter should be set
        $this->assertNotNull($rclone->filter());

        $rclone->clearFilter();
    }

    #[Test]
    public function rclone_retry_configuration(): void
    {
        $provider = new LocalProvider('retry_test', ['root' => $this->tempDir]);
        $rclone = new Rclone($provider);

        $rclone->retry(5, 500);

        // Should not throw during normal operation
        $this->assertTrue(true);
    }

    #[Test]
    public function rclone_get_last_command(): void
    {
        $provider = new LocalProvider('cmd_test', ['root' => $this->tempDir]);
        $rclone = new Rclone($provider);

        // Perform an operation
        $rclone->ls('');

        $command = $rclone->getLastCommand();
        $this->assertStringContainsString('rclone', $command);
    }
}
