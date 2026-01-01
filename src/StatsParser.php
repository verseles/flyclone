<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

class StatsParser
{
    public static function parse(string $output): object
    {
        $stats = [
            'errors'                 => 0,
            'checks'                 => 0,
            'files'                  => 0,
            'bytes'                  => 0,
            'elapsed_time'           => 0.0,
            'speed_human'            => '0 B/s',
            'speed_bytes_per_second' => 0.0,
        ];

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (preg_match('/Transferred:\s+([\d.]+\s*[KMGTPI]?B)/i', $line, $matches)) {
                $stats['bytes'] = self::convertSizeToBytes(trim($matches[1]));
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            switch ($key) {
                case 'Transferred':
                    if (preg_match('/^\s*([\d.]+\s*[KMGTPI]?B)/i', $value, $byteMatches)) {
                        $stats['bytes'] += self::convertSizeToBytes(trim($byteMatches[1]));
                    } elseif (preg_match('/^\s*(\d+)\s*\/\s*\d+/', $value, $fileMatches)) {
                        $stats['files'] += (int)$fileMatches[1];
                    }
                    break;
                case 'Renamed':
                    $stats['files'] += (int)$value;
                    break;
                case 'Errors':
                    $stats['errors'] = (int)$value;
                    break;
                case 'Checks':
                    if (preg_match('/^\s*(\d+)/', $value, $matches)) {
                        $stats['checks'] = (int)$matches[1];
                    }
                    break;
                case 'Elapsed time':
                    $stats['elapsed_time'] = self::convertDurationToSeconds($value);
                    break;
            }
        }

        if ($stats['elapsed_time'] > 0 && $stats['bytes'] > 0) {
            $stats['speed_bytes_per_second'] = $stats['bytes'] / $stats['elapsed_time'];
            $stats['speed_human'] = self::formatBytes((int) $stats['speed_bytes_per_second']) . '/s';
        }

        return (object) $stats;
    }

    public static function convertSizeToBytes(string $sizeStr): int
    {
        $sizeStr = trim($sizeStr);
        if (empty($sizeStr) || $sizeStr === '-') {
            return 0;
        }

        $units = ['B' => 0, 'K' => 1, 'M' => 2, 'G' => 3, 'T' => 4, 'P' => 5];
        preg_match('/([\d.]+)\s*([KMGTPI]?)B?/i', $sizeStr, $matches);

        if (!isset($matches[1])) {
            return (int) $sizeStr;
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? 'B');

        if (isset($units[$unit])) {
            return (int) ($value * (1024 ** $units[$unit]));
        }

        return (int) $value;
    }

    public static function convertDurationToSeconds(string $durationStr): float
    {
        $totalSeconds = 0.0;

        if (preg_match('/(\d+(\.\d+)?)d/', $durationStr, $matches)) {
            $totalSeconds += (float) $matches[1] * 86400;
        }
        if (preg_match('/(\d+(\.\d+)?)h/', $durationStr, $matches)) {
            $totalSeconds += (float) $matches[1] * 3600;
        }
        if (preg_match('/(\d+(\.\d+)?)m/', $durationStr, $matches)) {
            $totalSeconds += (float) $matches[1] * 60;
        }
        if (preg_match('/(\d+(\.\d+)?)s/', $durationStr, $matches)) {
            $totalSeconds += (float) $matches[1];
        }
        if (preg_match('/(\d+(\.\d+)?)ms/', $durationStr, $matches)) {
            $totalSeconds += (float) $matches[1] / 1000;
        }

        return $totalSeconds > 0 ? $totalSeconds : (float) $durationStr;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / (1024 ** (int)$i), 2) . ' ' . $units[(int) $i];
    }
}
