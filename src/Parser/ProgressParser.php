<?php

namespace Verseles\Flyclone\Parser;

/**
 * Parser for rclone real-time transfer progress output.
 */
final class ProgressParser
{
  /** Regex pattern base for parsing rclone transfer progress */
  private const PROGRESS_REGEX_BASE = '([\d.]+\s[KMGT]?i?B)\s*\/\s*([\d.]+\s[KMGT]?i?B|-),\s*(\d+)\%,\s*([\d.]+\s[KMGT]?i?B\/s|-),\s*ETA\s*(\S+)';

  /** Regex for basic transfer progress (without file transfer count) */
  private const PROGRESS_REGEX = '/' . self::PROGRESS_REGEX_BASE . '/iu';

  /** Regex for transfer progress with file transfer count (xfr#N/M) */
  private const PROGRESS_XFR_REGEX = '/' . self::PROGRESS_REGEX_BASE . '\s*\(xfr#(\d+\/\d+)\)/iu';

  /** Default progress structure */
  private const DEFAULT_PROGRESS = [
    'raw' => '',
    'dataSent' => '0 B',
    'dataTotal' => '0 B',
    'sent' => 0,
    'speed' => '0 B/s',
    'eta' => '-',
    'xfr' => '0/0',
  ];

  /**
   * Gets the default progress structure.
   *
   * @return object Default progress object
   */
  public static function getDefault(): object
  {
    return (object) self::DEFAULT_PROGRESS;
  }

  /**
   * Parses rclone progress output buffer.
   *
   * @param string $buffer The output buffer content from rclone stdout
   * @return object|null Progress object if parsed successfully, null otherwise
   */
  public static function parse(string $buffer): ?object
  {
    // Try matching the version with xfr count first
    if (preg_match(self::PROGRESS_XFR_REGEX, $buffer, $matches) && count($matches) >= 7) {
      return self::createProgress(
        $matches[0],
        $matches[1],
        $matches[2],
        (int) $matches[3],
        $matches[4],
        $matches[5],
        $matches[6]
      );
    }

    // Fallback to matching without xfr count
    if (preg_match(self::PROGRESS_REGEX, $buffer, $matches) && count($matches) >= 6) {
      return self::createProgress(
        $matches[0],
        $matches[1],
        $matches[2],
        (int) $matches[3],
        $matches[4],
        $matches[5]
      );
    }

    return null;
  }

  /**
   * Creates a progress object from parsed values.
   *
   * @param string $raw Raw progress string
   * @param string $dataSent Amount of data sent
   * @param string $dataTotal Total amount of data
   * @param int $sentPercentage Percentage completed
   * @param string $speed Current transfer speed
   * @param string $eta Estimated time remaining
   * @param string|null $xfr File transfer count (e.g., "1/10")
   * @return object Progress object
   */
  private static function createProgress(
    string $raw,
    string $dataSent,
    string $dataTotal,
    int $sentPercentage,
    string $speed,
    string $eta,
    ?string $xfr = '1/1'
  ): object {
    return (object) [
      'raw' => trim($raw),
      'dataSent' => trim($dataSent),
      'dataTotal' => trim($dataTotal),
      'sent' => $sentPercentage,
      'speed' => trim($speed),
      'eta' => trim($eta),
      'xfr' => $xfr ?? '1/1',
    ];
  }
}
