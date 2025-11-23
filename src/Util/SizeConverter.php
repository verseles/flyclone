<?php

namespace Verseles\Flyclone\Util;

/**
 * Utility class for converting between human-readable sizes and bytes.
 */
final class SizeConverter
{
  /** Regex for parsing size values with units */
  private const SIZE_REGEX = '/([\d.]+)\s*([KMGTPI]?)B?/i';

  /** Size unit multipliers (binary - 1024 based) */
  private const UNITS = [
    'B' => 0,
    'K' => 1,
    'M' => 2,
    'G' => 3,
    'T' => 4,
    'P' => 5,
  ];

  /** Human-readable unit names */
  private const UNIT_NAMES = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

  /**
   * Converts a human-readable size string to bytes.
   *
   * @param string $sizeStr The size string (e.g., "1.5GiB", "500 MB", "1024")
   * @return int The size in bytes
   */
  public static function toBytes(string $sizeStr): int
  {
    $sizeStr = trim($sizeStr);
    if (empty($sizeStr) || $sizeStr === '-') {
      return 0;
    }

    preg_match(self::SIZE_REGEX, $sizeStr, $matches);

    if (!isset($matches[1])) {
      return (int) $sizeStr;
    }

    $value = (float) $matches[1];
    $unit = strtoupper($matches[2] ?? 'B');

    if (isset(self::UNITS[$unit])) {
      return (int) ($value * (1024 ** self::UNITS[$unit]));
    }

    return (int) $value;
  }

  /**
   * Converts bytes to a human-readable string.
   *
   * @param int $bytes The number of bytes
   * @param int $precision Number of decimal places (default: 2)
   * @return string The formatted string (e.g., "1.5 GiB")
   */
  public static function toHuman(int $bytes, int $precision = 2): string
  {
    if ($bytes === 0) {
      return '0 B';
    }

    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count(self::UNIT_NAMES) - 1);

    return round($bytes / (1024 ** $i), $precision) . ' ' . self::UNIT_NAMES[$i];
  }

  /**
   * Formats bytes per second as a human-readable speed string.
   *
   * @param float $bytesPerSecond Speed in bytes per second
   * @param int $precision Number of decimal places (default: 2)
   * @return string The formatted speed string (e.g., "12.5 MiB/s")
   */
  public static function toSpeed(float $bytesPerSecond, int $precision = 2): string
  {
    return self::toHuman((int) $bytesPerSecond, $precision) . '/s';
  }
}
