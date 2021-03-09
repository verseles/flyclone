<?php


namespace CloudAtlas\Flyclone\Exception;

class SyntaxErrorException extends RcloneException implements Exception
{
   public function __construct(\Exception $exception, string $message = 'Syntax or usage error.', int $code = 1)
   {
      parent::__construct($message, $code, $exception);
   }
}
