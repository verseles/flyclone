<?php

namespace Verseles\Flyclone\Exception;

/**
 * Exception for temporary errors that might be resolved by retrying.
 *
 * This typically includes network issues, rate limiting, or transient failures.
 */
class TemporaryErrorException extends RcloneException
{
  public function __construct(
    \Exception $exception,
    string $message = 'Temporary error (one that more retries might fix) (Retry errors).',
    int $code = 5
  ) {
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
