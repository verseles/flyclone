<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Providers\SFtpProvider;
use Verseles\Flyclone\Rclone;

class FromSFtpToS3ProviderTest extends AbstractTwoProvidersTest
{
  public function setUp (): void
  {
    $left_disk_name = 'sftp_disk';
    $this->setLeftProviderName($left_disk_name);
    $working_directory       = '/upload';
    $this->setLeftWorkingDirectory($working_directory . '/' . $this->random_string());
    self::assertEquals($left_disk_name, $this->getLeftProviderName());
    
    
    $right_disk_name = 's3_disk';
    $this->setRightProviderName($right_disk_name);
    $this->setRightWorkingDirectory('flyclone/flyclone');  // bucket/folder
    
    self::assertEquals($right_disk_name, $this->getRightProviderName());
  }
  
  #[Test]
  final public function instantiate_left_provider (): SFtpProvider
  {
    $left_side = new SFtpProvider($this->getLeftProviderName(), [
      'HOST' => $_ENV[ 'SFTP_HOST' ],
      'USER' => $_ENV[ 'SFTP_USER' ],
      'PASS' => Rclone::obscure($_ENV[ 'SFTP_PASS' ]),
      'PORT' => $_ENV[ 'SFTP_PORT' ],
    ]);
    
    self::assertInstanceOf(get_class($left_side), $left_side);
    
    return $left_side;
  }
  
  #[Test]
  final public function instantiate_right_provider (): S3Provider
  {
    $right_side = new S3Provider($this->getRightProviderName(), [
      'REGION'            => $_ENV[ 'S3_REGION' ],
      'ENDPOINT'          => $_ENV[ 'S3_ENDPOINT' ],
      'ACCESS_KEY_ID'     => $_ENV[ 'S3_ACCESS_KEY_ID' ],
      'SECRET_ACCESS_KEY' => $_ENV[ 'S3_SECRET_ACCESS_KEY' ],
    ]);
    
    self::assertInstanceOf(get_class($right_side), $right_side);
    
    return $right_side;
  }
}