<?php declare(strict_types=1);

namespace CloudAtlas\Flyclone\Test\Unit;

use CloudAtlas\Flyclone\Providers\SFtpProvider;
use CloudAtlas\Flyclone\Rclone;

class SFtpProviderTest extends AbstractProviderTest
{
   public function setUp()
   : void
   {
      $left_disk_name = 'sftp_disk';
      $this->setLeftProviderName($left_disk_name);
      // $this->working_directory = "/home/{$_ENV['FTP_USER']}";
      $this->working_directory = "/root";

      self::assertEquals($left_disk_name, $this->getLeftProviderName());
   }

   /**  @test
    */
   final public function instantiate_left_provider()
   : SFtpProvider
   {
      $left_side = new SFtpProvider($this->getLeftProviderName(), [
         'HOST' => $_ENV['SFTP_HOST'],
         'USER' => $_ENV['SFTP_USER'],
         'PASS' => Rclone::obscure($_ENV['SFTP_PASS']),
      ]);

      self::assertInstanceOf(get_class($left_side), $left_side);

      return $left_side;
   }

}
