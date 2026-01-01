<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use Symfony\Component\Process\Process;

class ProgressParser
{
    private object $progress;

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

    public function parse(string $type, string $buffer): void
    {
        if ($type !== Process::OUT) {
            return;
        }

        $regexBase = '([\d.]+\s[KMGT]?i?B)\s*\/\s*([\d.]+\s[KMGT]?i?B|-),\s*(\d+)\%,\s*([\d.]+\s[KMGT]?i?B\/s|-),\s*ETA\s*(\S+)';
        $regexXfr = '/' . $regexBase . '\s*\(xfr#(\d+\/\d+)\)/iu';
        $regex = '/' . $regexBase . '/iu';

        $matchesXfr = [];
        preg_match($regexXfr, $buffer, $matchesXfr);

        if (isset($matchesXfr[0]) && count($matchesXfr) >= 7) {
            $this->setProgressData(
                $matchesXfr[0],
                $matchesXfr[1],
                $matchesXfr[2],
                (int) $matchesXfr[3],
                $matchesXfr[4],
                $matchesXfr[5],
                $matchesXfr[6]
            );
            return;
        }

        $matches = [];
        preg_match($regex, $buffer, $matches);

        if (isset($matches[0]) && count($matches) >= 6) {
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

    public function getProgress(): object
    {
        return $this->progress;
    }

    public function reset(): void
    {
        $this->progress = (object) self::$defaultProgress;
    }
}
