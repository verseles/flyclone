<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use Verseles\Flyclone\Providers\Provider;

class CommandBuilder
{
    public static function prefixFlags(array $arr, string $prefix = 'RCLONE_'): array
    {
        $newArr = [];
        $replacePatterns = ['/^--/m' => '', '/-/m' => '_'];

        foreach ($arr as $key => $value) {
            $baseKey = preg_replace(array_keys($replacePatterns), array_values($replacePatterns), (string) $key);
            $baseKey = strtoupper($baseKey);

            if (str_starts_with($baseKey, 'RCLONE_')) {
                $finalEnvVarName = ($prefix === 'RCLONE_')
                    ? $baseKey
                    : $prefix . substr($baseKey, strlen('RCLONE_'));
            } else {
                $finalEnvVarName = $prefix . $baseKey;
            }

            $newArr[$finalEnvVarName] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $newArr;
    }

    public static function buildEnvironment(
        Provider $leftSide,
        Provider $rightSide,
        array $globalFlags,
        array $globalEnvs,
        array $operationFlags = []
    ): array {
        $envVars = [
            'RCLONE_LOCAL_ONE_FILE_SYSTEM' => 'true',
            'RCLONE_CONFIG' => '/dev/null',
        ];

        $envVars = array_merge($envVars, $leftSide->flags(), $rightSide->flags());
        $envVars = array_merge($envVars, self::prefixFlags($globalFlags, 'RCLONE_'));
        $envVars = array_merge($envVars, self::prefixFlags($globalEnvs, 'RCLONE_'));
        $envVars = array_merge($envVars, self::prefixFlags($operationFlags, 'RCLONE_'));

        return $envVars;
    }

    public static function buildCommandArgs(string $binary, string $command, array $args = []): array
    {
        return array_merge([$binary, $command], $args);
    }
}
