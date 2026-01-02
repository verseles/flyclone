<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use Symfony\Component\Process\Process;

/**
 * Parser for rclone progress output.
 *
 * Handles both complete and fragmented output buffers gracefully.
 */
class ProgressParser
{
    private object $progress;

    /** @var string Buffer for incomplete lines */
    private string $lineBuffer = '';

    private static array $defaultProgress = [
        'raw' => '',
        'dataSent' => '0 B',
        'dataTotal' => '0 B',
        'sent' => 0,
        'speed' => '0 B/s',
        'eta' => '-',
        'xfr' => '0/0',
    ];

    public function __construct()
    {
        $this->reset();
    }

    /**
     * Parse progress output from rclone.
     *
     * Handles fragmented buffers by accumulating incomplete lines.
     */
    public function parse(string $type, string $buffer): void
    {
        if ($type !== Process::OUT) {
            return;
        }

        // Append to line buffer
        $this->lineBuffer .= $buffer;

        // Process complete lines
        $lines = explode("\n", $this->lineBuffer);

        // Keep the last incomplete line in the buffer
        $this->lineBuffer = array_pop($lines) ?? '';

        // Process each complete line
        foreach ($lines as $line) {
            $this->parseLine(trim($line));
        }

        // Also try to parse the current buffer in case it contains progress info
        if ($this->lineBuffer !== '') {
            $this->parseLine($this->lineBuffer);
        }
    }

    /**
     * Parse a single line of progress output.
     */
    private function parseLine(string $line): void
    {
        if ($line === '') {
            return;
        }

        $regexBase = '([\d.]+\s[KMGT]?i?B)\s*\/\s*([\d.]+\s[KMGT]?i?B|-),\s*(\d+)\%,\s*([\d.]+\s[KMGT]?i?B\/s|-),\s*ETA\s*(\S+)';
        $regexXfr = '/' . $regexBase . '\s*\(xfr#(\d+\/\d+)\)/iu';
        $regex = '/' . $regexBase . '/iu';

        // Try to match with transfer count first
        if (preg_match($regexXfr, $line, $matches) && count($matches) >= 7) {
            $this->setProgressData(
                $matches[0],
                $matches[1],
                $matches[2],
                (int) $matches[3],
                $matches[4],
                $matches[5],
                $matches[6]
            );
            return;
        }

        // Try basic progress pattern
        if (preg_match($regex, $line, $matches) && count($matches) >= 6) {
            $this->setProgressData(
                $matches[0],
                $matches[1],
                $matches[2],
                (int) $matches[3],
                $matches[4],
                $matches[5]
            );
        }
    }

    private function setProgressData(
        string $raw,
        string $dataSent,
        string $dataTotal,
        int $sentPercentage,
        string $speed,
        string $eta,
        ?string $xfr = '1/1'
    ): void {
        $this->progress = (object) [
            'raw' => trim($raw),
            'dataSent' => trim($dataSent),
            'dataTotal' => trim($dataTotal),
            'sent' => $sentPercentage,
            'speed' => trim($speed),
            'eta' => trim($eta),
            'xfr' => $xfr ?? '1/1',
        ];
    }

    /**
     * Get the current progress object.
     */
    public function getProgress(): object
    {
        return $this->progress;
    }

    /**
     * Get progress as array for easier manipulation.
     */
    public function getProgressArray(): array
    {
        return (array) $this->progress;
    }

    /**
     * Get the percentage complete (0-100).
     */
    public function getPercentage(): int
    {
        return $this->progress->sent;
    }

    /**
     * Check if transfer is complete (100%).
     */
    public function isComplete(): bool
    {
        return $this->progress->sent >= 100;
    }

    /**
     * Reset the parser state.
     */
    public function reset(): void
    {
        $this->progress = (object) self::$defaultProgress;
        $this->lineBuffer = '';
    }

    /**
     * Flush any remaining buffer content.
     *
     * Call this when the process ends to ensure any remaining
     * buffered content is processed.
     */
    public function flush(): void
    {
        if ($this->lineBuffer !== '') {
            $this->parseLine(trim($this->lineBuffer));
            $this->lineBuffer = '';
        }
    }
}
