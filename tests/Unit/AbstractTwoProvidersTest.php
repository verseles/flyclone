<?php


namespace Verseles\Flyclone\Test\Unit;


use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\Provider;
use Verseles\Flyclone\Rclone;

abstract class AbstractTwoProvidersTest extends TestCase
{
  use Helpers;
  use ProgressTrackingTrait;
  
  // Added the new trait for progress testing capabilities
  
  protected string $leftProviderName        = 'undefined_disk_left'; // Name for the left (source) provider
  protected string $rightProviderName       = 'undefined_disk_right';// Name for the right (destination) provider
  protected string $left_working_directory  = '/tmp/flyclone_left';  // Base working directory for the left provider
  protected string $right_working_directory = '/tmp/flyclone_right'; // Base working directory for the right provider
  
  /**
   * Sets the name for the left provider.
   *
   * @param string $leftProviderName Name of the left provider.
   */
  final public function setLeftProviderName(string $leftProviderName) : void
  {
    $this->leftProviderName = $leftProviderName;
  }
  
  /**
   * Gets the name of the left provider.
   *
   * @return string Name of the left provider.
   */
  final public function getLeftProviderName() : string
  {
    return $this->leftProviderName;
  }
  
  /**
   * Gets the name of the right provider.
   *
   * @return string Name of the right provider.
   */
  final public function getRightProviderName() : string
  {
    return $this->rightProviderName;
  }
  
  /**
   * Sets the name for the right provider.
   *
   * @param string $rightProviderName Name of the right provider.
   */
  final public function setRightProviderName(string $rightProviderName) : void
  {
    $this->rightProviderName = $rightProviderName;
  }
  
  /**
   * Gets the working directory for the left provider.
   *
   * @return string Path to the working directory.
   */
  final public function getLeftWorkingDirectory() : string
  {
    return $this->left_working_directory;
  }
  
  /**
   * Sets the working directory for the left provider.
   *
   * @param string $left_working_directory Path to the working directory.
   */
  final public function setLeftWorkingDirectory(string $left_working_directory) : void
  {
    $this->left_working_directory = $left_working_directory;
  }
  
  /**
   * Gets the working directory for the right provider.
   *
   * @return string Path to the working directory.
   */
  final public function getRightWorkingDirectory() : string
  {
    return $this->right_working_directory;
  }
  
  /**
   * Sets the working directory for the right provider.
   *
   * @param string $right_working_directory Path to the working directory.
   */
  final public function setRightWorkingDirectory(string $right_working_directory) : void
  {
    $this->right_working_directory = $right_working_directory;
  }
  
  
  /**
   * Instantiates the left provider. Must be implemented by concrete test classes.
   *
   * @return Provider Instance of the left provider.
   */
  abstract public function instantiate_left_provider() : Provider;
  
  /**
   * Instantiates the right provider. Must be implemented by concrete test classes.
   *
   * @return Provider Instance of the right provider.
   */
  abstract public function instantiate_right_provider() : Provider;
  
  /**
   * Instantiates Rclone with two providers.
   * Depends on successful instantiation of both left and right providers.
   *
   * @param Provider $left_side  Instantiated left provider.
   * @param Provider $right_side Instantiated right provider.
   *
   * @return Rclone Instance of Rclone configured with two providers.
   * @noinspection PhpUnitTestsInspection PhpStorm flags this due to @depends on abstract methods, which is valid.
   */
  #[Test]
  #[Depends('instantiate_left_provider')]
  #[Depends('instantiate_right_provider')]
  public function instantiate_with_two_providers($left_side, $right_side) : Rclone
  {
    $two_sides_rclone = new Rclone($left_side, $right_side); // Variable name corrected
    
    self::assertInstanceOf(Rclone::class, $two_sides_rclone);
    
    return $two_sides_rclone;
  }
  
  /**
   * Tests 'touch' command on the left provider.
   * Depends on a successfully instantiated Rclone (with two providers, but 'touch' uses left_side).
   *
   * @param Rclone $two_sides_rclone Rclone instance.
   *
   * @return array Array containing Rclone instance and path to the touched file.
   */
  #[Test]
  #[Depends('instantiate_with_two_providers')]
  public function touch_a_file_on_left_side(Rclone $two_sides_rclone) : array
  {
    // Generate a unique filepath within the left provider's working directory
    $temp_filepath = $this->getLeftWorkingDirectory() . '/flyclone_left_touch_' . $this->random_string();
    
    // Rclone 'touch' operates on its configured left_side by default when called on $two_sides_rclone
    $result = $two_sides_rclone->touch($temp_filepath);
    self::assertTrue($result, "Rclone touch command failed for {$temp_filepath} on left provider.");
    
    $file_info = $two_sides_rclone->is_file($temp_filepath); // Check on left_side
    self::assertTrue($file_info->exists, "File not created at {$temp_filepath} on left provider after touch.");
    self::assertTrue(
      (isset($file_info->details->Size) && ($file_info->details->Size === 0 || $file_info->details->Size === -1)),
      'Touched file on left provider should be empty. Actual size: ' . ($file_info->details->Size ?? 'N/A')
    );
    
    return [$two_sides_rclone, $temp_filepath];
  }
  
