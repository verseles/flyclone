<?php


namespace CloudAtlas\Flyclone\Providers;


class DropboxProvider extends Provider
{
   protected string $provider = 'dropbox';

   public function __construct(string $name, array $flags = [])
   {
      parent::__construct($this->provider, $name, $flags);
   }

}
