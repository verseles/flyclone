<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use Exception;

class SyntaxErrorException extends RcloneException
{
    public function __construct(Exception $exception, string $message = 'Syntax or usage error.', int $code = 1)
    {
        parent::__construct($message, $code, $exception);
    }
}
