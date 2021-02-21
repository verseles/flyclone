<?php


namespace CloudAtlas\Flyclone\Providers;


class GDriveProvider extends Provider
{
   protected string $provider = 'drive';

   public function __construct(string $name, array $flags = [])
   {
      parent::__construct($this->provider, $name, $flags);
   }

}
