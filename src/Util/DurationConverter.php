<?php

namespace Verseles\Flyclone\Util;

/**
 * Utility class for converting between duration formats.
 */
final class DurationConverter
{
  /** Seconds per time unit */
  private const SECONDS_PER_DAY = 86400;
  private const SECONDS_PER_HOUR = 3600;
  private const SECONDS_PER_MINUTE = 60;
  private const MILLISECONDS_PER_SECOND = 1000;

  /**
   * Converts a duration string to seconds.
   *
   * Supports formats like:
   * - "1d2h3m4s" (days, hours, minutes, seconds)
   * - "1m30s" (minutes and seconds)
   * - "500ms" (milliseconds)
   * - "90" (plain seconds)
   *
   * @param string $durationStr The duration string
   * @return float The duration in seconds
   */
  public static function toSeconds(string $durationStr): float
  {
    $totalSeconds = 0.0;

    // Parse days
    if (preg_match('/(\d+(?:\.\d+)?)d/', $durationStr, $matches)) {
      $totalSeconds += (float) $matches[1] * self::SECONDS_PER_DAY;
    }

    // Parse hours
    if (preg_match('/(\d+(?:\.\d+)?)h/', $durationStr, $matches)) {
      $totalSeconds += (float) $matches[1] * self::SECONDS_PER_HOUR;
    }

    // Parse minutes (avoid matching 'ms')
    if (preg_match('/(\d+(?:\.\d+)?)m(?!s)/', $durationStr, $matches)) {
      $totalSeconds += (float) $matches[1] * self::SECONDS_PER_MINUTE;
    }

    // Parse seconds (must not be followed by anything)
    if (preg_match('/(\d+(?:\.\d+)?)s(?!$|[^m])/', $durationStr, $matches)) {
      $totalSeconds += (float) $matches[1];
    } elseif (preg_match('/(\d+(?:\.\d+)?)s$/', $durationStr, $matches)) {
      $totalSeconds += (float) $matches[1];
    }

    // Parse milliseconds
    if (preg_match('/(\d+(?:\.\d+)?)ms/', $durationStr, $matches)) {
      $totalSeconds += (float) $matches[1] / self::MILLISECONDS_PER_SECOND;
    }

    // If no units were matched, try parsing as plain seconds
    if ($totalSeconds === 0.0 && is_numeric($durationStr)) {
      return (float) $durationStr;
    }

    return $totalSeconds;
  }

  /**
   * Converts seconds to a human-readable duration string.
   *
   * @param float $seconds The duration in seconds
   * @param bool $includeMs Whether to include milliseconds for sub-second precision
   * @return string The formatted duration (e.g., "1h30m45s")
   */
  public static function toHuman(float $seconds, bool $includeMs = false): string
  {
    if ($seconds <= 0) {
      return '0s';
    }

    $parts = [];

    $days = (int) floor($seconds / self::SECONDS_PER_DAY);
    if ($days > 0) {
      $parts[] = $days . 'd';
      $seconds -= $days * self::SECONDS_PER_DAY;
    }

    $hours = (int) floor($seconds / self::SECONDS_PER_HOUR);
    if ($hours > 0) {
      $parts[] = $hours . 'h';
      $seconds -= $hours * self::SECONDS_PER_HOUR;
    }

    $minutes = (int) floor($seconds / self::SECONDS_PER_MINUTE);
    if ($minutes > 0) {
      $parts[] = $minutes . 'm';
      $seconds -= $minutes * self::SECONDS_PER_MINUTE;
    }

    if ($seconds > 0 || empty($parts)) {
      if ($includeMs && $seconds < 1 && $seconds > 0) {
        $ms = (int) round($seconds * self::MILLISECONDS_PER_SECOND);
        $parts[] = $ms . 'ms';
      } else {
        $wholeSeconds = (int) floor($seconds);
        if ($wholeSeconds > 0 || empty($parts)) {
          $parts[] = $wholeSeconds . 's';
        }
      }
    }

    return implode('', $parts);
  }
}
