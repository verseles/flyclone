<?php


namespace Verseles\Flyclone\Providers;

use Verseles\Flyclone\Rclone;

abstract class AbstractProvider
{
   protected bool $folderAgnostic = FALSE;

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

   public function isFolderAgnostic()
   : bool
   {
      return $this->folderAgnostic;
   }

}
