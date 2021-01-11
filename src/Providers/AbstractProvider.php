<?php


namespace CloudAtlas\Flyclone\Providers;

use CloudAtlas\Flyclone\Rclone;

abstract class AbstractProvider
{
   public function provider()
   : string
   {
      return $this->provider;
   }

   public function flags()
   : array
   {
      $prefix = 'RCLONE_CONFIG_' . $this->name() . '_';

      return $this->prefix_flags($prefix);
   }

   private function prefix_flags(string $prefix)
   : array
   {
      $prefixed = Rclone::prefix_flags($this->flags, $prefix);

      $prefixed[ strtoupper($prefix . 'TYPE') ] = $this->provider();

      return $prefixed;
   }

   public function name()
   {
      return $this->name;
   }

   public function backend($path = NULL)
   {
      return $this->name() . ':' . $path;
   }

}
