<?php declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\FtpProvider;
use Verseles\Flyclone\Rclone;

class FtpProviderTest extends AbstractProviderTest
{
  
  public function setUp()
  : void
  {
    $left_disk_name = 'ftp_disk';
    $this->setLeftProviderName($left_disk_name);
    $this->working_directory = $_ENV[ 'FTP_USER' ] === 'root' ? '/root' : '/home/' . $_ENV[ 'FTP_USER' ] . '/';
    
    
    self::assertEquals($left_disk_name, $this->getLeftProviderName());
  }
  
  #[Test]
  final public function instantiate_left_provider()
  : FtpProvider
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