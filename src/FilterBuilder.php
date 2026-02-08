<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

/**
 * Fluent builder for rclone filter patterns.
 *
 * Provides a clean API for building include/exclude filter rules
 * that are passed to rclone commands.
 */
class FilterBuilder
{
    /** @var array Include patterns */
    private array $includes = [];

    /** @var array Exclude patterns */
    private array $excludes = [];

    /** @var int|null Minimum file size in bytes */
    private ?int $minSize = null;

    /** @var int|null Maximum file size in bytes */
    private ?int $maxSize = null;

    /** @var int|null Minimum file age in seconds */
    private ?int $minAge = null;

    /** @var int|null Maximum file age in seconds */
    private ?int $maxAge = null;

    /** @var bool Whether to delete excluded files */
    private bool $deleteExcluded = false;

    /**
     * Create a new filter builder.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Add an include pattern.
     *
     * @param string $pattern Glob pattern (e.g., "*.txt", "photos/**")
     */
    public function include(string $pattern): self
    {
        $this->includes[] = $pattern;

        return $this;
    }

    /**
     * Add multiple include patterns.
     *
     * @param array $patterns Array of glob patterns
     */
    public function includeMany(array $patterns): self
    {
        foreach ($patterns as $pattern) {
            $this->include($pattern);
        }

        return $this;
    }

    /**
     * Add an exclude pattern.
     *
     * @param string $pattern Glob pattern (e.g., "*.tmp", "node_modules/**")
     */
    public function exclude(string $pattern): self
    {
        $this->excludes[] = $pattern;

        return $this;
    }

    /**
     * Add multiple exclude patterns.
     *
     * @param array $patterns Array of glob patterns
     */
    public function excludeMany(array $patterns): self
    {
        foreach ($patterns as $pattern) {
            $this->exclude($pattern);
        }

        return $this;
    }

    /**
     * Include only files with specific extensions.
     *
     * @param string|array $extensions Extension(s) without dot (e.g., "jpg", ["jpg", "png"])
     */
    public function extensions(string|array $extensions): self
    {
        $exts = is_array($extensions) ? $extensions : [$extensions];
        foreach ($exts as $ext) {
            $this->include('*.' . ltrim($ext, '.'));
        }

        return $this;
    }

    /**
     * Exclude common temporary and system files.
     */
    public function excludeCommon(): self
    {
        return $this->excludeMany([
            '*.tmp',
            '*.temp',
            '*.bak',
            '*~',
            '.DS_Store',
            'Thumbs.db',
            '.git/**',
            '.svn/**',
            'node_modules/**',
            '__pycache__/**',
            '*.pyc',
        ]);
    }

    /**
     * Exclude hidden files and directories.
     */
    public function excludeHidden(): self
    {
        return $this->exclude('.*');
    }

    /**
     * Set minimum file size.
     *
     * @param int|string $size Size in bytes or human-readable (e.g., "1M", "500K")
     */
    public function minSize(int|string $size): self
    {
        $this->minSize = $this->parseSize($size);

        return $this;
    }

    /**
     * Set maximum file size.
     *
     * @param int|string $size Size in bytes or human-readable (e.g., "100M", "1G")
     */
    public function maxSize(int|string $size): self
    {
        $this->maxSize = $this->parseSize($size);

        return $this;
    }

    /**
     * Only include files older than the specified age.
     *
     * @param string $age Age string (e.g., "1d", "2h", "30m")
     */
    public function olderThan(string $age): self
    {
        $this->minAge = $this->parseAge($age);

        return $this;
    }

    /**
     * Only include files newer than the specified age.
     *
     * @param string $age Age string (e.g., "1d", "2h", "30m")
     */
    public function newerThan(string $age): self
    {
        $this->maxAge = $this->parseAge($age);

        return $this;
    }

    /**
     * Enable deletion of excluded files on sync operations.
     */
    public function deleteExcluded(bool $delete = true): self
    {
        $this->deleteExcluded = $delete;

        return $this;
    }

