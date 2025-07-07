<?php


namespace Verseles\Flyclone\Test\Unit;


use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Verseles\Flyclone\Providers\Provider;
use Verseles\Flyclone\Rclone;

abstract class AbstractProviderTest extends TestCase
{
  use Helpers;
  use ProgressTrackingTrait;
  
  // Added the new trait for progress testing capabilities
  
  protected string $leftProviderName  = 'undefined_disk'; // Name of the provider under test
  protected string $working_directory = '/tmp'; // Base working directory for tests on the provider
  
  /**
   * Sets the name for the left provider.
   *
   * @param string $leftProviderName Name of the provider.
   */
  final public function setLeftProviderName(string $leftProviderName) : void
  {
    $this->leftProviderName = $leftProviderName;
  }
  
  /**
   * Gets the name of the left provider.
   *
   * @return string Name of the provider.
   */
  final public function getLeftProviderName() : string
  {
    return $this->leftProviderName;
  }
  
  /**
   * Instantiates the specific provider being tested.
   * This method must be implemented by concrete test classes.
   *
   * @return Provider Instance of the provider.
   */
  abstract public function instantiate_left_provider() : Provider;
  
  /**
   * Instantiates Rclone with a single provider (the one under test).
   * Depends on the successful instantiation of the provider.
   *
   * @param Provider $left_side The instantiated provider.
   *
   * @return Rclone Instance of Rclone configured with the provider.
   */
  #[Test]
  #[Depends('instantiate_left_provider')]
  public function instantiate_with_one_provider($left_side) : Rclone
  {
    $rclone_instance = new Rclone($left_side); // Corrected variable name
    
    self::assertInstanceOf(Rclone::class, $rclone_instance);
    
    return $rclone_instance;
  }
  
  /**
   * Tests the 'touch' command to create an empty file.
   * Depends on a successfully instantiated Rclone instance.
   *
   * @param Rclone $left_side Rclone instance.
   *
   * @return array Returns an array containing the Rclone instance and the path to the touched file.
   */
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function touch_a_file(Rclone $left_side) : array
  {
    // Generate a unique filepath within the working directory
    $temp_filepath = $this->working_directory . '/flyclone_touch_' . $this->random_string();
    
    $result = $left_side->touch($temp_filepath); // Execute touch command
    self::assertTrue($result, "Rclone touch command failed for {$temp_filepath}");
    
    $file_info = $left_side->is_file($temp_filepath); // Verify file existence
    
    self::assertTrue($file_info->exists, "File not created at {$temp_filepath} after touch.");
    // For most providers, a touched file has size 0. Some might report -1 or other.
    // Allowing 0 or -1 for size check for newly touched files.
    self::assertTrue(
      (isset($file_info->details->Size) && ($file_info->details->Size === 0 || $file_info->details->Size === -1)),
      'File created by touch should be empty (Size 0 or -1). Actual size: ' . ($file_info->details->Size ?? 'N/A')
    );
    
    
    return [$left_side, $temp_filepath];
  }
  
  /**
   * Tests writing content to a file using 'rcat'.
   * Depends on a file successfully created by 'touch_a_file'.
   *
   * @param array $params Array from touch_a_file: [Rclone instance, filepath].
   *
   * @return array Returns an array containing the Rclone instance, the filepath, and the written content.
   */
  #[Test]
  #[Depends('touch_a_file')]
  public function write_to_a_file($params) : array
  {
    $content = 'But my father lives at https://helio.me';
    
    /** @var Rclone $left_side */
    [$left_side, $temp_filepath] = $params;
    
    $left_side->rcat($temp_filepath, $content); // Write content using rcat
    
    $file_content = $left_side->cat($temp_filepath); // Read content back using cat
    self::assertEquals($content, $file_content, "File content mismatch after rcat for {$temp_filepath}.");
    
    return [$left_side, $temp_filepath, $content]; // Pass Rclone instance and filepath for further tests
  }
  
