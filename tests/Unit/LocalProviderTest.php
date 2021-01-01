<?php declare(strict_types=1);

namespace CloudAtlas\Flyclone\Test\Unit;

use PHPUnit\Framework\TestCase;
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;

class LocalProviderTest extends TestCase
{
   /**  @test */
   final public function instantiate_local_provider()
   : LocalProvider
   {
      $left_side = new LocalProvider('mydisk'); // name

      self::assertInstanceOf(LocalProvider::class, $left_side);

      return $left_side;
   }

   /**
    * @test
    * @depends instantiate_local_provider
    */
   final public function instantiate_with_one_provider($left_side)
   : Rclone
   {
      $left_side = new Rclone($left_side);

      self::assertInstanceOf(Rclone::class, $left_side);

      return $left_side;
   }

   /**
    * @test
    * @depends instantiate_with_one_provider
    *    * @param $left_side Rclone
    *
    * @param $left_side Rclone
    */
   final public function directory_operations($left_side)
   : void
   {
      $dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_empty';
      $file = $dir . DIRECTORY_SEPARATOR . 'deleteme';

      self::assertDirectoryDoesNotExist($dir);
      $left_side->mkdir($dir);
      self::assertDirectoryExists($dir);
      $left_side->rmdir($dir);
      self::assertDirectoryDoesNotExist($dir);
      $left_side->mkdir($dir);
      $left_side->touch($file);
      $left_side->purge($dir);
      self::assertDirectoryDoesNotExist($dir);
   }

   /**
    * @test
    * @depends instantiate_with_one_provider
    */
   final public function list_files_from_root_dir($left_side)
   : void
   {
      $dir    = '/';
      $result = $left_side->ls($dir);

      self::assertIsArray($result);
      self::assertTrue(count($result) > 0, "I need at least one result from $dir");
      self::assertObjectHasAttribute('Name', $result[ 0 ], 'Unexpected result from ls');
   }

   /**
    * @test
    * @depends instantiate_with_one_provider
    *
    * @param $left_side Rclone
    */
   final public function touch_a_file_on_system_temp_dir($left_side)
   : array
   {
      $temp_filepath = tempnam(sys_get_temp_dir(), 'flyclone_');

      $result = $left_side->touch($temp_filepath);

      self::assertTrue($result);
      self::assertFileExists($temp_filepath, 'File not created');
      self::assertFileIsReadable($temp_filepath, 'File not readable');
      self::assertEquals(0, filesize($temp_filepath), 'File should be empty by now');

      return [ $left_side, $temp_filepath ];
   }

   /**
    * @test
    * @depends touch_a_file_on_system_temp_dir
    *
    */
   final public function write_to_a_temp_file($params)
   : array
   {
      [ $left_side, $temp_filepath ] = $params;
      $content = 'I live at https://github.com/cloudatlasid/flyclone';
      self::assertFileIsWritable($temp_filepath, 'File not writable');
      $result = file_put_contents($temp_filepath, $content);

      self::assertIsInt($result);
      self::assertNotFalse($result);
      self::assertEquals(file_get_contents($temp_filepath), $content);

      return [ $left_side, $temp_filepath ];
   }

   /**
    * @test
    * @depends write_to_a_temp_file
    *
    */
   final public function write_to_a_file($params)
   : array
   {
      $content = 'But my father lives at https://helio.me';

      /** @var Rclone $left_side */
      [ $left_side, $temp_filepath ] = $params;

      $success = $left_side->write_file($temp_filepath, $content);

      self::assertTrue($success);

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
      $tmp_dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
      $new_path = $tmp_dir . 'flyclone_local_test.txt';

      self::assertFileDoesNotExist($new_path, 'This file should not exist yet');

      $left_side->moveto($temp_filepath, $new_path);

      self::assertFileExists($new_path, 'File not renamed');

      return [ $left_side, $new_path ];
   }

   /**
    * @test
    * @depends rename_a_file
    *
    */
   final public function delete_a_file($params)
   : array
   {
      /** @var Rclone $left_side */
      [ $left_side, $filepath ] = $params;
      self::assertFileExists($filepath, 'File should exist at this point');

      $left_side->delete($filepath);

      self::assertFileDoesNotExist($filepath, 'This file should not exist by now');

      return [ $left_side ];
   }
}

