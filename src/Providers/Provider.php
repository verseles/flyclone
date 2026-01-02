<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

/**
 * Base provider class that other providers extend.
 *
 * Includes validation and credential checking on construction.
 */
class Provider extends AbstractProvider
{
    protected string $provider;
    protected string $name;
    protected array $flags;

    protected function __construct(string $provider, string $name, array $flags = [])
    {
        $this->provider = $provider;

        $name = strtoupper($name);
        $name = preg_replace('/[^A-Z0-9]+/', '', $name);
        $this->name = $name;

        // Validate required fields if defined in subclass
        $this->validateConfig($flags);

        // Check for plaintext credentials
        $this->checkCredentials($flags, $name);

        $this->flags = $flags;
    }
}