  /**
   * Tests copying and then renaming (moving) a file on the same provider.
   * This now includes checking transfer stats on the copy operation.
   * Depends on a file successfully written by 'write_to_a_file'.
   *
   * @param array $params Array from write_to_a_file: [Rclone instance, old filepath, content].
   *
   * @return array Returns an array containing the Rclone instance, the original filepath, and the new renamed filepath.
   */
  #[Test]
  #[Depends('write_to_a_file')]
  final public function copy_and_rename_a_file(array $params) : array
  {
    /** @var Rclone $left_side */
    [$left_side, $temp_filepath, $content] = $params;
    
    // 1. Copy the file and check stats
    $copied_file_path = $this->working_directory . '/flyclone_copied_file_' . $this->random_string() . '.txt';
    $copy_result = $left_side->copyto($temp_filepath, $copied_file_path);
    
    self::assertTrue($copy_result->success, 'copyto operation should be successful.');
    self::assertObjectHasProperty('stats', $copy_result, "The result object should have a 'stats' property.");
    self::assertEquals(strlen($content), $copy_result->stats->bytes, 'Bytes transferred in copyto should match content length.');
    
    $check_copy = $left_side->is_file($copied_file_path);
    self::assertTrue($check_copy->exists, "File not copied to {$copied_file_path}.");
    
    
    // 2. Rename the *copied* file
    $new_path = $this->working_directory . '/flyclone_renamed_file_' . $this->random_string() . '.txt';
    $new_file_check_before = $left_side->is_file($new_path);
    self::assertFalse($new_file_check_before->exists, "New file path {$new_path} should not exist before moveto.");
    
    $left_side->moveto($copied_file_path, $new_path); // Execute moveto (rename) on the copied file
    
    $old_file_check_after = $left_side->is_file($copied_file_path);
    self::assertFalse($old_file_check_after->exists, "Copied file {$copied_file_path} should not exist after moveto.");
    
    $new_file_check_after = $left_side->is_file($new_path);
    self::assertTrue($new_file_check_after->exists, "New file {$new_path} should exist after moveto.");
    
    // Verify size if not dir agnostic
    if (!$left_side->isLeftSideDirAgnostic() && isset($new_file_check_after->details->Size)) {
      self::assertGreaterThan(0, $new_file_check_after->details->Size, "Renamed file {$new_path} should have size greater than 0 if it had content.");
    }
    
    // Return original and final renamed path for cleanup
    return [$left_side, $temp_filepath, $new_path];
  }
  
  /**
   * Tests deleting multiple files.
   * Depends on files successfully created by 'copy_and_rename_a_file'.
   *
   * @param array $params Array from copy_and_rename_a_file: [Rclone instance, original_filepath, renamed_filepath].
   *
   * @return array Returns an array containing the Rclone instance.
   */
  #[Test]
  #[Depends('copy_and_rename_a_file')]
  public function delete_a_file(array $params) : array
  {
    /** @var Rclone $left_side */
    [$left_side, $original_filepath, $renamed_filepath] = $params;
    
    // Delete the original file
    $file_check_before_orig = $left_side->is_file($original_filepath);
    self::assertTrue($file_check_before_orig->exists, "Original file {$original_filepath} should exist before deletion.");
    $left_side->deletefile($original_filepath);
    $file_check_after_orig = $left_side->is_file($original_filepath);
    self::assertFalse($file_check_after_orig->exists, "Original file {$original_filepath} should not exist after deletion.");
    
    // Delete the renamed file
    $file_check_before_renamed = $left_side->is_file($renamed_filepath);
    self::assertTrue($file_check_before_renamed->exists, "Renamed file {$renamed_filepath} should exist before deletion.");
    $left_side->deletefile($renamed_filepath);
    $file_check_after_renamed = $left_side->is_file($renamed_filepath);
    self::assertFalse($file_check_after_renamed->exists, "Renamed file {$renamed_filepath} should not exist after deletion.");
    
    return [$left_side];
  }
  
  /**
   * Tests creating a directory.
   * Depends on a successfully instantiated Rclone instance.
   *
   * @param Rclone $left_side Rclone instance.
   *
   * @return array Returns an array containing the Rclone instance and the path to the created directory.
   */
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function make_a_directory(Rclone $left_side) : array
  {
    // Define a unique directory path within the working directory
    $dir_path = $this->working_directory . '/flyclone_test_dir_' . $this->random_string();
    
    $check_dir_before = $left_side->is_dir($dir_path);
    self::assertFalse($check_dir_before->exists, "Directory {$dir_path} should not exist before mkdir.");
    
    $left_side->mkdir($dir_path); // Create the directory
    $check_dir_after = $left_side->is_dir($dir_path);
    // For dir-agnostic providers, an empty directory might not "exist" until it has content.
    // So, we assert it exists OR the provider is dir-agnostic.
    self::assertTrue(
      $check_dir_after->exists || $left_side->isLeftSideDirAgnostic(),
      "Directory {$dir_path} should exist after mkdir (or provider is dir-agnostic)."
    );
    
    return [$left_side, $dir_path];
  }
  
  /**
   * Tests creating a nested directory.
   * Depends on a directory successfully created by 'make_a_directory'.
   *
   * @param array $params Array from make_a_directory: [Rclone instance, parent directory path].
   *
   * @return array Returns an array containing the Rclone instance, parent dir path, and nested dir path.
   */
  #[Test]
  #[Depends('make_a_directory')]
  public function make_a_directory_inside_the_previous(array $params) : array
  {
    /** @var Rclone $left_side */
    [$left_side, $parent_dir_path] = $params;
    // Define a nested directory path
    $nested_dir_path = $parent_dir_path . '/flyclone_nested_dir_' . $this->random_string();
    
    $left_side->mkdir($nested_dir_path); // Create the nested directory
    $check_nested_dir = $left_side->is_dir($nested_dir_path);
    self::assertTrue(
      $check_nested_dir->exists || $left_side->isLeftSideDirAgnostic(),
      "Nested directory {$nested_dir_path} should exist after mkdir (or provider is dir-agnostic)."
    );
    
    return [$left_side, $parent_dir_path, $nested_dir_path];
  }
  
