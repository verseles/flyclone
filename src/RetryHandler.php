<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use Verseles\Flyclone\Exception\RcloneException;

/**
 * Handles retry logic with exponential backoff for transient failures.
 */
class RetryHandler
{
    private int $maxAttempts = 3;
    private int $baseDelayMs = 1000;
    private float $multiplier = 2.0;
    private int $maxDelayMs = 30000;
    private bool $enabled = true;

    /** @var callable|null Custom retry condition */
    private $retryCondition = null;

    /** @var callable|null Callback called before each retry */
    private $onRetry = null;

    /**
     * Create a new retry handler with default settings.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set maximum number of retry attempts.
     */
    public function maxAttempts(int $attempts): self
    {
        $this->maxAttempts = max(1, $attempts);
        return $this;
    }

    /**
     * Set base delay between retries in milliseconds.
     */
    public function baseDelay(int $milliseconds): self
    {
        $this->baseDelayMs = max(0, $milliseconds);
        return $this;
    }

    /**
     * Set the multiplier for exponential backoff.
     */
    public function multiplier(float $multiplier): self
    {
        $this->multiplier = max(1.0, $multiplier);
        return $this;
    }

    /**
     * Set maximum delay between retries in milliseconds.
     */
    public function maxDelay(int $milliseconds): self
    {
        $this->maxDelayMs = max(0, $milliseconds);
        return $this;
    }

    /**
     * Enable or disable retry mechanism.
     */
    public function enabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Set a custom condition for determining if an exception should trigger a retry.
     *
     * @param callable $condition Function that receives the exception and returns bool.
     */
    public function retryWhen(callable $condition): self
    {
        $this->retryCondition = $condition;
        return $this;
    }

    /**
     * Set a callback to be called before each retry attempt.
     *
     * @param callable $callback Function that receives (attempt number, exception, delay).
     */
    public function onRetry(callable $callback): self
    {
        $this->onRetry = $callback;
        return $this;
    }

    /**
     * Execute an operation with retry logic.
     *
     * @param callable $operation The operation to execute.
     *
     * @return mixed The result of the operation.
     * @throws \Exception The last exception if all retries fail.
     */
    public function execute(callable $operation): mixed
    {
        if (!$this->enabled) {
            return $operation();
        }

        $lastException = null;
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;

                // Check if we should retry
                if (!$this->shouldRetry($e, $attempt)) {
                    throw $e;
                }

                // Calculate delay with exponential backoff
                $delay = $this->calculateDelay($attempt);

                // Call retry callback if set
                if ($this->onRetry !== null) {
                    ($this->onRetry)($attempt, $e, $delay);
                }

                Logger::debug("Retry attempt $attempt/$this->maxAttempts after {$delay}ms", [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);

                // Wait before retry
                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Determine if an exception should trigger a retry.
     */
    private function shouldRetry(\Exception $exception, int $attempt): bool
    {
        // Don't retry if we've exhausted attempts
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        // Use custom condition if set
        if ($this->retryCondition !== null) {
            return ($this->retryCondition)($exception);
        }

        // Default: retry only RcloneExceptions that are marked as retryable
        if ($exception instanceof RcloneException) {
            return $exception->isRetryable();
        }

        return false;
    }

    /**
     * Calculate delay for the given attempt using exponential backoff.
     */
    private function calculateDelay(int $attempt): int
    {
        $delay = (int) ($this->baseDelayMs * ($this->multiplier ** ($attempt - 1)));

        // Add jitter (±10%) to prevent thundering herd
        $jitter = (int) ($delay * 0.1 * (mt_rand(0, 200) - 100) / 100);
        $delay += $jitter;

        return min($delay, $this->maxDelayMs);
    }

    /**
     * Get current configuration as array.
     */
    public function getConfig(): array
    {
        return [
            'max_attempts' => $this->maxAttempts,
            'base_delay_ms' => $this->baseDelayMs,
            'multiplier' => $this->multiplier,
            'max_delay_ms' => $this->maxDelayMs,
            'enabled' => $this->enabled,
        ];
    }
}
