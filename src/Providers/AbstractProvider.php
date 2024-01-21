<?php


namespace Verseles\Flyclone\Providers;

use Verseles\Flyclone\Rclone;

abstract class AbstractProvider
{
  /** @var bool TRUE if the provider does not support empty folders  */
  protected bool $dirAgnostic = FALSE;

  /** @var bool TRUE if the bucket provider is shown as a directory */
  protected bool $bucketAsDir = FALSE;

  /** @var bool TRUE if the provider lists all files at once */
  protected bool $listsAsTree = FALSE;

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

   public function isDirAgnostic() : bool
   {
      return $this->dirAgnostic;
   }

   public function isBucketAsDir() : bool {
      return $this->bucketAsDir;
   }

   public function isListsAsTree() : bool {
      return $this->listsAsTree;
   }

}
