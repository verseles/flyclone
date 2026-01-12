<?php

namespace Verseles\Flyclone\Parser;

use Verseles\Flyclone\Util\DurationConverter;
use Verseles\Flyclone\Util\SizeConverter;

/**
 * Parser for rclone transfer statistics output.
 */
final class StatsParser
{
  /** Regex for parsing transferred bytes from stats output */
  private const STATS_TRANSFERRED_REGEX = '/Transferred:\s+([\d.]+\s*[KMGTPI]?B)/i';

  /**
   * Parses the final statistics block from rclone's stderr output.
   *
   * @param string $output The stderr output from rclone
   * @return object An object containing parsed statistics
   */
  public static function parse(string $output): object
  {
    $stats = [
      'errors' => 0,
      'checks' => 0,
      'files' => 0,
      'bytes' => 0,
      'elapsed_time' => 0.0,
      'speed_human' => '0 B/s',
      'speed_bytes_per_second' => 0.0,
    ];

    $lines = explode("\n", $output);

    foreach ($lines as $line) {
      // Regex for --stats-one-line format
      if (preg_match(self::STATS_TRANSFERRED_REGEX, $line, $matches)) {
        $stats['bytes'] = SizeConverter::toBytes(trim($matches[1]));
        continue;
      }

      // Fallback to multiline stats parsing
      $parts = explode(':', $line, 2);
      if (count($parts) < 2) {
        continue;
      }

      $key = trim($parts[0]);
      $value = trim($parts[1]);

      switch ($key) {
        case 'Transferred':
          if (preg_match('/^\s*([\d.]+\s*[KMGTPI]?B)/i', $value, $byteMatches)) {
            $stats['bytes'] += SizeConverter::toBytes(trim($byteMatches[1]));
          } elseif (preg_match('/^\s*(\d+)\s*\/\s*\d+/', $value, $fileMatches)) {
            $stats['files'] += (int) $fileMatches[1];
          }
          break;

        case 'Renamed':
          $stats['files'] += (int) $value;
          break;

        case 'Errors':
          $stats['errors'] = (int) $value;
          break;

        case 'Checks':
          if (preg_match('/^\s*(\d+)/', $value, $matches)) {
            $stats['checks'] = (int) $matches[1];
          }
          break;

        case 'Elapsed time':
          $stats['elapsed_time'] = DurationConverter::toSeconds($value);
          break;
      }
    }

    // Calculate speed if we have elapsed time and bytes
    if ($stats['elapsed_time'] > 0 && $stats['bytes'] > 0) {
      $stats['speed_bytes_per_second'] = $stats['bytes'] / $stats['elapsed_time'];
      $stats['speed_human'] = SizeConverter::toSpeed($stats['speed_bytes_per_second']);
    }

    return (object) $stats;
  }
}
