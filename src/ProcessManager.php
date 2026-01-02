<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use Verseles\Flyclone\Exception\DirectoryNotFoundException;
use Verseles\Flyclone\Exception\FatalErrorException;
use Verseles\Flyclone\Exception\FileNotFoundException;
use Verseles\Flyclone\Exception\LessSeriousErrorException;
use Verseles\Flyclone\Exception\MaxTransferReachedException;
use Verseles\Flyclone\Exception\NoFilesTransferredException;
use Verseles\Flyclone\Exception\ProcessTimedOutException;
use Verseles\Flyclone\Exception\RcloneException;
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

    /** @var array Secrets to redact from error messages */
    private array $secrets = [];

    /** @var array Last executed command (for debugging) */
    private array $lastCommand = [];

    /** @var array Last environment variables (for debugging) */
    private array $lastEnvs = [];

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

    /**
     * Set secrets to be redacted from error messages.
     *
     * @param array $secrets Array of secret values to redact.
     */
    public function setSecrets(array $secrets): self
    {
        $this->secrets = $secrets;
        return $this;
    }

    /**
     * Get the last executed command (for debugging).
     *
     * @return array The command array.
     */
    public function getLastCommand(): array
    {
        return $this->lastCommand;
    }

    /**
     * Get the last executed command as a string (for debugging).
     * Sensitive values in environment variables are redacted.
     *
     * @return string The command string.
     */
    public function getLastCommandString(): string
    {
        return implode(' ', $this->lastCommand);
    }

    /**
     * Get the last environment variables (for debugging).
     * Sensitive values are redacted.
     *
     * @return array The environment variables with secrets redacted.
     */
    public function getLastEnvs(): array
    {
        return SecretsRedactor::isEnabled()
            ? $this->redactEnvValues($this->lastEnvs)
            : $this->lastEnvs;
    }

    /**
     * Redact sensitive values from environment variables.
     */
    private function redactEnvValues(array $envs): array
    {
        $redacted = [];
        foreach ($envs as $key => $value) {
            $keyLower = strtolower($key);
            $isSensitive = false;

            foreach (['password', 'secret', 'token', 'key', 'auth', 'credential'] as $sensitiveWord) {
                if (str_contains($keyLower, $sensitiveWord)) {
                    $isSensitive = true;
                    break;
                }
            }

            $redacted[$key] = $isSensitive ? SecretsRedactor::REDACTED : $value;
        }
        return $redacted;
    }

    public function run(array $command, array $envs = [], ?callable $onProgress = null, ?int $timeout = null): Process
    {
        $this->lastCommand = $command;
        $this->lastEnvs = $envs;

        $process = new Process($command, sys_get_temp_dir(), $envs);
        $process->setTimeout($timeout ?? self::getTimeout());
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

        // Redact secrets from error message
        $msg = SecretsRedactor::redact($msg, $this->secrets);

        // Create exception with enhanced context
        $rcloneException = match ((int) $code) {
            1 => new SyntaxErrorException($exception, $msg, (int) $code),
            3 => new DirectoryNotFoundException($exception, $msg, (int) $code),
            4 => new FileNotFoundException($exception, $msg, (int) $code),
            5 => new TemporaryErrorException($exception, $msg, (int) $code),
            6 => new LessSeriousErrorException($exception, $msg, (int) $code),
            7 => new FatalErrorException($exception, $msg, (int) $code),
            8 => new MaxTransferReachedException($exception, $msg, (int) $code),
            9 => new NoFilesTransferredException($exception, $msg, (int) $code),
            default => new UnknownErrorException($exception, "Rclone error (Code: $code): $msg", (int) $code),
        };

        // Add context to exception
        $rcloneException->setContext([
            'command' => $this->getLastCommandString(),
            'exit_code' => $code,
        ]);

        throw $rcloneException;
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
