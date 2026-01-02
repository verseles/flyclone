<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

class LocalProvider extends Provider
{
    protected string $provider = 'local';

    public function __construct(string $name, array $flags = [])
    {
        parent::__construct($this->provider, $name, $flags);
    }
}
