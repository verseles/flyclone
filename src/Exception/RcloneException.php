<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

use RuntimeException;

/**
 * Base exception for all rclone-related errors.
 *
 * Provides enhanced context including command executed, provider info, and paths.
 */
class RcloneException extends RuntimeException
{
    /** @var array Additional context about the error */
    protected array $context = [];

    /**
     * Set additional context for the exception.
     *
     * @param array $context Contextual information (command, provider, path, etc.)
     */
    public function setContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Get the exception context.
     *
     * @return array The context array.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a specific context value.
     *
     * @param string $key The context key.
     * @param mixed $default Default value if key doesn't exist.
     *
     * @return mixed The context value or default.
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if this exception represents a retryable error.
     *
     * @return bool True if the operation can be retried.
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Get a detailed string representation of the exception.
     *
     * @return string Detailed exception information.
     */
    public function getDetailedMessage(): string
    {
        $details = [$this->getMessage()];

        if (! empty($this->context)) {
            $details[] = 'Context:';
            foreach ($this->context as $key => $value) {
                $details[] = "  $key: " . (is_array($value) ? json_encode($value) : $value);
            }
        }

        return implode("\n", $details);
    }
}
