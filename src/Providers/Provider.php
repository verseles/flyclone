<?php

namespace Verseles\Flyclone\Providers;

class Provider extends AbstractProvider
{
  /**
   * Constructor for Provider.
   *
   * @param string $provider The rclone provider type (e.g., 's3', 'local', 'sftp')
   * @param string $name     A unique nickname for this provider instance
   * @param array  $flags    Configuration flags for this provider
   */
  protected function __construct(string $provider, string $name, array $flags = [])
  {
    $this->provider = $provider;

    // Normalize name: uppercase and alphanumeric only
    $name = strtoupper($name);
    $name = preg_replace('/[^A-Z0-9]+/', '', $name);
    $this->name = $name;

    $this->flags = $flags;
  }
}
