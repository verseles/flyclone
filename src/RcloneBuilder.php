<?php

namespace Verseles\Flyclone;

use Verseles\Flyclone\Providers\ProviderInterface;

/**
 * Builder for creating configured Rclone instances.
 *
 * Provides a fluent interface for configuring Rclone before instantiation,
 * ensuring each instance has its own isolated configuration.
 *
 * @example
 * ```php
 * $rclone = Rclone::create($s3Provider)
 *     ->withRightSide($localProvider)
 *     ->withTimeout(300)
 *     ->withIdleTimeout(200)
 *     ->withFlags(['verbose' => true])
 *     ->build();
 * ```
 */
final class RcloneBuilder
{
  private ProviderInterface $leftSide;
  private ?ProviderInterface $rightSide = null;
  private int $timeout = 120;
  private int $idleTimeout = 100;
  private array $flags = [];
  private array $envs = [];

  /**
   * Creates a new builder with the required left-side provider.
   *
   * @param ProviderInterface $leftSide The primary (source) provider
   */
  public function __construct(ProviderInterface $leftSide)
  {
    $this->leftSide = $leftSide;
  }

  /**
   * Sets the right-side (destination) provider.
   *
   * @param ProviderInterface $rightSide The destination provider
   * @return self
   */
  public function withRightSide(ProviderInterface $rightSide): self
  {
    $this->rightSide = $rightSide;

    return $this;
  }

  /**
   * Sets the process timeout in seconds.
   *
   * @param int $timeout Timeout in seconds (default: 120)
   * @return self
   */
  public function withTimeout(int $timeout): self
  {
    $this->timeout = $timeout;

    return $this;
  }

  /**
   * Sets the process idle timeout in seconds.
   *
   * @param int $idleTimeout Idle timeout in seconds (default: 100)
   * @return self
   */
  public function withIdleTimeout(int $idleTimeout): self
  {
    $this->idleTimeout = $idleTimeout;

    return $this;
  }

  /**
   * Sets additional rclone flags.
   *
   * @param array $flags Associative array of flags (e.g., ['verbose' => true, 'dry-run' => true])
   * @return self
   */
  public function withFlags(array $flags): self
  {
    $this->flags = array_merge($this->flags, $flags);

    return $this;
  }

  /**
   * Sets custom environment variables.
   *
   * @param array $envs Associative array of environment variables
   * @return self
   */
  public function withEnvs(array $envs): self
  {
    $this->envs = array_merge($this->envs, $envs);

    return $this;
  }

  /**
   * Builds and returns the configured Rclone instance.
   *
   * @return Rclone The configured Rclone instance
   */
  public function build(): Rclone
  {
    return new Rclone(
      leftSide: $this->leftSide,
      rightSide: $this->rightSide,
      timeout: $this->timeout,
      idleTimeout: $this->idleTimeout,
      flags: $this->flags,
      envs: $this->envs
    );
  }

  /**
   * Gets the configured timeout.
   *
   * @return int Timeout in seconds
   */
  public function getTimeout(): int
  {
    return $this->timeout;
  }

  /**
   * Gets the configured idle timeout.
   *
   * @return int Idle timeout in seconds
   */
  public function getIdleTimeout(): int
  {
    return $this->idleTimeout;
  }

  /**
   * Gets the configured flags.
   *
   * @return array The flags array
   */
  public function getFlags(): array
  {
    return $this->flags;
  }

  /**
   * Gets the configured environment variables.
   *
   * @return array The envs array
   */
  public function getEnvs(): array
  {
    return $this->envs;
  }
}
