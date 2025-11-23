<?php

namespace Verseles\Flyclone\Providers;

use LogicException;

/**
 * Provider for rclone's union remote, which joins multiple remotes together.
 *
 * The union remote combines multiple remotes to act as a single remote.
 * It can be used for read-only access to multiple remotes or for
 * writing to specific upstreams based on policies.
 *
 * @see https://rclone.org/union/
 */
class UnionProvider extends Provider
{
  protected string $provider = 'union';

  /** @var ProviderInterface[] The upstream providers to combine */
  private array $upstreamProviders = [];

  /**
   * Creates a new UnionProvider.
   *
   * @param string $name  Unique name for this union remote
   * @param array  $flags Configuration flags. May include:
   *                      - 'upstream_providers': Array of ProviderInterface instances
   *                      - 'upstreams': Colon-separated list of remote paths (auto-generated if providers given)
   *                      - 'action_policy': Policy for action commands (default: 'epall')
   *                      - 'create_policy': Policy for create commands (default: 'epmfs')
   *                      - 'search_policy': Policy for search commands (default: 'ff')
   */
  public function __construct(string $name, array $flags = [])
  {
    if (isset($flags['upstream_providers'])) {
      if (!is_array($flags['upstream_providers'])) {
        throw new LogicException('UnionProvider upstream_providers must be an array of ProviderInterface instances.');
      }

      foreach ($flags['upstream_providers'] as $provider) {
        if (!$provider instanceof ProviderInterface) {
          throw new LogicException('All upstream_providers must implement ProviderInterface.');
        }
      }

      $this->upstreamProviders = $flags['upstream_providers'];

      // Build the upstreams string if not provided
      if (!isset($flags['upstreams'])) {
        $upstreams = array_map(
          fn(ProviderInterface $p) => $p->backend(),
          $this->upstreamProviders
        );
        $flags['upstreams'] = implode(' ', $upstreams);
      }

      unset($flags['upstream_providers']);
    }

    parent::__construct($this->provider, $name, $flags);
  }

  /**
   * Gets the configuration flags for the union remote and all upstream providers.
   *
   * @return array Merged environment variables for rclone
   */
  public function flags(): array
  {
    $allFlags = parent::flags();

    // Add flags from all upstream providers
    foreach ($this->upstreamProviders as $provider) {
      $allFlags = array_merge($allFlags, $provider->flags());
    }

    return $allFlags;
  }

  /**
   * Gets the upstream providers.
   *
   * @return ProviderInterface[] The upstream providers
   */
  public function getUpstreamProviders(): array
  {
    return $this->upstreamProviders;
  }
}