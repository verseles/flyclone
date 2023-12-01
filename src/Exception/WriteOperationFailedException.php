<?php


namespace Verseles\Flyclone\Exception;


class WriteOperationFailedException extends \RuntimeException
{
   public function __construct(string $path)
   {
      parent::__construct(sprintf('Cannot write to "%s"', $path));
   }
}
