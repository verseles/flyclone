<?php

namespace Verseles\Flyclone\Providers;

use LogicException;

class UnionProvider extends Provider
{
  protected string $provider = 'union';
  
  /** @var Provider[] */
  protected array $upstreamProviders = [];
  
  public function __construct(string $name, array $flags = [])
  {
    if (isset($flags['upstream_providers'])) {
      if (!is_array($flags['upstream_providers'])) {
        // @codeCoverageIgnoreStart
        throw new LogicException('UnionProvider upstream_providers must be an array of Provider instances.');
        // @codeCoverageIgnoreEnd
      }
      $this->upstreamProviders = $flags['upstream_providers'];
      unset($flags['upstream_providers']);
    }
    
    parent::__construct($this->provider, $name, $flags);
  }
  
  public function flags() : array
  {
    $allFlags = parent::flags();
    foreach ($this->upstreamProviders as $provider) {
      $allFlags = array_merge($allFlags, $provider->flags());
    }
    
    return $allFlags;
  }
}