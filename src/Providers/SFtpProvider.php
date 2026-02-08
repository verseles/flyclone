<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

class SFtpProvider extends Provider
{
    protected string $provider = 'sftp';

    public function __construct(string $name, array $flags = [])
    {
        parent::__construct($this->provider, $name, $flags);
    }
}
