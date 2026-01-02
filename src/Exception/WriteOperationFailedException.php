<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use RuntimeException;

class WriteOperationFailedException extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Cannot write to "%s"', $path));
    }
}
