<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

/**
 * Simple logger for Flyclone operations.
 *
 * Supports optional PSR-3 logger integration and debug mode.
 * When no PSR-3 logger is set, logs are stored internally.
 */
class Logger
{
    /** Log level constants (PSR-3 compatible values) */
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /** @var object|null PSR-3 compatible logger */
    private static ?object $logger = null;
    private static bool $debugMode = false;
    private static array $logs = [];
    private static int $maxLogs = 1000;

    /**
     * Set a PSR-3 compatible logger.
     */
    public static function setLogger(?object $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Get the configured PSR-3 logger.
     */
    public static function getLogger(): ?object
    {
        return self::$logger;
    }

    /**
     * Enable or disable debug mode.
     * When enabled, commands and responses are logged.
     */
    public static function setDebugMode(bool $enabled): void
    {
        self::$debugMode = $enabled;
    }

    /**
     * Check if debug mode is enabled.
     */
    public static function isDebugMode(): bool
    {
        return self::$debugMode;
    }

    /**
     * Log a debug message (only if debug mode is enabled).
     */
    public static function debug(string $message, array $context = []): void
    {
        if (self::$debugMode) {
            self::log(self::DEBUG, $message, $context);
        }
    }

    /**
     * Log an info message.
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log a message at the specified level.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        // Redact secrets from context
        if (SecretsRedactor::isEnabled()) {
            $context = self::redactContext($context);
        }

        // Store in internal log
        self::$logs[] = [
            'time' => microtime(true),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        // Trim logs if over limit
        if (count(self::$logs) > self::$maxLogs) {
            self::$logs = array_slice(self::$logs, -self::$maxLogs);
        }

        // Forward to PSR-3 logger if set
        if (self::$logger !== null) {
            self::$logger->log($level, $message, $context);
        }
    }

    /**
     * Get all stored logs.
     */
    public static function getLogs(): array
    {
        return self::$logs;
    }

    /**
     * Clear stored logs.
     */
    public static function clearLogs(): void
    {
        self::$logs = [];
    }

    /**
     * Get logs filtered by level.
     */
    public static function getLogsByLevel(string $level): array
    {
        return array_filter(self::$logs, fn($log) => $log['level'] === $level);
    }

    /**
     * Log command execution (debug mode only).
     */
    public static function logCommand(string $command, array $envs = []): void
    {
        if (self::$debugMode) {
            self::debug('Executing rclone command', [
                'command' => $command,
                'env_count' => count($envs),
            ]);
        }
    }

    /**
     * Log command result (debug mode only).
     */
    public static function logResult(bool $success, float $duration, ?string $output = null): void
    {
        if (self::$debugMode) {
            self::debug('Command completed', [
                'success' => $success,
                'duration_ms' => round($duration * 1000, 2),
                'output_length' => $output !== null ? strlen($output) : 0,
            ]);
        }
    }

    /**
     * Redact sensitive information from context array.
     */
    private static function redactContext(array $context): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'credential', 'auth'];

        $redact = function ($value, $key) use (&$redact, $sensitiveKeys) {
            if (is_array($value)) {
                return array_map($redact, $value, array_keys($value));
            }

            if (is_string($key)) {
                foreach ($sensitiveKeys as $sensitiveKey) {
                    if (stripos($key, $sensitiveKey) !== false) {
                        return SecretsRedactor::REDACTED;
                    }
                }
            }

            return $value;
        };

        return array_map($redact, $context, array_keys($context));
    }
}
