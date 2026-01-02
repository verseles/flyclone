<?php

declare(strict_types=1);

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

        // Determine which timeout was exceeded by inspecting the Symfony exception object and its process.
        if ($exception->isIdleTimeout()) {
            $message = sprintf('The process exceeded the idle timeout of %s seconds.', $exception->getProcess()->getIdleTimeout());
        } elseif ($exception->isGeneralTimeout()) {
            $message = sprintf('The process exceeded the total timeout of %s seconds.', $exception->getProcess()->getTimeout());
        }

        parent::__construct($message, $code, $exception);
    }
}
