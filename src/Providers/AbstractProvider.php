<?php


namespace CloudAtlas\Flyclone\Providers;

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
      $flags = $this->flags;

      $prefix = 'RCLONE_CONFIG_' . $this->name() . '_';

      return $this->prefix_envs($flags, $prefix);
   }

   private function prefix_envs(array $arr, string $prefix)
   : array
   {
      $newArr = [];
      foreach ($arr as $key => $value) {
         $newArr[ strtoupper($prefix . $key) ] = $value;
      }

      $newArr[ strtoupper($prefix . 'TYPE') ] = $this->provider();

      return $newArr;
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
