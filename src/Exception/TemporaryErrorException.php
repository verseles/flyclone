<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

/**
 * Exception for temporary/transient errors that may succeed on retry.
 *
 * Exit code 5 - one that more retries might fix.
 */
class TemporaryErrorException extends RcloneException
{
    public function __construct(\Exception $exception, string $message = 'Temporary error (one that more retries might fix) (Retry errors).', int $code = 5)
    {
        parent::__construct($message, $code, $exception);
    }

    /**
     * @inheritDoc
     */
    public function isRetryable(): bool
    {
        return true;
    }
}
