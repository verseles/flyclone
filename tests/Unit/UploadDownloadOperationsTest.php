<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\LocalProvider;

// Needed for simulating local side in progress tests
use Verseles\Flyclone\Providers\SFtpProvider;
use Verseles\Flyclone\Rclone;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Tests the upload_file and download_to_local operations of the Rclone class.
 * This test suite uses SFtpProvider as the "remote" provider (configured as left_side in the Rclone instance)
 * to simulate interactions with an external server.
 */
class UploadDownloadOperationsTest extends AbstractProviderTest // Inherits ProgressTrackingTrait
{
  /**
   * Sets up the test environment before each test method.
   * Defines the provider name and working directory for SFTP.
   *
   * @return void
   * @throws ExpectationFailedException
   * @throws InvalidArgumentException
   */
  public function setUp() : void
  {
    // Set the disk name for the SFTP provider. This will be the 'left_side' of the Rclone instance.
    $this->setLeftProviderName('sftp_updown_disk');
    // Set the base working directory on the SFTP server for this test suite.
    // A random string is used to avoid conflicts between test runs.
    $this->working_directory = '/upload/flyclone_tests_updown/' . $this->random_string();
    
    // Ensure the provider name was configured correctly.
    self::assertEquals('sftp_updown_disk', $this->getLeftProviderName());
  }
  
  /**
   * Instantiates the SFTP provider that will be used as the 'left_side' (remote) in the Rclone instance.
   * SFTP credentials and settings are obtained from environment variables.
   * This method is a dependency provider for other tests.
   *
   * @return SFtpProvider The configured instance of SfTpProvider.
   */
  #[Test]
  public function instantiate_left_provider() : SFtpProvider
  {
    $sftpProvider = new SFtpProvider($this->getLeftProviderName(), [
      'HOST' => $_ENV['SFTP_HOST'],
      'USER' => $_ENV['SFTP_USER'],
      'PASS' => Rclone::obscure($_ENV['SFTP_PASS']), // Password is obscured using Rclone::obscure
      'PORT' => $_ENV['SFTP_PORT'],
    ]);
    
    self::assertInstanceOf(SFtpProvider::class, $sftpProvider);
    return $sftpProvider;
  }
  
