<?php


namespace CloudAtlas\Flyclone\Exception;

class LessSeriousErrorException extends RcloneException implements Exception
{
   public function __construct(\Exception $exception, string $message = 'Less serious errors (like 461 errors from dropbox) (NoRetry errors).', int $code = 6)
   {
      parent::__construct($message, $code, $exception);
   }
}
