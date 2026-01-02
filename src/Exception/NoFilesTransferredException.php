<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use Exception;

class NoFilesTransferredException extends RcloneException
{
    public function __construct(Exception $exception, string $message = 'Operation successful, but no files transferred.', int $code = 9)
    {
        parent::__construct($message, $code, $exception);
    }
}
