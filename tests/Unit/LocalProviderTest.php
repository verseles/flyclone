<?php
declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\LocalProvider;

class LocalProviderTest extends AbstractProviderTest
{
  public function setUp() : void
  {
    $left_disk_name = 'local_disk';
    $this->setLeftProviderName($left_disk_name);
    $working_directory = sys_get_temp_dir() . '/flyclone_' . $this->random_string();
    mkdir($working_directory, 0777, TRUE);
    $this->working_directory = $working_directory;
    
    self::assertEquals($left_disk_name, $this->getLeftProviderName());
  }
  
  #[Test]
  public function instantiate_left_provider() : LocalProvider
  {
    $left_side = new LocalProvider($this->getLeftProviderName()); // name
    
    self::assertInstanceOf(get_class($left_side), $left_side);
    
    return $left_side;
  }
  
  
  #[Test]
  #[Depends('touch_a_file')]
  final public function write_to_a_file($params) : array
  {
    [$left_side, $temp_filepath] = $params;
    $content = 'I live at https://github.com/verseles/flyclone';
    self::assertFileIsWritable($temp_filepath, "File not writable: $temp_filepath");
    $result = file_put_contents($temp_filepath, $content);
    
    self::assertIsInt($result);
    self::assertNotFalse($result);
    self::assertStringEqualsFile($temp_filepath, $content);
    
    return [$left_side, $temp_filepath, $content];
  }

  #[Test]
  #[Depends('write_to_a_file')]
  public function is_file_with_flags(array $params) : void
  {
    [$left_side, $temp_filepath] = $params;

    // Test 1: Use 'min-size' flag to filter OUT the file (expecting it NOT to exist in the listing)
    // 1PB is definitely larger than the test file.
    $resultFalse = $left_side->is_file($temp_filepath, ['min-size' => '1P']);
    self::assertFalse($resultFalse->exists, "File should NOT be found when min-size is huge (flag respected).");

    // Test 2: Use 'max-size' flag to keep the file (expecting it to exist)
    // 1PB is definitely larger than the test file.
    $resultTrue = $left_side->is_file($temp_filepath, ['max-size' => '1P']);
    self::assertTrue($resultTrue->exists, "File SHOULD be found when max-size is huge.");
  }
  
}