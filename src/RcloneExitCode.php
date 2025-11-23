<?php

namespace Verseles\Flyclone;

/**
 * Enum representing rclone exit codes.
 *
 * @see https://rclone.org/docs/#exit-code
 */
enum RcloneExitCode: int
{
  /** Success */
  case SUCCESS = 0;

  /** Syntax or usage error */
  case SYNTAX_ERROR = 1;

  /** Error not otherwise categorised */
  case UNCATEGORIZED_ERROR = 2;

  /** Directory not found */
  case DIRECTORY_NOT_FOUND = 3;

  /** File not found */
  case FILE_NOT_FOUND = 4;

  /** Temporary error (one that more retries might fix) */
  case TEMPORARY_ERROR = 5;

  /** Less serious errors (like transfer limit reached) */
  case LESS_SERIOUS_ERROR = 6;

  /** Fatal error (one that more retries won't fix) */
  case FATAL_ERROR = 7;

  /** Transfer exceeded - Loss of data on one or more transfers */
  case MAX_TRANSFER_REACHED = 8;

  /** No files transferred */
  case NO_FILES_TRANSFERRED = 9;

  /**
   * Gets a human-readable description for this exit code.
   *
   * @return string The description
   */
  public function description(): string
  {
    return match ($this) {
      self::SUCCESS => 'Success',
      self::SYNTAX_ERROR => 'Syntax or usage error',
      self::UNCATEGORIZED_ERROR => 'Error not otherwise categorised',
      self::DIRECTORY_NOT_FOUND => 'Directory not found',
      self::FILE_NOT_FOUND => 'File not found',
      self::TEMPORARY_ERROR => 'Temporary error (one that more retries might fix)',
      self::LESS_SERIOUS_ERROR => 'Less serious errors (like transfer limit reached)',
      self::FATAL_ERROR => 'Fatal error (one that more retries won\'t fix)',
      self::MAX_TRANSFER_REACHED => 'Transfer exceeded - Loss of data on one or more transfers',
      self::NO_FILES_TRANSFERRED => 'No files transferred',
    };
  }

  /**
   * Checks if this exit code represents a retryable error.
   *
   * @return bool True if retrying might help
   */
  public function isRetryable(): bool
  {
    return $this === self::TEMPORARY_ERROR;
  }

  /**
   * Checks if this exit code represents a successful operation.
   *
   * @return bool True if successful
   */
  public function isSuccess(): bool
  {
    return $this === self::SUCCESS;
  }
}
