<?php


namespace CloudAtlas\Flyclone\Test\Unit;


use CloudAtlas\Flyclone\Providers\Provider;
use CloudAtlas\Flyclone\Rclone;
use PHPUnit\Framework\TestCase;

abstract class AbstractProviderTest extends TestCase
{
   use Helpers;

   protected string $leftProviderName = 'undefined_disk';
   protected string $working_directory = '/tmp';

   /**
    * @param string $leftProviderName
    */
   final public function setLeftProviderName(string $leftProviderName)
   : void
   {
      $this->leftProviderName = $leftProviderName;
   }

   /**
    * @return string
    */
   final public function getLeftProviderName()
   : string
   {
      return $this->leftProviderName;
   }


   abstract public function instantiate_left_provider()
   : Provider;

   /**
    * @test
    * @depends      instantiate_left_provider
    * @noinspection PhpUnitTestsInspection
    */
   public function instantiate_with_one_provider($left_side)
   : Rclone
   {
      $left_side = new Rclone($left_side);

      self::assertInstanceOf(Rclone::class, $left_side);

      return $left_side;
   }

   /**
    * @test
    * @depends instantiate_with_one_provider
    *
    * @param $left_side Rclone
    */
   public function touch_a_file( Rclone $left_side)
   : array
   {
      $temp_filepath = $this->working_directory . '/flyclone_' . $this->random_string();

      $result = $left_side->touch($temp_filepath);

      self::assertTrue($result);

      $file = $left_side->is_file($temp_filepath);

      self::assertTrue($file->exists, 'File not created');

      self::assertEquals(0, $file->details->Size ?? 9999, 'File should be empty by now');

      return [ $left_side, $temp_filepath ];
   }

   /**
    * @test
    * @depends touch_a_file
    *
    */
   public function write_to_a_file($params)
   : array
   {
      $content = 'But my father lives at https://helio.me';

      /** @var Rclone $left_side */
      [ $left_side, $temp_filepath ] = $params;

      $left_side->rcat($temp_filepath, $content);

      $file_content = $left_side->cat($temp_filepath);
      self::assertEquals($file_content, $content, 'File content are different');

      return [ $left_side, $temp_filepath ];
   }

   /**
    * @test
    * @depends write_to_a_file
    *
    */
   final public function rename_a_file($params)
   : array
   {

      /** @var Rclone $left_side */
      [ $left_side, $temp_filepath ] = $params;
      $tmp_dir  = $this->working_directory . '/';
      $new_path = $tmp_dir . 'flyclone_test.txt';

      $new_file = $left_side->is_file($new_path);

      self::assertFalse($new_file->exists, 'This file should not exist yet');

      $left_side->moveto($temp_filepath, $new_path);

      $old_file = $left_side->is_file($temp_filepath);
      self::assertFalse($old_file->exists, 'This file should not exist anymore');

      $new_file = $left_side->is_file($new_path);
      self::assertTrue($new_file->exists, 'This file should exist by now');

      return [ $left_side, $new_path ];
   }

   /**
    * @test
    * @depends rename_a_file
    *
    */
   public function delete_a_file($params)
   : array
   {
      /** @var Rclone $left_side */
      [ $left_side, $filepath ] = $params;

      $file = $left_side->is_file($filepath);
      self::assertTrue($file->exists, 'File should exist at this point');

      $left_side->delete($filepath);

      $file = $left_side->is_file($filepath);

      self::assertFalse($file->exists, 'This file should not exist anymore');

      return [ $left_side ];
   }

   /**
    * @test
    * @depends instantiate_with_one_provider
    */
   public function make_a_directory(Rclone $left_side)
   : array
   {
      $dir = $this->working_directory . '/flyclone_empty';

      $check_dir = $left_side->is_dir($dir);
      self::assertFalse($check_dir->exists, 'Directory should not exist yet');

      $left_side->mkdir($dir);
      $check_dir = $left_side->is_dir($dir);
      self::assertTrue($check_dir->exists || $left_side->isLeftSideFolderAgnostic(), 'Directory should exist by now');

      return [ $left_side, $dir ];
   }

   /**
    * @test
    * @depends make_a_directory
    */
   public function make_a_directory_inside_the_previous(array $params)
   : array
   {
      /** @var $left_side Rclone */
      [ $left_side, $dir ] = $params;

      $new_dir = $dir . '/flyclone';

      $left_side->mkdir($new_dir);
      $check_dir = $left_side->is_dir($new_dir);
      self::assertTrue($check_dir->exists || $left_side->isLeftSideFolderAgnostic(), 'Directory should exist by now');

      return [ $left_side, $dir, $new_dir ];
   }

   /**
    * @test
    * @depends make_a_directory_inside_the_previous
    */
   public function touch_a_file_inside_first_directory(array $params)
   : array
   {
      /** @var $left_side Rclone */
      [ $left_side, $first_dir, $latest_dir ] = $params;

      $new_file = $first_dir . '/delete-me';

      $content = 'JUST DO IT';
      $left_side->rcat($new_file, $content);

      $file_content = $left_side->cat($new_file);
      self::assertEquals($file_content, $content, 'File content are different');

      return [ $left_side, $first_dir, $latest_dir, $new_file ];
   }

   /**
    * @test
    * @depends touch_a_file_inside_first_directory
    */
   public function copy_latest_file_to_first_directory(array $params)
   : array
   {
      /** @var $left_side Rclone */
      [ $left_side, $first_dir, $latest_dir, $latest_file ] = $params;
      $copy_file = $first_dir . '/' . basename($latest_file);
      $left_side->copy($latest_file, $first_dir);

      $check_original = $left_side->is_file($latest_file);
      $check_copy     = $left_side->is_file($copy_file);

      self::assertTrue($check_copy->exists, 'File not copied');
      self::assertEquals($check_original->details->Size, $check_copy->details->Size, 'File not copied correctly');

      return [ $left_side, $first_dir, $latest_dir, $latest_file, $copy_file ];

   }

   /**
    * @test
    *
    * @depends copy_latest_file_to_first_directory
    */
   public function move_latest_file_to_latest_directory(array $params)
   : array
   {
      /** @var $left_side Rclone */
      [ $left_side, $first_dir, $latest_dir, $latest_file ] = $params;

      $new_place = $latest_dir . '/' . basename($latest_file);

      $left_side->moveto($latest_file, $new_place);

      $check_new_place = $left_side->is_file($new_place);
      self::assertTrue($check_new_place->exists, 'File not moved');
      self::assertGreaterThan(0, $check_new_place->details->Size, 'File not moved correctly');

      return [ $left_side, $first_dir, $latest_dir, $new_place ];
   }

   /**
    * @test
    * @depends move_latest_file_to_latest_directory
    */
   public function list_directory(array $params): Rclone
   {
      $left_side = $params[0];
      $dir    = dirname($this->working_directory);
      $result = $left_side->ls($dir);

      self::assertIsArray($result);
      self::assertTrue(count($result) > 0, "I need at least one result from $dir");
      self::assertObjectHasAttribute('Name', $result[0], 'Unexpected result from ls');

      return $left_side;
   }

   /**
    * @test
    * @depends move_latest_file_to_latest_directory
    */
   public function purge_first_directory_created(array $params)
   : array
   {
      /** @var $left_side Rclone */
      [ $left_side, $first_dir, $latest_dir, $latest_file ] = $params;

      $left_side->purge($first_dir);
      $check_dir = $left_side->is_dir($first_dir);

      self::assertFalse($check_dir->exists, 'The directory should not exist anymore');

      return $params;
   }
}
