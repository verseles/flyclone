<?php


namespace CloudAtlas\Flyclone\Test\traits;


trait hasFiles
{
   /**
    * @test
    * @depends instantiate_with_one_provider
    */
   public function list_files_from_root_dir($left_side)
   : void
   {
      $dir    = '/';
      $result = $left_side->ls($dir);

      self::assertIsArray($result);
      self::assertTrue(count($result) > 0, "I need at least one result from $dir");
      self::assertObjectHasAttribute('Name', $result[ 0 ], 'Unexpected result from ls');
   }
}
