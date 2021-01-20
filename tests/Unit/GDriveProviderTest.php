<?php declare(strict_types=1);

namespace CloudAtlas\Flyclone\Test\Unit;

use CloudAtlas\Flyclone\Providers\GdriveProvider;

class GDriveProviderTest extends AbstractProviderTest
{

   public function setUp()
   : void
   {
      $left_disk_name = 'gdrive_disk';
      $this->setLeftProviderName($left_disk_name);
      $this->working_directory = '/flyclone';


      self::assertEquals($left_disk_name, $this->getLeftProviderName());
   }

   /**
    * @test
    */
   final public function instantiate_left_provider()
   : GdriveProvider
   {
      $left_side = new GdriveProvider($this->getLeftProviderName(), [
          'CLIENT_ID' => $_ENV[ 'GDRIVE_CLIENT_ID' ],
          'CLIENT_SECRET' => $_ENV[ 'GDRIVE_CLIENT_SECRET' ],
          'TOKEN' => $_ENV[ 'GDRIVE_TOKEN' ],
      ]);

      self::assertInstanceOf(get_class($left_side), $left_side);

      return $left_side;
   }

}