  /**
   * Tests writing content to a file on the left provider using 'rcat'.
   * Depends on a file successfully created by 'touch_a_file_on_left_side'.
   *
   * @param array $params Array from previous test: [Rclone instance, filepath on left_side].
   *
   * @return array Array containing Rclone instance, filepath, and content.
   */
  #[Test]
  #[Depends('touch_a_file_on_left_side')]
  public function write_to_a_file_on_left_side($params) : array
  {
    $content = 'But my father lives at https://helio.me :)';
    
    /** @var Rclone $two_sides_rclone */
    [$two_sides_rclone, $temp_filepath] = $params;
    
    // 'rcat' targets the left_side of the $two_sides_rclone instance
    $two_sides_rclone->rcat($temp_filepath, $content);
    
    $file_content = $two_sides_rclone->cat($temp_filepath); // 'cat' also targets left_side
    self::assertEquals($content, $file_content, "File content mismatch for {$temp_filepath} on left provider.");
    
    return [$two_sides_rclone, $temp_filepath, $content];
  }
  
  /**
   * Tests moving a file from the left provider to the right provider.
   * Depends on a file successfully written by 'write_to_a_file_on_left_side'.
   *
   * @param array $params Array from previous test: [Rclone instance, source filepath on left, content].
   *
   * @return array Array containing the main Rclone instance, an Rclone instance for right_side, new filepath on right, and content.
   */
  #[Test]
  #[Depends('write_to_a_file_on_left_side')]
  public function move_file_to_right_side(array $params) : array
  {
    /** @var Rclone $two_sides_rclone */
    [$two_sides_rclone, $file_on_left_side, $content] = $params;
    
    // Define destination path on the right provider's working directory
    $new_place_on_right = $this->getRightWorkingDirectory() . '/' . basename($file_on_left_side);
    // Ensure the parent directory exists on the right side (especially for non-local/dir-agnostic)
    $rclone_for_right_ops = new Rclone($two_sides_rclone->getRightSide());
    $rclone_for_right_ops->mkdir(dirname($new_place_on_right));
    
    
    // 'moveto' from $two_sides_rclone uses its left_side as source and right_side as destination
    $two_sides_rclone->moveto($file_on_left_side, $new_place_on_right);
    
    // Verify the file now exists on the right_side
    $check_new_place_on_right = $rclone_for_right_ops->is_file($new_place_on_right);
    self::assertTrue($check_new_place_on_right->exists, "File not moved to {$new_place_on_right} on right provider.");
    // If the right side is not dir-agnostic and file had content, check size.
    if (!$two_sides_rclone->isRightSideDirAgnostic() && isset($check_new_place_on_right->details->Size)) {
      self::assertGreaterThan(0, $check_new_place_on_right->details->Size, "Moved file {$new_place_on_right} on right provider has incorrect size.");
    }
    
    // Verify the file was removed from the left_side
    $rclone_for_left_ops = new Rclone($two_sides_rclone->getLeftSide());
    $check_old_place_on_left = $rclone_for_left_ops->is_file($file_on_left_side);
    self::assertFalse($check_old_place_on_left->exists, "File {$file_on_left_side} still exists on left provider after moveto.");
    
    
    return [$two_sides_rclone, $rclone_for_right_ops, $new_place_on_right, $content];
  }
  
  /**
   * Tests downloading a file (from the right provider, which now holds the file) to local storage.
   * Depends on 'move_file_to_right_side'.
   *
   * @param array $params Array from previous test.
   *
   * @return array Array containing the main Rclone instance, path to downloaded local file, and content.
   */
  #[Test]
  #[Depends('move_file_to_right_side')]
  public function download_to_local(array $params) : array
  {
    /** @var Rclone $two_sides_rclone_main_config Not directly used for download, but passed through */
    /** @var Rclone $rclone_for_right_ops Configured with right_side as its left_side (source for download) */
    [$two_sides_rclone_main_config, $rclone_for_right_ops, $filepath_on_right, $content] = $params;
    
    // download_to_local will use $rclone_for_right_ops's left_side (which is our right provider) as source
    $downloadResult = $rclone_for_right_ops->download_to_local($filepath_on_right);
    
    self::assertTrue($downloadResult->success, "Download failed for {$filepath_on_right}.");
    self::assertFileExists($downloadResult->local_path, "File not downloaded to local path: {$downloadResult->local_path}.");
    
    // Verify content of the downloaded file
    $local_downloaded_content = file_get_contents($downloadResult->local_path);
    self::assertEquals($content, $local_downloaded_content, "Content mismatch for downloaded file {$downloadResult->local_path}.");
    
    // Clean up the local downloaded file and its temporary directory
    unlink($downloadResult->local_path);
    rmdir(dirname($downloadResult->local_path));
    
    
    return [$two_sides_rclone_main_config, $rclone_for_right_ops, $filepath_on_right, $content]; // Pass $rclone_for_right_ops for next step
  }
  