  /**
   * Tests touching a file inside a previously created directory.
   * Depends on successful creation of nested directories.
   *
   * @param array $params Array from previous test: [Rclone, parent dir, nested dir].
   *
   * @return array Returns array with Rclone instance, parent dir, nested dir, and new file path.
   */
  #[Test]
  #[Depends('make_a_directory_inside_the_previous')]
  public function touch_a_file_inside_first_directory(array $params) : array
  {
    /** @var Rclone $left_side */
    [$left_side, $first_dir_path, $latest_dir_path] = $params; // $latest_dir_path is the nested one
    // Create a file inside the *first* (parent) directory
    $new_file_path = $first_dir_path . '/file_in_parent_' . $this->random_string() . '.txt';
    $content = 'CONTENT FOR FILE IN PARENT DIR';
    
    $left_side->rcat($new_file_path, $content); // Create and write content to the file
    
    $file_content = $left_side->cat($new_file_path); // Read content back
    self::assertEquals($content, $file_content, "Content mismatch for file {$new_file_path}.");
    
    return [$left_side, $first_dir_path, $latest_dir_path, $new_file_path];
  }
  
  /**
   * Tests copying a file to another directory on the same provider.
   * Depends on a file successfully created by 'touch_a_file_inside_first_directory'.
   *
   * @param array $params Array from previous test: [Rclone, parent dir, nested dir, source file path].
   *
   * @return array Returns array with Rclone, parent dir, nested dir, source file path, copied file path.
   */
  #[Test]
  #[Depends('touch_a_file_inside_first_directory')]
  public function copy_latest_file_to_first_directory(array $params) // Method name a bit misleading now
  : array
  {
    /** @var Rclone $left_side */
    // $latest_file refers to $new_file_path from previous test (file_in_parent)
    [$left_side, $first_dir_path, $latest_dir_path, $source_file_path] = $params;
    // Copy the source file into the *latest_dir_path* (nested directory)
    $copied_file_path_in_nested_dir = $latest_dir_path . '/' . basename($source_file_path);
    
    $left_side->copy($source_file_path, $latest_dir_path); // Copy file to directory
    
    $check_original = $left_side->is_file($source_file_path);
    $check_copy = $left_side->is_file($copied_file_path_in_nested_dir);
    
    self::assertTrue($check_original->exists, "Original file {$source_file_path} should still exist after copy.");
    self::assertTrue($check_copy->exists, "File not copied to {$copied_file_path_in_nested_dir}.");
    if (isset($check_original->details->Size) && isset($check_copy->details->Size)) {
      self::assertEquals($check_original->details->Size, $check_copy->details->Size, 'Copied file size mismatch.');
    }
    
    
    return [$left_side, $first_dir_path, $latest_dir_path, $source_file_path, $copied_file_path_in_nested_dir];
  }
  
  /**
   * Tests moving a file to another directory on the same provider.
   * Uses the copied file from 'copy_latest_file_to_first_directory' (which is in nested_dir)
   * and moves it back to the first_dir (parent_dir).
   *
   * @param array $params Array from previous test.
   *
   * @return array Returns array with Rclone, parent dir, nested dir, and the new path of the moved file.
   */
  #[Test]
  #[Depends('copy_latest_file_to_first_directory')]
  public function move_latest_file_to_latest_directory(array $params)  // Method name can be improved
  : array
  {
    /** @var Rclone $left_side */
    // $latest_file is $source_file_path (in parent_dir), $copy_file is $copied_file_path_in_nested_dir
    [$left_side, $first_dir_path, $latest_dir_path, $original_source_file_path, $file_to_move_path] = $params;
    
    // We will move $file_to_move_path (from nested_dir) back to $first_dir_path (parent_dir) with a new name.
    $moved_file_new_name_in_first_dir = $first_dir_path . '/moved_back_' . basename($file_to_move_path);
    
    $left_side->moveto($file_to_move_path, $moved_file_new_name_in_first_dir);
    
    $check_original_location = $left_side->is_file($file_to_move_path); // Should be gone from nested_dir
    self::assertFalse($check_original_location->exists, "File {$file_to_move_path} should not exist in nested dir after moveto.");
    
    $check_new_location = $left_side->is_file($moved_file_new_name_in_first_dir); // Should exist in parent_dir
    self::assertTrue($check_new_location->exists, "File not moved to {$moved_file_new_name_in_first_dir}.");
    if (isset($check_new_location->details->Size) && !$left_side->isLeftSideDirAgnostic()) {
      self::assertGreaterThan(0, $check_new_location->details->Size, "Moved file {$moved_file_new_name_in_first_dir} size error.");
    }
    
    
    return [$left_side, $first_dir_path, $latest_dir_path, $moved_file_new_name_in_first_dir];
  }
  
