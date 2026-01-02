<?php

namespace Verseles\Flyclone;

use Verseles\Flyclone\Exception\DirectoryNotFoundException;
use Verseles\Flyclone\Exception\FatalErrorException;
use Verseles\Flyclone\Exception\FileNotFoundException;
use Verseles\Flyclone\Exception\LessSeriousErrorException;
use Verseles\Flyclone\Exception\MaxTransferReachedException;
use Verseles\Flyclone\Exception\NoFilesTransferredException;
use Verseles\Flyclone\Exception\ProcessTimedOutException;
use Verseles\Flyclone\Exception\SyntaxErrorException;
use Verseles\Flyclone\Exception\TemporaryErrorException;
use Verseles\Flyclone\Exception\UnknownErrorException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessManager
{
    private static string $bin;
    private static int $timeout = 120;
    private static int $idleTimeout = 100;
    private static string $input = '';

    public static function getTimeout(): int
    {
        return self::$timeout;
    }

    public static function setTimeout(int $timeout): void
    {
        self::$timeout = $timeout;
    }

    public static function getIdleTimeout(): int
    {
        return self::$idleTimeout;
    }

    public static function setIdleTimeout(int $idleTimeout): void
    {
        self::$idleTimeout = $idleTimeout;
    }

    public static function getInput(): string
    {
        return self::$input;
    }

    public static function setInput(string $input): void
    {
        self::$input = $input;
    }

    public static function getBin(): string
    {
        return self::$bin ?? self::guessBin();
    }

    public static function setBin(string $bin): void
    {
        self::$bin = $bin;
    }

    public static function guessBin(): string
    {
        if (isset(self::$bin) && self::$bin !== '') {
            return self::$bin;
        }

        $finder = new ExecutableFinder();
        $rclonePath = $finder->find('rclone', '/usr/bin/rclone', [
            '/usr/local/bin',
            '/usr/bin',
            '/bin',
            '/usr/local/sbin',
            '/var/lib/snapd/snap/bin',
        ]);

        if ($rclonePath === null) {
            throw new \RuntimeException('Rclone binary not found. Please ensure rclone is installed and in your PATH, or set the path manually using ProcessManager::setBin().');
        }

        self::$bin = $rclonePath;

        return self::$bin;
    }

    public function run(array $command, array $envs = [], ?callable $onProgress = null): Process
    {
        $process = new Process($command, sys_get_temp_dir(), $envs);
        $process->setTimeout(self::getTimeout());
        $process->setIdleTimeout(self::getIdleTimeout());

        if (!empty(self::getInput())) {
            $process->setInput(self::getInput());
        }

        return $this->execute($process, $onProgress);
    }

    public function execute(Process $process, ?callable $onProgress = null): Process
    {
        try {
            if ($onProgress) {
                $process->mustRun($onProgress);
            } else {
                $process->mustRun();
            }
            return $process;
        } catch (ProcessFailedException $e) {
            $this->handleFailure($e);
        } catch (SymfonyProcessTimedOutException $e) {
            throw new ProcessTimedOutException($e);
        } catch (\Exception $e) {
            throw new UnknownErrorException($e, 'An unexpected error occurred: ' . $e->getMessage());
        } finally {
            self::setInput('');
        }
    }

    private function handleFailure(ProcessFailedException $exception): never
    {
        $process = $exception->getProcess();
        $code = $process->getExitCode();
        $msg = trim($process->getErrorOutput());

        if (empty($msg)) {
            $msg = 'Rclone process failed. Stdout: ' . trim($process->getOutput());
        }

        match ((int) $code) {
            1 => throw new SyntaxErrorException($exception, $msg, (int) $code),
            3 => throw new DirectoryNotFoundException($exception, $msg, (int) $code),
            4 => throw new FileNotFoundException($exception, $msg, (int) $code),
            5 => throw new TemporaryErrorException($exception, $msg, (int) $code),
            6 => throw new LessSeriousErrorException($exception, $msg, (int) $code),
            7 => throw new FatalErrorException($exception, $msg, (int) $code),
            8 => throw new MaxTransferReachedException($exception, $msg, (int) $code),
            9 => throw new NoFilesTransferredException($exception, $msg, (int) $code),
            default => throw new UnknownErrorException($exception, "Rclone error (Code: $code): $msg", (int) $code),
        };
    }

    public static function obscure(string $secret): string
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('Cannot obscure an empty secret.');
        }

        $process = new Process([self::getBin(), 'obscure', $secret]);
        $process->setTimeout(3);
        $process->mustRun();

        $output = trim($process->getOutput());

        if ($output === '') {
            throw new \RuntimeException('rclone obscure returned empty output.');
        }

        return $output;
    }
}
