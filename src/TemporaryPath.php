<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use RuntimeException;

class TemporaryPath
{
    public static function directory(string $prefix): string
    {
        $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $safePrefix = self::safePrefix($prefix);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $path = $baseDir . DIRECTORY_SEPARATOR . $safePrefix . bin2hex(random_bytes(8));

            if (@mkdir($path, 0700)) {
                return $path;
            }

            if (! is_dir($path)) {
                throw new RuntimeException("Failed to create temporary directory: {$path}");
            }
        }

        throw new RuntimeException('Failed to create a unique temporary directory.');
    }

    public static function remoteName(string $prefix): string
    {
        return strtoupper(self::safePrefix($prefix) . bin2hex(random_bytes(8)));
    }

    private static function safePrefix(string $prefix): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9]+/', '_', $prefix) ?? '';
        $prefix = trim($prefix, '_');

        return $prefix === '' ? 'flyclone_' : $prefix . '_';
    }
}
