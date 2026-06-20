<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

use InvalidArgumentException;

/**
 * Base provider class that other providers extend.
 *
 * Includes validation and credential checking on construction.
 */
class Provider extends AbstractProvider
{
    protected function __construct(string $provider, string $name, array $flags = [])
    {
        $this->provider = $provider;

        $name = self::normalizeName($name);

        if ($name === '') {
            throw new InvalidArgumentException('Provider name must contain at least one ASCII letter or digit.');
        }

        $this->name = $name;

        // Validate required fields if defined in subclass
        $this->validateConfig($flags);

        // Check for plaintext credentials
        $this->checkCredentials($flags, $name);

        $this->flags = $flags;
    }
}
