<?php


namespace CloudAtlas\Flyclone\Exception;

class UnknownErrorException extends RcloneException implements Exception
{
   public function __construct(\Exception $exception, string $message = 'Error not otherwise categorised.', int $code = 2)
   {
      parent::__construct($message, $code, $exception);
   }
}
