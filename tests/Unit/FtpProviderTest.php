<?php declare(strict_types=1);

namespace CloudAtlas\Flyclone\Test\Unit;

use PHPUnit\Framework\TestCase;
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\FtpProvider;

class FtpProviderTest extends TestCase
{
   /**  @test */
   final public function instantiate_ftp_provider()
   : FtpProvider
   {
      $left_side = new FtpProvider('my_ftp_disk', [
          'HOST' => 'ftp.dlptest.com',
          'USER' => 'dlpuser@dlptest.com',
          'PASS' => Rclone::obscure('eUj8GeW55SvYaswqUyDSm5v6N'),
      ]);

      self::assertInstanceOf(FtpProvider::class, $left_side);

      return $left_side;
   }

   /**
    * @test
    * @depends instantiate_ftp_provider
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
    */
   final public function list_remote_root_directory($left_side)

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
    * @param Rclone $left_side
    */
   final public function file_operations($left_side)
   : void
   {
      $file    = '/flyclone_' . random_int(100, 999) . '.txt';
      $success = $left_side->touch($file);
      self::assertTrue($success);

      $content = 'I live at https://helio.me';
      $success = $left_side->write_file($file, $content);
      self::assertTrue($success);

      $file_content = $left_side->cat($file);
      self::assertEquals($file_content, $content, 'File content are different');

      $success = $left_side->moveto($file, "$file.zip");
      self::assertTrue($success);
      $success = $left_side->delete("$file.zip");
      self::assertTrue($success);
   }

   /**
    * @test
    * @depends instantiate_with_one_provider
    *
    * @param Rclone $left_side
    */
   final public function directory_operations($left_side)
   : void
   {
      $dir        = '/flyclone_' . random_int(100, 999);
      $dir_inside = "$dir/flyclone";
      $file       = $dir . '/flyclone_' . random_int(100, 999) . '.txt';
      $success    = $left_side->mkdir($dir);
      self::assertTrue($success);
      $success = $left_side->mkdir($dir_inside);
      self::assertTrue($success);

      $content = 'I live at https://helio.me';
      $success = $left_side->write_file($file, $content);
      self::assertTrue($success);

      $success = $left_side->moveto($file, "$dir_inside/" . basename($file));
      self::assertTrue($success);

      $success = $left_side->purge($dir);
      self::assertTrue($success);
   }
}
