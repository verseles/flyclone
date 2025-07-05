<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use Verseles\Flyclone\Providers\CryptProvider;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Rclone;

class CryptProviderTest extends AbstractProviderTest
{
  private LocalProvider $localProvider;
  private string $localRemotePath;
  
  public function setUp(): void
  {
    $this->setLeftProviderName('crypt_test');
    
    // Setup the underlying local provider
    $localProviderName = 'local_for_crypt';
    $this->localRemotePath = sys_get_temp_dir() . '/flyclone_crypt_remote_' . $this->random_string();
    mkdir($this->localRemotePath, 0777, true);
    
    // This provider instance will be passed into the CryptProvider config
    // It now includes its path directly in its configuration
    $this->localProvider = new LocalProvider($localProviderName, ['root' => $this->localRemotePath]);
    
    // The crypt provider will wrap the local provider
    $this->working_directory = ''; // Crypt path is relative to the wrapped remote
  }
  
  #[Test]
  public function instantiate_left_provider(): CryptProvider
  {
    // Pass the actual LocalProvider instance.
    // The CryptProvider's constructor and flags() method will handle it.
    $cryptProvider = new CryptProvider($this->getLeftProviderName(), [
      'remote'    => $this->localProvider,
      'password'  => Rclone::obscure('testpassword'),
      'password2' => Rclone::obscure('testsalt'),
    ]);
    
    self::assertInstanceOf(CryptProvider::class, $cryptProvider);
    return $cryptProvider;
  }
  
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function test_encryption_and_decryption(Rclone $rclone): void
  {
    $originalContent = 'This is a secret message for the crypt test.';
    $testFile = 'secret_file.txt';
    
    // Write content using the Crypt provider
    $rclone->rcat($testFile, $originalContent);
    
    // Check the local disk directly to find the encrypted file
    $files = array_diff(scandir($this->localRemotePath), ['.', '..']);
    
    self::assertCount(1, $files, 'Expected one encrypted file to be created.');
    
    $encryptedFileName = reset($files);
    $encryptedFilePath = $this->localRemotePath . '/' . $encryptedFileName;
    
    self::assertFileExists($encryptedFilePath);
    $encryptedContent = file_get_contents($encryptedFilePath);
    
    // Assert that the stored content is NOT the original content and does not contain it
    self::assertNotEquals($originalContent, $encryptedContent);
    self::assertStringNotContainsString('secret message', $encryptedContent, 'Encrypted content should not contain plaintext.');
    
    // Read the content back through the Crypt provider
    $decryptedContent = $rclone->cat($testFile);
    
    // Assert that the decrypted content IS the original content
    self::assertEquals($originalContent, $decryptedContent);
  }
}