  /**
   * Tests the complete cycle of uploading a local file to SFTP and then
   * downloading that file from SFTP to a new local location.
   *
   * @param Rclone $rcloneRemote Rclone instance configured with SFTPProvider as left_side.
   *                             This instance is provided by the `instantiate_with_one_provider`
   *                             method from the parent `AbstractProviderTest` class.
   *
   * @throws ExpectationFailedException
   * @throws InvalidArgumentException
   */
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function test_upload_and_download_file_operations(Rclone $rcloneRemote) : void
  {
    // Step 0: Ensure the working directory on SFTP exists.
    // The mkdir method will create the directory if it doesn't exist.
    $rcloneRemote->mkdir($this->working_directory);
    $dirCheck = $rcloneRemote->is_dir($this->working_directory);
    self::assertTrue($dirCheck->exists, "Working directory '{$this->working_directory}' could not be created or verified on SFTP.");
    
    // Step 1: Create a temporary local file with content.
    $localTempUploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_upload_temp_' . $this->random_string();
    // Create the local temporary directory for the upload file.
    if (!mkdir($localTempUploadDir, 0777, TRUE) && !is_dir($localTempUploadDir)) {
      // @codeCoverageIgnoreStart
      self::fail("Could not create local temporary directory for upload: {$localTempUploadDir}");
      // @codeCoverageIgnoreEnd
    }
    $localFilePath = $localTempUploadDir . DIRECTORY_SEPARATOR . 'test_upload_content.txt';
    $originalContent = 'Specific content for upload and download test - ' . $this->random_string(10);
    // Write content to the local file.
    file_put_contents($localFilePath, $originalContent);
    self::assertFileExists($localFilePath, 'Local file for upload was not created.');
    
    // Step 2: Define the remote file path on SFTP.
    $remoteFilePath = $this->working_directory . DIRECTORY_SEPARATOR . 'uploaded_via_flyclone.txt';
    
    // Step 3: Upload the local file to the "remote" (SFTP).
    // The `upload_file` method uses `moveto`, which removes the original local file upon success.
    $uploadResult = $rcloneRemote->upload_file($localFilePath, $remoteFilePath);
    self::assertTrue($uploadResult->success, 'Failed to upload file to SFTP.');
    // Verify that the original local file was removed, as expected by `moveto`.
    self::assertFileDoesNotExist($localFilePath, 'Original local file still exists after upload_file (should have been moved).');
    
    // Step 4: Verify that the file exists on SFTP and its content is correct.
    $fileExistsOnRemote = $rcloneRemote->is_file($remoteFilePath);
    self::assertTrue($fileExistsOnRemote->exists, 'File not found on SFTP after upload.');
    // Read the content of the file on SFTP.
    $remoteContent = $rcloneRemote->cat($remoteFilePath);
    self::assertEquals($originalContent, $remoteContent, 'Content of the file on SFTP differs from the original.');
    
    // Step 5: Download the file from SFTP to a new temporary local location.
    $localTempDownloadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_download_temp_' . $this->random_string();
    // The `download_to_local` method will create the parent directory if it doesn't exist.
    $downloadedLocalFilePath = $localTempDownloadDir . DIRECTORY_SEPARATOR . 'downloaded_from_sftp.txt';
    
    $downloadResult = $rcloneRemote->download_to_local($remoteFilePath, $downloadedLocalFilePath);
    self::assertTrue($downloadResult->success, 'Failed to download file from SFTP.');
    self::assertEquals($downloadedLocalFilePath, $downloadResult->local_path, 'Downloaded file path is not as expected.');
    self::assertFileExists($downloadedLocalFilePath, 'Downloaded file not found locally.');
    
    // Step 6: Verify that the content of the downloaded file is identical to the original.
    $downloadedContent = file_get_contents($downloadedLocalFilePath);
    self::assertEquals($originalContent, $downloadedContent, 'Content of the downloaded file differs from the original.');
    
    // Step 7: Cleanup of test artifacts.
    // Remove local temporary upload directory (the file was already moved).
    if (is_dir($localTempUploadDir)) {
      rmdir($localTempUploadDir);
    }
    // Remove downloaded file and its temporary directory.
    if (file_exists($downloadedLocalFilePath)) {
      unlink($downloadedLocalFilePath);
    }
    if (is_dir($localTempDownloadDir)) {
      rmdir($localTempDownloadDir);
    }
    // Remove file from the "remote" (SFTP).
    $rcloneRemote->deletefile($remoteFilePath);
    // Attempt to remove the working directory on SFTP (will only work if empty).
    try {
      $rcloneRemote->rmdir($this->working_directory);
    }
    catch (\Exception $e) {
      // Ignore failure to remove directory, it might not be empty or due to permissions.
      // In a real scenario, this could be logged.
    }
  }
  
  /**
   * Tests the progress tracking of the upload_file operation.
   * upload_file internally uses 'moveto' with a (Local -> Remote) Rclone configuration.
   *
   * @param Rclone $rcloneRemote Instance of Rclone configured with SFTPProvider (Remote).
   */
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function test_upload_with_progress(Rclone $rcloneRemote) : void
  {
    // Ensure the remote working directory exists on SFTP.
    $rcloneRemote->mkdir($this->working_directory);
    
    // Create a large local file to ensure progress updates are triggered.
    $localLargeFile = $this->create_large_temp_file(1); // Creates a 1MB file.
    $remoteFilePath = $this->working_directory . '/' . basename($localLargeFile);
    
    // To test progress for upload_file, we test the underlying 'moveto' (Local -> Remote) operation.
    $localProvider = new LocalProvider('temp_local_for_upload_progress');
    // This Rclone instance is specifically for (Local -> SFTP) transfer.
    $uploaderRclone = new Rclone($localProvider, $rcloneRemote->getLeftSide()); // $rcloneRemote->getLeftSide() is the SFTP provider
    
    $this->assert_progress_tracking(
      $uploaderRclone,    // The Rclone instance performing the operation.
      'moveto',           // The operation used by upload_file.
      $localLargeFile,    // Source path (local large file).
      $remoteFilePath     // Destination path on SFTP.
    );
    
    // Cleanup:
    // 'moveto' deletes the source, so $localLargeFile should be gone from its original temp dir.
    $this->cleanup_temp_file_and_dir($localLargeFile); // This will clean the temp dir if empty.
    $rcloneRemote->deletefile($remoteFilePath); // Delete the uploaded file from SFTP.
    // Attempt to remove the working directory on SFTP if it's empty.
    if ($rcloneRemote->is_dir($this->working_directory)->exists && count($rcloneRemote->ls($this->working_directory)) === 0) {
      $rcloneRemote->rmdir($this->working_directory);
    }
  }
  
