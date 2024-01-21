<?php


namespace Verseles\Flyclone\Providers;


class S3Provider extends Provider
{
   protected string $provider    = 's3';
   protected bool   $dirAgnostic = TRUE;


   public function __construct(string $name, array $flags = [])
   {
      parent::__construct($this->provider, $name, $flags);
   }

}
