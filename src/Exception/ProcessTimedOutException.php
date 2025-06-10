<?php

namespace Verseles\Flyclone\Exception;

use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;

class ProcessTimedOutException extends RcloneException
{
  /**
   * @param SymfonyProcessTimedOutException $exception The original exception from Symfony Process.
   * @param int                             $code      The exception code.
   */
  public function __construct(SymfonyProcessTimedOutException $exception, int $code = 22)
  {
    $message = 'The process timed out.'; // Default message
    
    // Determine which timeout was exceeded by inspecting the Symfony exception object.
    if ($exception->isIdleTimeout()) {
      $message = sprintf('The process exceeded the idle timeout of %s seconds.', $exception->getIdleTimeout());
    } elseif ($exception->isGeneralTimeout()) {
      $message = sprintf('The process exceeded the total timeout of %s seconds.', $exception->getTimeout());
    }
    
    parent::__construct($message, $code, $exception);
  }
}