    /**
     * Convert the filter to rclone flags array.
     *
     * @return array Flags to be merged with operation flags
     */
    public function toFlags(): array
    {
        $flags = [];

        // Include patterns
        foreach ($this->includes as $pattern) {
            $flags['include'][] = $pattern;
        }

        // Exclude patterns
        foreach ($this->excludes as $pattern) {
            $flags['exclude'][] = $pattern;
        }

        // Size filters
        if ($this->minSize !== null) {
            $flags['min-size'] = $this->formatSize($this->minSize);
        }
        if ($this->maxSize !== null) {
            $flags['max-size'] = $this->formatSize($this->maxSize);
        }

        // Age filters
        if ($this->minAge !== null) {
            $flags['min-age'] = $this->formatAge($this->minAge);
        }
        if ($this->maxAge !== null) {
            $flags['max-age'] = $this->formatAge($this->maxAge);
        }

        // Delete excluded
        if ($this->deleteExcluded) {
            $flags['delete-excluded'] = true;
        }

        return $flags;
    }

    /**
     * Convert to command line arguments.
     *
     * @return array Command line arguments
     */
    public function toArgs(): array
    {
        $args = [];

        foreach ($this->includes as $pattern) {
            $args[] = '--include';
            $args[] = $pattern;
        }

        foreach ($this->excludes as $pattern) {
            $args[] = '--exclude';
            $args[] = $pattern;
        }

        if ($this->minSize !== null) {
            $args[] = '--min-size';
            $args[] = $this->formatSize($this->minSize);
        }

        if ($this->maxSize !== null) {
            $args[] = '--max-size';
            $args[] = $this->formatSize($this->maxSize);
        }

        if ($this->minAge !== null) {
            $args[] = '--min-age';
            $args[] = $this->formatAge($this->minAge);
        }

        if ($this->maxAge !== null) {
            $args[] = '--max-age';
            $args[] = $this->formatAge($this->maxAge);
        }

        if ($this->deleteExcluded) {
            $args[] = '--delete-excluded';
        }

        return $args;
    }

    /**
     * Check if any filters are defined.
     */
    public function hasFilters(): bool
    {
        return ! empty($this->includes)
            || ! empty($this->excludes)
            || $this->minSize !== null
            || $this->maxSize !== null
            || $this->minAge !== null
            || $this->maxAge !== null;
    }

    /**
     * Reset all filters.
     */
    public function reset(): self
    {
        $this->includes = [];
        $this->excludes = [];
        $this->minSize = null;
        $this->maxSize = null;
        $this->minAge = null;
        $this->maxAge = null;
        $this->deleteExcluded = false;

        return $this;
    }

    /**
     * Parse a size string to bytes.
     */
    private function parseSize(int|string $size): int
    {
        if (is_int($size)) {
            return $size;
        }

        $units = ['B' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776];
        $size = strtoupper(trim($size));

        if (preg_match('/^([\d.]+)\s*([BKMGT])?$/i', $size, $matches)) {
            $value = (float) $matches[1];
            $unit = $matches[2] ?? 'B';

            return (int) ($value * ($units[$unit] ?? 1));
        }

        return (int) $size;
    }

    /**
     * Format bytes to human-readable size for rclone.
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . 'G';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . 'M';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'K';
        }

        return $bytes . 'B';
    }

    /**
     * Parse an age string to seconds.
     */
    private function parseAge(string $age): int
    {
        $units = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800, 'M' => 2592000, 'y' => 31536000];

        if (preg_match('/^(\d+)\s*([smhdwMy])?$/i', trim($age), $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2] ?? 's';

            return $value * ($units[$unit] ?? 1);
        }

        return (int) $age;
    }

    /**
     * Format seconds to age string for rclone.
     */
    private function formatAge(int $seconds): string
    {
        if ($seconds >= 86400) {
            return (int) ($seconds / 86400) . 'd';
        }
        if ($seconds >= 3600) {
            return (int) ($seconds / 3600) . 'h';
        }
        if ($seconds >= 60) {
            return (int) ($seconds / 60) . 'm';
        }

        return $seconds . 's';
    }
}
