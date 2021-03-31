<?php


namespace CloudAtlas\Flyclone\Exception;

class MaxTransferReachedException extends RcloneException
{
   public function __construct(\Exception $exception, string $message = 'Transfer exceeded - limit set by --max-transfer reached.', int $code = 8)
   {
      parent::__construct($message, $code, $exception);
   }
}
