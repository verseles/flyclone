<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use Exception;

class FatalErrorException extends RcloneException
{
    public function __construct(Exception $exception, string $message = 'Fatal error (one that more retries won\'t fix, like account suspended).', int $code = 7)
    {
        parent::__construct($message, $code, $exception);
    }
}
