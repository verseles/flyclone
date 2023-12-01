<?php


namespace Verseles\Flyclone\Providers;


class MegaProvider extends Provider
{
   protected string $provider = 'mega';

   public function __construct(string $name, array $flags = [])
   {
      parent::__construct($this->provider, $name, $flags);
   }

}
