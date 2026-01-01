<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use Verseles\Flyclone\Providers\CryptProvider;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Rclone;

class CryptProviderTest extends AbstractProviderTest
{
  private LocalProvider $localProvider;
  private static string $sharedLocalRemotePath = '';
  
  public function setUp(): void
  {
    $this->setLeftProviderName('crypt_test');
    
    if (self::$sharedLocalRemotePath === '' || !is_dir(self::$sharedLocalRemotePath)) {
      self::$sharedLocalRemotePath = sys_get_temp_dir() . '/flyclone_crypt_remote_' . $this->random_string();
      mkdir(self::$sharedLocalRemotePath, 0777, true);
    }
    
    $this->localProvider = new LocalProvider('local_for_crypt');
    $this->working_directory = '';
  }
  
  #[Test]
  public function instantiate_left_provider(): CryptProvider
  {
    $cryptProvider = new CryptProvider($this->getLeftProviderName(), [
      'remote'      => $this->localProvider,
      'remote_path' => self::$sharedLocalRemotePath,
      'password'    => Rclone::obscure('testpassword'),
      'password2'   => Rclone::obscure('testsalt'),
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
    
    $rclone->rcat($testFile, $originalContent);
    
    $files = array_diff(scandir(self::$sharedLocalRemotePath), ['.', '..']);
    
    self::assertCount(1, $files, 'Expected one encrypted file to be created.');
    
    $encryptedFileName = reset($files);
    $encryptedFilePath = self::$sharedLocalRemotePath . '/' . $encryptedFileName;
    
    self::assertFileExists($encryptedFilePath);
    $encryptedContent = file_get_contents($encryptedFilePath);
    
    self::assertNotEquals($originalContent, $encryptedContent);
    self::assertStringNotContainsString('secret message', $encryptedContent, 'Encrypted content should not contain plaintext.');
    
    $decryptedContent = $rclone->cat($testFile);
    
    self::assertEquals($originalContent, $decryptedContent);
  }
}