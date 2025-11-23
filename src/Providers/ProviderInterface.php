<?php

namespace Verseles\Flyclone\Providers;

/**
 * Interface for rclone storage providers.
 *
 * Defines the contract that all storage providers must implement.
 * This enables type-hinting, mocking in tests, and documentation of provider capabilities.
 */
interface ProviderInterface
{
  /**
   * Gets the rclone provider type identifier.
   *
   * @return string The provider type (e.g., 's3', 'local', 'sftp', 'drive')
   */
  public function provider(): string;

  /**
   * Gets the unique name/nickname for this provider instance.
   *
   * @return string The provider name (uppercase, alphanumeric only)
   */
  public function name(): string;

  /**
   * Gets the configuration flags formatted for rclone environment variables.
   *
   * @return array<string, string> Associative array of environment variables
   */
  public function flags(): array;

  /**
   * Builds the backend path string for rclone commands.
   *
   * @param string|null $path The path to append to the backend (optional)
   * @return string The full backend path (e.g., 'myremote:path/to/file')
   */
  public function backend(?string $path = null): string;

  /**
   * Checks if the provider is directory-agnostic.
   *
   * Directory-agnostic providers (like S3) don't support empty directories.
   * Files define the directory structure implicitly.
   *
   * @return bool True if the provider doesn't support empty directories
   */
  public function isDirAgnostic(): bool;

  /**
   * Checks if the provider treats buckets as directories.
   *
   * Some providers (like S3) have a bucket concept that acts as a top-level directory.
   *
   * @return bool True if buckets are treated as directories
   */
  public function isBucketAsDir(): bool;

  /**
   * Checks if the provider lists all contents as a flat tree.
   *
   * Some providers return all files at once rather than directory-by-directory.
   *
   * @return bool True if the provider lists contents as a flat tree
   */
  public function isListsAsTree(): bool;
}
