<?php

namespace Verseles\Flyclone\Exception;

/**
 * Base exception for all rclone-related errors.
 *
 * Provides additional context for debugging including the command output,
 * exit code, and access to the original exception.
 */
class RcloneException extends \RuntimeException
{
  /**
   * Creates a new RcloneException with optional context.
   *
   * @param string          $message  The exception message
   * @param int             $code     The exception code (usually rclone exit code)
   * @param \Throwable|null $previous The previous throwable used for exception chaining
   */
  public function __construct(
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Gets the rclone exit code if available.
   *
   * @return int The exit code
   */
  public function getExitCode(): int
  {
    return $this->getCode();
  }

  /**
   * Checks if this error is potentially retryable.
   *
   * @return bool True if the error might be resolved by retrying
   */
  public function isRetryable(): bool
  {
    return false;
  }

  /**
   * Gets a user-friendly summary of the error.
   *
   * @return string Human-readable error summary
   */
  public function getSummary(): string
  {
    return sprintf(
      '[Rclone Error %d] %s',
      $this->getCode(),
      $this->getMessage()
    );
  }
}