  /**
   * Tests listing contents of a directory.
   * Depends on the state after 'move_latest_file_to_latest_directory'.
   *
   * @param array $params Array from previous test.
   *
   * @return Rclone The Rclone instance.
   */
  #[Test]
  #[Depends('move_latest_file_to_latest_directory')]
  public function list_directory(array $params) : Rclone
  {
    /** @var Rclone $left_side */
    [$left_side, $first_dir_path, $latest_dir_path, $file_in_first_dir] = $params;
    
    // List the $first_dir_path, it should contain $file_in_first_dir and possibly $original_source_file_path
    $listing_result = $left_side->ls($first_dir_path);
    
    self::assertIsArray($listing_result);
    self::assertTrue(count($listing_result) > 0, "Listing of {$first_dir_path} should not be empty.");
    self::assertObjectHasProperty('Name', $listing_result[0], 'Unexpected result structure from ls.');
    
    // Check if one of the listed items is the file we expect to be there.
    $foundExpectedFile = FALSE;
    foreach ($listing_result as $item) {
      if ($item->Name === basename($file_in_first_dir)) {
        $foundExpectedFile = TRUE;
        break;
      }
    }
    self::assertTrue($foundExpectedFile, 'Expected file ' . basename($file_in_first_dir) . " not found in ls result of {$first_dir_path}.");
    
    
    return $left_side;
  }
  
  /**
   * Tests purging a directory (removing directory and all its contents).
   * Depends on the state after 'move_latest_file_to_latest_directory'.
   *
   * @param array $params Array from previous test.
   *
   * @return array The Rclone instance.
   */
  #[Test]
  #[Depends('move_latest_file_to_latest_directory')]
  public function purge_first_directory_created(array $params) : array
  {
    /** @var Rclone $left_side */
    [$left_side, $first_dir_path, $latest_dir_path /*, ... */] = $params;
    
    // Purge the $first_dir_path (which should contain files and the $latest_dir_path as a subdirectory)
    $left_side->purge($first_dir_path);
    $check_dir_after_purge = $left_side->is_dir($first_dir_path);
    
    self::assertFalse($check_dir_after_purge->exists, "Directory {$first_dir_path} should not exist after purge.");
    // Also check that the nested directory is gone as part of the purge
    $check_nested_dir_after_purge = $left_side->is_dir($latest_dir_path);
    self::assertFalse($check_nested_dir_after_purge->exists, "Nested directory {$latest_dir_path} should not exist after parent purge.");
    
    
    return $params; // Return original params for consistency, though not strictly needed for next step
  }
  
  /**
   * Tests copy operation with progress tracking on the same provider.
   * Depends on a file being available from 'write_to_a_file' to get a working rclone instance.
   *
   * @param array $params Output from write_to_a_file: [Rclone $rclone, string $sourceFilePath]
   */
  #[Test]
  #[Depends('write_to_a_file')]
  public function test_copy_with_progress_on_same_provider(array $params) : void
  {
    /** @var Rclone $rclone */
    [$rclone, $originalSourceFilePath] = $params; // This file might be small, so we'll create a larger one.
    
    // Destination directory for the copy operation
    $destinationDir = $this->working_directory . '/progress_test_dest_dir_' . $this->random_string();
    $rclone->mkdir($destinationDir); // Ensure destination directory exists
    
    // Create a larger temporary file for more reliable progress reporting on the rclone's left_side.
    $largeSourceFileContent = str_repeat('0', 1 * 1024 * 1024); // 1MB of '0's
    $largeSourceFilePathOnProvider = $this->working_directory . '/large_source_for_copy_progress_' . $this->random_string() . '.dat';
    $rclone->rcat($largeSourceFilePathOnProvider, $largeSourceFileContent);
    self::assertTrue($rclone->is_file($largeSourceFilePathOnProvider)->exists, 'Large source file not created on provider for copy progress test.');
    
    
    $this->assert_progress_tracking(
      $rclone,
      'copy', // Rclone operation to test
      $largeSourceFilePathOnProvider, // Source path (the large file on the provider)
      $destinationDir   // Destination directory path
    );
    
    // Cleanup
    $rclone->deletefile($largeSourceFilePathOnProvider); // Delete the large source file from the provider
    $rclone->purge($destinationDir); // Clean up the destination directory and its contents
  }
}