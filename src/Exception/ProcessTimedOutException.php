<?php


namespace Verseles\Flyclone\Exception;


class ProcessTimedOutException extends RcloneException
{
   public function __construct(\Exception $exception, string $message = 'The process took more than defined.', int $code = 22)
   {
      parent::__construct($message, $code, $exception);
   }
}
