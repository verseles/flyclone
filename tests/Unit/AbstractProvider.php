<?php


namespace CloudAtlas\Flyclone\Test\Unit;


use CloudAtlas\Flyclone\Providers\Provider;
use CloudAtlas\Flyclone\Rclone;
use PHPUnit\Framework\TestCase;

abstract class AbstractProvider extends TestCase
{
   private string $leftProviderName = 'undefined_disk';

   /**
    * @param string $leftProviderName
    */
   final public function setLeftProviderName(string $leftProviderName)
   : void
   {
      $this->leftProviderName = $leftProviderName;
   }

   /**
    * @return string
    */
   public function getLeftProviderName()
   : string
   {
      return $this->leftProviderName;
   }

   abstract public function instantiate_left_provider()
   : Provider;

   abstract public function instantiate_with_one_provider(Rclone $left_side)
   : Rclone;
}
