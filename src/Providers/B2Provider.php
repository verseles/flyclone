<?php


namespace Verseles\Flyclone\Providers;


class B2Provider extends Provider
{
   protected string $provider    = 'b2';
   protected bool   $dirAgnostic = TRUE;


   public function __construct(string $name, array $flags = [])
   {
      parent::__construct($this->provider, $name, $flags);
   }

}
