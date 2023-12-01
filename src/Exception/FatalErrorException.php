<?php


namespace Verseles\Flyclone\Exception;

class FatalErrorException extends RcloneException
{
   public function __construct(\Exception $exception, string $message = 'Fatal error (one that more retries won\'t fix, like account suspended).', int $code = 7)
   {
      parent::__construct($message, $code, $exception);
   }
}
