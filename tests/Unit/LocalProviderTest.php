<?php declare(strict_types=1);

namespace CloudAtlas\Flyclone\Test\Unit;

use CloudAtlas\Flyclone\Providers\LocalProvider;

class LocalProviderTest extends AbstractProviderTest
{
   public function setUp()
   : void
   {
      $left_disk_name = 'local_disk';
      $this->setLeftProviderName($left_disk_name);
      $working_directory =sys_get_temp_dir() . '/flyclone_' . $this->random_string();
      mkdir($working_directory, 0777, true);
      $this->working_directory = $working_directory;

      self::assertEquals($left_disk_name, $this->getLeftProviderName());
   }

   /**  @test
    */
   public function instantiate_left_provider()
   : LocalProvider
   {
      $left_side = new LocalProvider($this->getLeftProviderName()); // name

      self::assertInstanceOf(get_class($left_side), $left_side);

      return $left_side;
   }



   /**
    * @test
    * @depends touch_a_file
    *
    */
   final public function write_to_a_temp_file($params)
   : array
   {
      [ $left_side, $temp_filepath ] = $params;
      $content = 'I live at https://github.com/cloudatlasid/flyclone';
      self::assertFileIsWritable($temp_filepath, "File not writable: $temp_filepath");
      $result = file_put_contents($temp_filepath, $content);

      self::assertIsInt($result);
      self::assertNotFalse($result);
      self::assertEquals(file_get_contents($temp_filepath), $content);

      return [ $left_side, $temp_filepath ];
   }


}
