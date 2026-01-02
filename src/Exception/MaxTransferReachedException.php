<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use Exception;

class MaxTransferReachedException extends RcloneException
{
    public function __construct(Exception $exception, string $message = 'Transfer exceeded - limit set by --max-transfer reached.', int $code = 8)
    {
        parent::__construct($message, $code, $exception);
    }
}
