<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Rclone;

class FromLocalToS3ProviderTest extends AbstractTwoProvidersTest
{
  public function setUp (): void
  {
    $left_disk_name = 'local_disk';
    $this->setLeftProviderName($left_disk_name);
    $working_directory =sys_get_temp_dir() . '/flyclone_' . $this->random_string();
    mkdir($working_directory, 0777, true);
    $this->setLeftWorkingDirectory($working_directory);
    self::assertEquals($left_disk_name, $this->getLeftProviderName());
    
    
    $right_disk_name = 's3_disk';
    $this->setRightProviderName($right_disk_name);
    $this->setRightWorkingDirectory('flyclone/flyclone');  // bucket/folder
    
    self::assertEquals($right_disk_name, $this->getRightProviderName());
  }
  
  #[Test]
  final public function instantiate_left_provider (): LocalProvider
  {
    $left_side = new LocalProvider($this->getLeftProviderName());
    
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