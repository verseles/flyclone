<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

/**
 * Redacts sensitive information from error messages and debug output.
 *
 * This class identifies and masks credentials, tokens, and other secrets
 * that might appear in rclone command output or error messages.
 */
class SecretsRedactor
{
    public const REDACTED = '[REDACTED]';

    /**
     * Patterns for sensitive keys in environment variables.
     * These are matched case-insensitively.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'secret',
        'token',
        'key',
        'credential',
        'auth',
        'bearer',
        'api_key',
        'apikey',
        'access_key',
        'private',
    ];

    /**
     * Patterns for URLs with embedded credentials.
     */
    private const URL_PATTERNS = [
        // Basic auth in URLs: https://user:pass@host
        '/(:\/\/)([^:]+):([^@]+)@/i',
        // S3-style secret in query params: ?AWSAccessKeyId=...&Signature=...
        '/(AWSAccessKeyId|Signature|X-Amz-Credential|X-Amz-Signature)=([^&\s]+)/i',
        // Generic token in query params
        '/(token|key|secret|password|auth)=([^&\s]+)/i',
    ];

    /**
     * Known rclone obscured password pattern.
     * Obscured passwords start with specific characters.
     */
    private const OBSCURED_PATTERN = '/[A-Za-z0-9_-]{20,}/';

    private static bool $enabled = true;

    /**
     * Enable or disable secrets redaction globally.
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if redaction is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Redact sensitive information from a message.
     *
     * @param string $message The message that may contain secrets.
     * @param array  $knownSecrets Additional secrets to redact (values from provider config).
     *
     * @return string The message with secrets replaced by [REDACTED].
     */
    public static function redact(string $message, array $knownSecrets = []): string
    {
        if (!self::$enabled || $message === '') {
            return $message;
        }

        // First, redact any known secrets passed explicitly
        $message = self::redactKnownSecrets($message, $knownSecrets);

        // Redact environment variable patterns
        $message = self::redactEnvVars($message);

        // Redact URLs with embedded credentials
        $message = self::redactUrls($message);

        return $message;
    }

    /**
     * Redact known secret values from the message.
     */
    private static function redactKnownSecrets(string $message, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if (is_string($secret) && strlen($secret) >= 4) {
                $message = str_replace($secret, self::REDACTED, $message);
            }
        }
        return $message;
    }

    /**
     * Redact sensitive environment variable values.
     */
    private static function redactEnvVars(string $message): string
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            // Match RCLONE_CONFIG_*_{KEY}=value or RCLONE_{KEY}=value
            $pattern = '/RCLONE_(?:CONFIG_[A-Z0-9_]+_)?' . strtoupper($key) . '[A-Z0-9_]*=([^\s]+)/i';
            $message = preg_replace($pattern, 'RCLONE_$0=' . self::REDACTED, $message) ?? $message;

            // Simpler replacement for the value part
            $pattern2 = '/(RCLONE_(?:CONFIG_[A-Z0-9_]+_)?' . strtoupper($key) . '[A-Z0-9_]*=)([^\s]+)/i';
            $message = preg_replace($pattern2, '$1' . self::REDACTED, $message) ?? $message;
        }

        return $message;
    }

    /**
     * Redact credentials from URLs.
     */
    private static function redactUrls(string $message): string
    {
        foreach (self::URL_PATTERNS as $pattern) {
            if (str_contains($pattern, '@')) {
                // URL with basic auth: preserve scheme and host
                $message = preg_replace($pattern, '$1' . self::REDACTED . ':' . self::REDACTED . '@', $message) ?? $message;
            } else {
                // Query params: preserve param name
                $message = preg_replace($pattern, '$1=' . self::REDACTED, $message) ?? $message;
            }
        }

        return $message;
    }

    /**
     * Extract secrets from provider configuration for targeted redaction.
     *
     * @param array $providerFlags The flags array from a Provider.
     *
     * @return array List of secret values that should be redacted.
     */
    public static function extractSecretsFromFlags(array $providerFlags): array
    {
        $secrets = [];

        foreach ($providerFlags as $key => $value) {
            if (!is_string($value) || strlen($value) < 4) {
                continue;
            }

            $keyLower = strtolower($key);
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($keyLower, $sensitiveKey)) {
                    $secrets[] = $value;
                    break;
                }
            }
        }

        return $secrets;
    }
}
