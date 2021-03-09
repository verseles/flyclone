<?php


namespace CloudAtlas\Flyclone\Exception;

class NoFilesTransferredException extends RcloneException implements Exception
{
   public function __construct(\Exception $exception, string $message = 'Operation successful, but no files transferred.', int $code = 9)
   {
      parent::__construct($message, $code, $exception);
   }
}
