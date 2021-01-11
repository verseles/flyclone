<?php


namespace CloudAtlas\Flyclone\Test\traits;

use CloudAtlas\Flyclone\Rclone;

trait hasLeftProvider
{

   /**
    * @test
    * @depends instantiate_left_provider
    */
   final public function instantiate_with_one_provider($left_side)
   : Rclone
   {
      $left_side = new Rclone($left_side);

      self::assertInstanceOf(Rclone::class, $left_side);

      return $left_side;
   }
}
