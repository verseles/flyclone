<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

// This allows us to intercept sys_get_temp_dir() if we include this file during the test
if (!function_exists('Verseles\Flyclone\sys_get_temp_dir')) {
    function sys_get_temp_dir(): string
    {
        return \Verseles\Flyclone\Test\Unit\sys_get_temp_dir_mock() ?? \sys_get_temp_dir();
    }
}
