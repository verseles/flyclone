<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

use Verseles\Flyclone\Exception\CredentialWarning;
use Verseles\Flyclone\Logger;
use Verseles\Flyclone\Rclone;

abstract class AbstractProvider
{
    /** @var bool TRUE if the provider does not support empty folders */
    protected bool $dirAgnostic = false;

    /** @var bool TRUE if the bucket provider is shown as a directory */
    protected bool $bucketAsDir = false;

    /** @var bool TRUE if the provider lists all files at once */
    protected bool $listsAsTree = false;

    /** @var array Fields that should be validated as required */
    protected array $requiredFields = [];

    /** @var array Fields that contain sensitive data (passwords, tokens, etc.) */
    protected array $sensitiveFields = ['password', 'secret', 'token', 'key', 'pass'];

    /** @var bool Whether to validate credentials on construction */
    protected static bool $validateCredentials = true;

    /** @var array Collected warnings during construction */
    protected array $warnings = [];

    /**
     * Enable or disable credential validation globally.
     */
    public static function setValidateCredentials(bool $validate): void
    {
        self::$validateCredentials = $validate;
    }

    /**
     * Check if credential validation is enabled.
     */
    public static function isValidateCredentials(): bool
    {
        return self::$validateCredentials;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function flags(): array
    {
        $prefix = 'RCLONE_CONFIG_' . $this->name() . '_';

        return $this->prefix_flags($prefix);
    }

    protected function prefix_flags(string $prefix): array
    {
        $prefixed = Rclone::prefix_flags($this->flags, $prefix);

        $prefixed[strtoupper($prefix . 'TYPE')] = $this->provider();

        return $prefixed;
    }

    public function name()
    {
        return $this->name;
    }

    public function backend($path = null)
    {
        return $this->name() . ':' . $path;
    }

    public function isDirAgnostic(): bool
    {
        return $this->dirAgnostic;
    }

    public function isBucketAsDir(): bool
    {
        return $this->bucketAsDir;
    }

    public function isListsAsTree(): bool
    {
        return $this->listsAsTree;
    }

    /**
     * Validate provider configuration.
     *
     * @throws \InvalidArgumentException If required fields are missing.
     */
    protected function validateConfig(array $flags): void
    {
        $missing = [];
        foreach ($this->requiredFields as $field) {
            if (!isset($flags[$field]) || $flags[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(sprintf(
                'Provider "%s" is missing required configuration: %s',
                $this->provider ?? 'unknown',
                implode(', ', $missing)
            ));
        }
    }

    /**
     * Check for plaintext credentials and emit warnings.
     *
     * @param array $flags The provider flags.
     * @param string $providerName The provider name for context.
     */
    protected function checkCredentials(array $flags, string $providerName): void
    {
        if (!self::$validateCredentials) {
            return;
        }

        foreach ($flags as $key => $value) {
            if (!is_string($value) || strlen($value) < 4) {
                continue;
            }

            $keyLower = strtolower($key);
            $isSensitive = false;

            foreach ($this->sensitiveFields as $sensitiveField) {
                if (str_contains($keyLower, $sensitiveField)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive && !$this->looksObscured($value)) {
                $warning = new CredentialWarning($providerName, $key);
                $this->warnings[] = $warning;

                Logger::warning($warning->getMessage(), [
                    'provider' => $providerName,
                    'field' => $key,
                ]);
            }
        }
    }

    /**
     * Check if a value appears to be obscured (rclone obscure output).
     *
     * Obscured values are base64-like strings that rclone produces.
     */
    protected function looksObscured(string $value): bool
    {
        // Obscured passwords are typically 20+ chars of base64-like characters
        // and don't contain spaces or special chars common in plaintext passwords
        if (strlen($value) < 20) {
            return false;
        }

        // Check if it looks like base64 (only alphanumeric, +, /, =, -)
        if (preg_match('/^[A-Za-z0-9+\/=_-]+$/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Get any warnings collected during construction.
     *
     * @return CredentialWarning[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if the provider has any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get the raw flags array.
     */
    public function getRawFlags(): array
    {
        return $this->flags ?? [];
    }

    /**
     * Get sensitive field names that should be redacted.
     */
    public function getSensitiveFields(): array
    {
        return $this->sensitiveFields;
    }

    /**
     * Extract secret values from this provider for redaction purposes.
     *
     * @return array List of secret values.
     */
    public function extractSecrets(): array
    {
        $secrets = [];

        foreach ($this->flags ?? [] as $key => $value) {
            if (!is_string($value) || strlen($value) < 4) {
                continue;
            }

            $keyLower = strtolower($key);
            foreach ($this->sensitiveFields as $sensitiveField) {
                if (str_contains($keyLower, $sensitiveField)) {
                    $secrets[] = $value;
                    break;
                }
            }
        }

        return $secrets;
    }
}
