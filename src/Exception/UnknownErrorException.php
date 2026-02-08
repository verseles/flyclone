<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use Exception;

class UnknownErrorException extends RcloneException
{
    public function __construct(Exception $exception, string $message = 'Error not otherwise categorised.', int $code = 2)
    {
        parent::__construct($message, $code, $exception);
    }
}
