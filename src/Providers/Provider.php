<?php


namespace CloudAtlas\Flyclone\Providers;


class Provider extends AbstractProvider
{
   protected string $provider;
   protected string $name;
   protected array $flags;


   protected function __construct(string $provider, string $name = NULL, array $flags = [])
   {
      $this->provider = $provider;
      $this->name     = strtoupper($name ?? rand ;
      $this->flags    = $flags;
   }
}
