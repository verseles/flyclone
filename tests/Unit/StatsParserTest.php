<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Verseles\Flyclone\StatsParser;

class StatsParserTest extends TestCase
{
    #[Test]
    public function parse_standard_output(): void
    {
        $output = <<<'OUTPUT'
Transferred:   1.5 GiB / 1.5 GiB, 100%, 10 MiB/s, ETA 0s
Errors:                 0
Checks:                10
Transferred:           50 / 50, 100%
Elapsed time:      1m30.5s
OUTPUT;

        $stats = StatsParser::parse($output);

        self::assertEquals(1610612736, $stats->bytes);
        self::assertEquals(50, $stats->files);
        self::assertEquals(0, $stats->errors);
        self::assertEquals(10, $stats->checks);
        self::assertEquals(90.5, $stats->elapsed_time);
    }

    #[Test]
    public function parse_empty_output(): void
    {
        $stats = StatsParser::parse('');

        self::assertEquals(0, $stats->bytes);
        self::assertEquals(0, $stats->files);
        self::assertEquals(0, $stats->errors);
        self::assertEquals(0, $stats->checks);
        self::assertEquals(0.0, $stats->elapsed_time);
    }

    #[Test]
    public function parse_json_output(): void
    {
        $output = <<<'JSON'
{"bytes": 1610612736, "checks": 10, "elapsedTime": 90.5, "errors": 0, "speed": 10485760, "transfers": 50}
JSON;

        $stats = StatsParser::parse($output);

        self::assertEquals(1610612736, $stats->bytes);
        self::assertEquals(50, $stats->files);
        self::assertEquals(0, $stats->errors);
        self::assertEquals(10, $stats->checks);
        self::assertEquals(90.5, $stats->elapsed_time);
        self::assertEquals('10 MiB/s', $stats->speed_human);
        self::assertEquals(10485760, $stats->speed_bytes_per_second);
    }

    #[Test]
    public function parse_multiline_json_output(): void
    {
        $output = <<<'JSON'
{"bytes": 100, "transfers": 1}
{"bytes": 200, "transfers": 2}
JSON;

        $stats = StatsParser::parse($output);

        self::assertEquals(200, $stats->bytes);
        self::assertEquals(2, $stats->files);
    }

    #[Test]
    public function convert_size_to_bytes_supports_iec_units(): void
    {
        self::assertEquals(1610612736, StatsParser::convertSizeToBytes('1.5 GiB'));
    }

    #[Test]
    public function convert_duration_to_seconds_distinguishes_minutes_and_milliseconds(): void
    {
        self::assertEquals(90.0, StatsParser::convertDurationToSeconds('1m30s'));
        self::assertEquals(0.5, StatsParser::convertDurationToSeconds('500ms'));
    }
}
