<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

class B2Provider extends Provider
{
    protected string $provider = 'b2';

    protected bool   $dirAgnostic = true;

    public function __construct(string $name, array $flags = [])
    {
        parent::__construct($this->provider, $name, $flags);
    }
}
