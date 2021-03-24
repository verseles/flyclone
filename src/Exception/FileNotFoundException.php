<?php


namespace CloudAtlas\Flyclone\Exception;

class FileNotFoundException extends RcloneException implements Exception
{
   public function __construct(\Exception $exception, string $message = 'File not found.', int $code = 4)
   {
      parent::__construct($message, $code, $exception);
   }
}