  /**
   * Tests the progress tracking of the download_to_local operation.
   * download_to_local internally uses 'copyto' with a (Remote -> Local) Rclone configuration.
   *
   * @param Rclone $rcloneRemote Instance of Rclone configured with SFTPProvider (Remote).
   */
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function test_download_with_progress(Rclone $rcloneRemote) : void
  {
    // Ensure the remote working directory exists on SFTP.
    $rcloneRemote->mkdir($this->working_directory);
    
    // 1. Create a large file on the SFTP remote to download.
    $largeContentSizeMB = 1; // 1MB
    $largeContent = str_repeat('X', $largeContentSizeMB * 1024 * 1024); // Content for the large file.
    $remoteLargeFilePath = $this->working_directory . '/remote_large_file_for_dl_progress_' . $this->random_string() . '.dat';
    $rcloneRemote->rcat($remoteLargeFilePath, $largeContent); // Upload content to SFTP.
    self::assertTrue($rcloneRemote->is_file($remoteLargeFilePath)->exists, 'Large file not created on SFTP for download progress test.');
    
    // 2. Define local destination path for the download.
    $localTempDownloadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_progress_dl_temp_' . $this->random_string();
    // No need to mkdir for $localTempDownloadDir, download_to_local handles parent dir creation for the file.
    // However, for cleanup_temp_file_and_dir to work as intended for the directory, it needs the specific name pattern.
    // So, we'll use our create_large_temp_file helper's directory structure pattern for the local destination directory,
    // even though the file itself is created by rclone.
    $patternMatchingTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_progress_test_src_' . $this->random_string();
    mkdir($patternMatchingTempDir, 0777, TRUE);
    $localDestinationPath = $patternMatchingTempDir . '/' . basename($remoteLargeFilePath);
    
    
    // 3. Setup Rclone for download (SFTP -> Local).
    $localProvider = new LocalProvider('temp_local_for_download_progress');
    // This Rclone instance is specifically for (SFTP -> Local) transfer.
    $downloaderRclone = new Rclone($rcloneRemote->getLeftSide(), $localProvider); // $rcloneRemote->getLeftSide() is SFTP.
    
    // 4. Assert progress for 'copyto' (underlying operation of download_to_local).
    $this->assert_progress_tracking(
      $downloaderRclone,
      'copyto',               // The operation used by download_to_local.
      $remoteLargeFilePath,   // Source path on SFTP.
      $localDestinationPath   // Destination path on local filesystem.
    );
    
    // 5. Cleanup:
    self::assertFileExists($localDestinationPath, "Downloaded file does not exist at {$localDestinationPath}.");
    $this->cleanup_temp_file_and_dir($localDestinationPath); // Cleans the local file and its temp dir.
    $rcloneRemote->deletefile($remoteLargeFilePath); // Delete the large file from SFTP.
    // Attempt to remove the working directory on SFTP if it's empty.
    if ($rcloneRemote->is_dir($this->working_directory)->exists && count($rcloneRemote->ls($this->working_directory)) === 0) {
      $rcloneRemote->rmdir($this->working_directory);
    }
  }
}