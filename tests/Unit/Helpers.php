<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

trait Helpers
{
    public function random_string($length = 7): string
    {
        return substr(md5((string) mt_rand()), 0, $length);
    }
}
