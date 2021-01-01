<?php


namespace CloudAtlas\Flyclone\Exception;


class WriteOperationFailedException extends \RuntimeException implements Exception
{
   public function __construct(string $path)
   {
      parent::__construct(sprintf('Cannot write to "%s"', $path));
   }
}
