<?php

namespace Verseles\Flyclone\Providers;

use Verseles\Flyclone\Rclone;

/**
 * Abstract base class for all rclone storage providers.
 *
 * Provides common functionality and default implementations for the ProviderInterface.
 */
abstract class AbstractProvider implements ProviderInterface
{
  /** @var string The rclone provider type (e.g., 's3', 'local', 'sftp') */
  protected string $provider;

  /** @var string The unique name/nickname for this provider instance */
  protected string $name;

  /** @var array Configuration flags for this provider */
  protected array $flags = [];

  /** @var bool True if the provider does not support empty folders */
  protected bool $dirAgnostic = false;

  /** @var bool True if the bucket provider is shown as a directory */
  protected bool $bucketAsDir = false;

  /** @var bool True if the provider lists all files at once */
  protected bool $listsAsTree = false;

  /**
   * Gets the rclone provider type.
   *
   * @return string The provider type (e.g., 's3', 'local', 'sftp')
   */
  public function provider(): string
  {
    return $this->provider;
  }

  /**
   * Gets the configuration flags formatted for rclone environment variables.
   *
   * @return array Associative array of environment variables
   */
  public function flags(): array
  {
    $prefix = 'RCLONE_CONFIG_' . $this->name() . '_';

    return $this->prefix_flags($prefix);
  }

  /**
   * Prefixes the flags with the given prefix for rclone configuration.
   *
   * @param string $prefix The prefix to apply (e.g., 'RCLONE_CONFIG_MYREMOTE_')
   * @return array The prefixed flags array
   */
  protected function prefix_flags(string $prefix): array
  {
    $prefixed = Rclone::prefix_flags($this->flags, $prefix);

    $prefixed[strtoupper($prefix . 'TYPE')] = $this->provider();

    return $prefixed;
  }

  /**
   * Gets the unique name/nickname for this provider instance.
   *
   * @return string The provider name
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Builds the backend path string for rclone commands.
   *
   * @param string|null $path The path to append to the backend (optional)
   * @return string The full backend path (e.g., 'myremote:path/to/file')
   */
  public function backend(?string $path = null): string
  {
    return $this->name() . ':' . $path;
  }

  /**
   * Checks if the provider is directory-agnostic (does not support empty directories).
   *
   * @return bool True if directory-agnostic
   */
  public function isDirAgnostic(): bool
  {
    return $this->dirAgnostic;
  }

  /**
   * Checks if the provider treats buckets as directories.
   *
   * @return bool True if buckets are treated as directories
   */
  public function isBucketAsDir(): bool
  {
    return $this->bucketAsDir;
  }

  /**
   * Checks if the provider lists contents as a flat tree.
   *
   * @return bool True if it lists as a tree
   */
  public function isListsAsTree(): bool
  {
    return $this->listsAsTree;
  }
}