<?php

namespace Verseles\Flyclone\Providers;

use LogicException;
use Verseles\Flyclone\Rclone;

class CryptProvider extends Provider
{
  protected string $provider = 'crypt';
  
  public function __construct(string $name, array $flags = [])
  {
    if (!isset($flags['remote']) || !$flags['remote'] instanceof Provider) {
      throw new LogicException('A CryptProvider must be instantiated with a "remote" flag pointing to another Provider instance.');
    }
    
    // We replace the Provider object with its backend string for parent constructor.
    $wrappedProvider = $flags['remote'];
    $flags['remote'] = $wrappedProvider->backend();
    
    parent::__construct($this->provider, $name, $flags);
    
    // Store the wrapped provider object for later use in flags()
    $this->flags['wrapped_provider'] = $wrappedProvider;
  }
  
  public function flags(): array
  {
    // Get the flags from the parent (which includes type=crypt, remote=backend_string, etc.)
    $cryptFlags = parent::flags();
    
    $wrappedProvider = $this->flags['wrapped_provider'] ?? null;
    
    if ($wrappedProvider instanceof Provider) {
      // Get the flags from the wrapped provider (e.g., type=local)
      $wrappedFlags = $wrappedProvider->flags();
      // Merge them together. Now the environment will have configs for both remotes.
      return array_merge($cryptFlags, $wrappedFlags);
    }
    
    // This should not happen due to the constructor check.
    return $cryptFlags;
  }
}