  /**
   * Tests deleting a file from the right provider.
   * Depends on 'download_to_local' (primarily for parameter passing, file is still on right provider).
   *
   * @param array $params Array from previous test.
   *
   * @return array Array containing the main Rclone instance and the Rclone instance for right_side.
   */
  #[Test]
  #[Depends('download_to_local')]
  public function delete_file_on_right_side($params) : array
  {
    /** @var Rclone $two_sides_rclone_main_config */
    /** @var Rclone $rclone_for_right_ops Configured with right_side as its left_side */
    [$two_sides_rclone_main_config, $rclone_for_right_ops, $filepath_on_right] = $params;
    
    $file_check_before = $rclone_for_right_ops->is_file($filepath_on_right);
    self::assertTrue($file_check_before->exists, "File {$filepath_on_right} should exist on right provider before deletion.");
    
    $rclone_for_right_ops->delete($filepath_on_right); // Delete operation on $rclone_for_right_ops targets its left_side
    
    $file_check_after = $rclone_for_right_ops->is_file($filepath_on_right);
    self::assertFalse($file_check_after->exists, "File {$filepath_on_right} should not exist on right provider after deletion.");
    
    // Cleanup parent dir if it was created by test and is now empty
    $parentDirRight = dirname($filepath_on_right);
    if ($rclone_for_right_ops->is_dir($parentDirRight)->exists && count($rclone_for_right_ops->ls($parentDirRight)) === 0) {
      $rclone_for_right_ops->rmdir($parentDirRight);
    }
    
    
    return [$two_sides_rclone_main_config, $rclone_for_right_ops];
  }
  
  /**
   * Tests move operation between two providers with progress tracking.
   * Depends on `write_to_a_file_on_left_side` to ensure there's a file to work with,
   * though this test creates its own larger file for better progress observation.
   *
   * @param array $params Output from write_to_a_file_on_left_side: [Rclone $two_sides_rclone, string $fileOnLeftSidePath, string $content]
   */
  #[Test]
  #[Depends('write_to_a_file_on_left_side')]
  public function test_move_with_progress_between_providers(array $params) : void
  {
    /** @var Rclone $two_sides_rclone This instance is configured (Left -> Right) */
    [$two_sides_rclone, $smallFileOnLeftSidePathIgnored, $contentIgnored] = $params;
    
    $leftProviderInstance = $two_sides_rclone->getLeftSide();
    $rightProviderInstance = $two_sides_rclone->getRightSide();
    
    // 1. Create a large file content string (e.g., 1MB)
    $largeContentSizeMB = 1;
    $largeContent = str_repeat('0', $largeContentSizeMB * 1024 * 1024);
    
    // 2. Define source path on the left provider and use rcat to write the large content to it.
    // This Rclone instance will operate only on the left provider.
    $rcloneLeftOnly = new Rclone($leftProviderInstance);
    $largeFileSourcePathOnLeft = $this->getLeftWorkingDirectory() . '/large_source_for_progress_' . $this->random_string() . '.dat';
    $rcloneLeftOnly->mkdir(dirname($largeFileSourcePathOnLeft)); // Ensure parent directory exists on the left provider.
    $rcloneLeftOnly->rcat($largeFileSourcePathOnLeft, $largeContent);
    self::assertTrue($rcloneLeftOnly->is_file($largeFileSourcePathOnLeft)->exists, "Large source file was not created on the left provider at {$largeFileSourcePathOnLeft}.");
    
    // 3. Define destination directory on the right provider.
    // This Rclone instance will operate only on the right provider for setup.
    $rcloneRightOnly = new Rclone($rightProviderInstance);
    $destinationDirOnRightSide = $this->getRightWorkingDirectory() . '/progress_test_dest_dir_' . $this->random_string();
    $rcloneRightOnly->mkdir($destinationDirOnRightSide); // Ensure destination directory exists on the right provider.
    
    // 4. Assert progress tracking for the 'move' operation using the $two_sides_rclone instance.
    // The 'move' command will transfer from left to right and then delete from source.
    $this->assert_progress_tracking(
      $two_sides_rclone, // This Rclone instance is configured for (Left -> Right) transfers.
      'move',            // The Rclone operation to test.
      $largeFileSourcePathOnLeft, // Source path on the left provider.
      $destinationDirOnRightSide  // Destination directory path on the right provider.
    );
    
    // 5. Cleanup
    // Verify the file was moved from the left provider (should no longer exist).
    self::assertFalse($rcloneLeftOnly->is_file($largeFileSourcePathOnLeft)->exists,
                      "Source file {$largeFileSourcePathOnLeft} still exists on the left provider after 'move' operation.");
    
    // Purge the destination directory on the right side to remove the moved file and the directory itself.
    $rcloneRightOnly->purge($destinationDirOnRightSide);
    
    // Clean up parent directory of the large source file on the left provider if it's empty.
    $parentDirLeft = dirname($largeFileSourcePathOnLeft);
    if ($rcloneLeftOnly->is_dir($parentDirLeft)->exists && count($rcloneLeftOnly->ls($parentDirLeft)) === 0) {
      $rcloneLeftOnly->rmdir($parentDirLeft);
    }
  }
}