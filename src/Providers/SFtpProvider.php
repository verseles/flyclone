<?php


namespace CloudAtlas\Flyclone\Providers;


class SFtpProvider extends Provider
{
   protected string $provider = 'sftp';

   public function __construct(string $name, array $flags = [])
   {
      parent::__construct($this->provider, $name, $flags);
   }

}
