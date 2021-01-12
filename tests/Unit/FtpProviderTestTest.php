<?php declare(strict_types=1);

namespace CloudAtlas\Flyclone\Test\Unit;

use CloudAtlas\Flyclone\Providers\FtpProvider;
use CloudAtlas\Flyclone\Providers\Provider;
use CloudAtlas\Flyclone\Rclone;

class FtpProviderTestTest extends AbstractProviderTest
{

   public function setUp()
   : void
   {
      $left_disk_name = 'ftp_disk';
      $this->setLeftProviderName($left_disk_name);
      $this->working_directory = '/';


      self::assertEquals($left_disk_name, $this->getLeftProviderName());
   }

   /**  @test
    */
   final public function instantiate_left_provider()
   : Provider
   {
      $left_side = new FtpProvider($this->getLeftProviderName(), [
          'HOST' => $_ENV[ 'FTP_HOST' ],
          'USER' => $_ENV[ 'FTP_USER' ],
          'PASS' => Rclone::obscure($_ENV[ 'FTP_PASS' ]),
      ]);

      self::assertInstanceOf(get_class($left_side), $left_side);

      return $left_side;
   }
}
