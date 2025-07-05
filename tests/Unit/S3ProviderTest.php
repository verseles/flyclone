<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\S3Provider;

class S3ProviderTest extends AbstractProviderTest
{
  
  public function setUp(): void
  {
    $left_disk_name = 's3_disk';
    $this->setLeftProviderName($left_disk_name);
    $this->working_directory = 'flyclone/flyclone'; // bucket/folder
    
    
    self::assertEquals($left_disk_name, $this->getLeftProviderName());
  }
  
  #[Test]
  final public function instantiate_left_provider(): S3Provider
  {
    $left_side = new S3Provider($this->getLeftProviderName(), [
      'REGION'            => $_ENV['S3_REGION'],
      'ENDPOINT'          => $_ENV['S3_ENDPOINT'],
      'ACCESS_KEY_ID'     => $_ENV['S3_ACCESS_KEY_ID'],
      'SECRET_ACCESS_KEY' => $_ENV['S3_SECRET_ACCESS_KEY'],
    ]);
    
    self::assertInstanceOf(get_class($left_side), $left_side);
    
    return $left_side;
  }
}