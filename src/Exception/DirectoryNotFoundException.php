<?php

namespace CloudAtlas\Flyclone\Exception;

class DirectoryNotFoundException extends RcloneException
{
   public function __construct(\Exception $exception, string $message = 'Directory not found.', int $code = 3)
   {
      parent::__construct($message, $code, $exception);
   }
}
