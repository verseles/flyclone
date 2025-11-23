<?php

namespace Verseles\Flyclone\Providers;

use LogicException;

/**
 * Provider for rclone's crypt remote, which provides encryption on top of another remote.
 *
 * @see https://rclone.org/crypt/
 */
class CryptProvider extends Provider
{
  protected string $provider = 'crypt';

  /** @var ProviderInterface The wrapped provider that will be encrypted */
  private ProviderInterface $wrappedProvider;

  /**
   * Creates a new CryptProvider.
   *
   * @param string $name  Unique name for this crypt remote
   * @param array  $flags Configuration flags. Must include:
   *                      - 'remote': A ProviderInterface instance to wrap
   *                      - 'password': The encryption password (will be obscured)
   *                      - 'password2': Optional salt password
   */
  public function __construct(string $name, array $flags = [])
  {
    if (!isset($flags['remote']) || !$flags['remote'] instanceof ProviderInterface) {
      throw new LogicException('A CryptProvider must be instantiated with a "remote" flag pointing to a ProviderInterface instance.');
    }

    // Store the wrapped provider
    $this->wrappedProvider = $flags['remote'];

    // Replace the Provider object with its backend string for rclone config
    $flags['remote'] = $this->wrappedProvider->backend();

    // Remove wrapped_provider if accidentally passed
    unset($flags['wrapped_provider']);

    parent::__construct($this->provider, $name, $flags);
  }

  /**
   * Gets the configuration flags for both the crypt remote and its underlying provider.
   *
   * @return array Merged environment variables for rclone
   */
  public function flags(): array
  {
    // Get the flags from the parent (crypt config)
    $cryptFlags = parent::flags();

    // Get the flags from the wrapped provider
    $wrappedFlags = $this->wrappedProvider->flags();

    // Merge them together - both configs are needed
    return array_merge($cryptFlags, $wrappedFlags);
  }

  /**
   * Gets the wrapped provider instance.
   *
   * @return ProviderInterface The underlying provider
   */
  public function getWrappedProvider(): ProviderInterface
  {
    return $this->wrappedProvider;
  }
}