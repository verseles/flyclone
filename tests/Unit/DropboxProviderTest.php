<?php declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use Verseles\Flyclone\Providers\DropboxProvider;

class DropboxProviderTest extends AbstractProviderTest
{

   public function setUp()
   : void
   {
      $left_disk_name = 'dropbox_disk';
      $this->setLeftProviderName($left_disk_name);
      $this->working_directory = '/flyclone/' . $this->random_string();


      self::assertEquals($left_disk_name, $this->getLeftProviderName());
   }

   /**
    * @test
    */
   final public function instantiate_left_provider()
   : DropboxProvider
   {
      $left_side = new DropboxProvider($this->getLeftProviderName(), [
          'CLIENT_ID'     => $_ENV[ 'DROPBOX_CLIENT_ID' ],
          'CLIENT_SECRET' => $_ENV[ 'DROPBOX_CLIENT_SECRET' ],
          'TOKEN'         => $_ENV[ 'DROPBOX_TOKEN' ],
      ]);

      self::assertInstanceOf(get_class($left_side), $left_side);

      return $left_side;
   }

}
