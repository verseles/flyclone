<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

use LogicException;

class CryptProvider extends Provider
{
    protected string $provider = 'crypt';

    protected Provider $wrappedProvider;

    public function __construct(string $name, array $flags = [])
    {
        if (! isset($flags['remote']) || ! $flags['remote'] instanceof Provider) {
            throw new LogicException('A CryptProvider must be instantiated with a "remote" flag pointing to another Provider instance.');
        }

        $this->wrappedProvider = $flags['remote'];
        $remotePath = $flags['remote_path'] ?? '';
        unset($flags['remote_path']);
        $flags['remote'] = $this->wrappedProvider->backend($remotePath);

        parent::__construct($this->provider, $name, $flags);
    }

    public function flags(): array
    {
        $cryptFlags = parent::flags();
        $wrappedFlags = $this->wrappedProvider->flags();

        return array_merge($cryptFlags, $wrappedFlags);
    }

    public function getWrappedProvider(): Provider
    {
        return $this->wrappedProvider;
    }
}
