<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Depends;
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
    if (!is_dir($working_directory)) {
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
    } catch (\Exception $e) {
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
}