<?php


namespace Verseles\Flyclone\Exception;

class TemporaryErrorException extends RcloneException
{
   public function __construct(\Exception $exception, string $message = 'Temporary error (one that more retries might fix) (Retry errors).', int $code = 5)
   {
      parent::__construct($message, $code, $exception);
   }
}
