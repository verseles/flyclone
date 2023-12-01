<?php


namespace Verseles\Flyclone\Providers;


use Verseles\Flyclone\Rclone;

class Provider extends AbstractProvider
{
   protected string $provider;
   protected string $name;
   protected array $flags;

   protected function __construct(string $provider, string $name, array $flags = [])
   {
      $this->provider = $provider;

      $name       = strtoupper($name);
      $name       = preg_replace('/[^A-Z0-9]+/', '', $name);
      $this->name = $name;

      $this->flags = $flags;
   }

}
