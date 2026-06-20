<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use LogicException;
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

            $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            if (array_key_exists($finalEnvVarName, $newArr) && $newArr[$finalEnvVarName] !== $value) {
                throw new LogicException("Duplicate rclone environment variable after normalization: {$finalEnvVarName}");
            }

            $newArr[$finalEnvVarName] = $value;
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

        $envVars = array_merge($envVars, self::mergeProviderFlags($leftSide->flags(), $rightSide->flags()));
        $envVars = array_merge($envVars, self::prefixFlags($globalFlags, 'RCLONE_'));
        $envVars = array_merge($envVars, self::prefixFlags($globalEnvs, 'RCLONE_'));
        $envVars = array_merge($envVars, self::prefixFlags($operationFlags, 'RCLONE_'));

        return $envVars;
    }

    public static function mergeProviderFlags(array ...$flagSets): array
    {
        $merged = [];
        $remoteConfigs = [];

        foreach ($flagSets as $flags) {
            foreach (self::extractRemoteConfigs($flags) as $remoteName => $config) {
                if (array_key_exists($remoteName, $remoteConfigs) && $remoteConfigs[$remoteName] !== $config) {
                    throw new LogicException("Conflicting rclone provider remote name: {$remoteName}");
                }

                $remoteConfigs[$remoteName] = $config;
            }

            foreach ($flags as $key => $value) {
                if (array_key_exists($key, $merged) && $merged[$key] !== $value) {
                    throw new LogicException("Conflicting rclone provider environment variable: {$key}");
                }

                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private static function extractRemoteConfigs(array $flags): array
    {
        $configs = [];

        foreach ($flags as $key => $value) {
            if (preg_match('/^RCLONE_CONFIG_([^_]+)_(.+)$/', (string) $key, $matches) !== 1) {
                continue;
            }

            $configs[$matches[1]][$matches[2]] = $value;
        }

        foreach ($configs as &$config) {
            ksort($config);
        }

        return $configs;
    }

    public static function buildCommandArgs(string $binary, string $command, array $args = []): array
    {
        return array_merge([$binary, $command], $args);
    }
}
