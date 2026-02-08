<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

class S3Provider extends Provider
{
    protected string $provider = 's3';

    protected bool   $dirAgnostic = true;

    public function __construct(string $name, array $flags = [])
    {
        parent::__construct($this->provider, $name, $flags);
    }
}
