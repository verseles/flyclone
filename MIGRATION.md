# Migration Guide: v3 to v4

This guide helps you migrate from Flyclone v3.x to v4.0.

## Quick Summary

**Good news:** v4 is **backward compatible** with v3. Your existing code will work without changes.

v4 adds new features and improves internals, but doesn't break the public API.

| Aspect | v3 | v4 | Action Required |
|--------|----|----|-----------------|
| Public API | ✅ | ✅ Same | None |
| PHP Version | 8.4+ | 8.4+ | None |
| Exceptions | Basic | Enhanced | None (additive) |
| New Features | - | Many | Optional adoption |

---

## Breaking Changes

### None

v4 maintains full backward compatibility with v3. All existing code will continue to work.

### Removed (Internal Only)

These were internal/undocumented and should not affect your code:

| Removed | Reason | Alternative |
|---------|--------|-------------|
| `resetProgress()` | Unused internal method | Not needed |
| `OBSCURED_PATTERN` constant | Unused | Not needed |

---

## New Features

### 1. Automatic Retry

Handle transient failures automatically with exponential backoff.

```php
// Simple: retry up to 5 times with 1 second base delay
$rclone->retry(maxAttempts: 5, baseDelayMs: 1000)
    ->copy($source, $dest);

// Advanced: custom retry handler
use Verseles\Flyclone\RetryHandler;

$handler = RetryHandler::create()
    ->maxAttempts(5)
    ->baseDelay(500)        // Start with 500ms
    ->multiplier(2.0)       // Double each retry: 500, 1000, 2000, 4000ms
    ->maxDelay(30000)       // Cap at 30 seconds
    ->jitter(true)          // Add randomness to prevent thundering herd
    ->onRetry(function ($attempt, $exception) {
        logger("Retry attempt $attempt: {$exception->getMessage()}");
    });

$rclone->withRetry($handler)->sync($source, $dest);
```

### 2. Fluent Filtering

Build complex filters with a readable API.

```php
use Verseles\Flyclone\FilterBuilder;

// v3 way (still works)
$rclone->copy($src, $dest, [
    'include' => '*.jpg',
    'exclude' => '**/temp/**',
    'min-size' => '100K',
]);

// v4 way (new)
$filter = FilterBuilder::create()
    ->include('*.jpg')
    ->include('*.png')
    ->exclude('**/temp/**')
    ->exclude('**/cache/**')
    ->minSize('100K')
    ->maxSize('50M')
    ->newerThan('7d')       // Files from last 7 days
    ->olderThan('1y');      // Files older than 1 year

$rclone->withFilter($filter)->copy($src, $dest);

// Shorthand for extensions
$filter = FilterBuilder::create()
    ->extensions(['jpg', 'png', 'gif', 'webp'])
    ->minSize('1K');

$rclone->withFilter($filter)->sync($src, $dest);

// Clear filter for next operation
$rclone->clearFilter();
```

### 3. Dry-Run Mode

Preview operations without making changes.

```php
// Enable dry-run
$rclone->dryRun(true);

// Operations won't actually execute
$result = $rclone->sync($source, $dest);

// Check if dry-run is active
if ($rclone->isDryRun()) {
    echo "Running in simulation mode";
}

// Disable dry-run
$rclone->dryRun(false);
```

### 4. Health Check

Verify provider connectivity before operations.

```php
$health = $rclone->healthCheck();

if ($health->healthy) {
    echo "Provider is accessible";
    echo "Latency: {$health->latency_ms}ms";
} else {
    echo "Provider error: {$health->error}";
    // Handle connectivity issue
}

// Use in monitoring/health endpoints
public function healthEndpoint(): JsonResponse
{
    $health = $this->rclone->healthCheck();
    return response()->json([
        'status' => $health->healthy ? 'ok' : 'error',
        'latency_ms' => $health->latency_ms,
    ], $health->healthy ? 200 : 503);
}
```

### 5. Command Introspection

Debug by inspecting executed commands.

```php
use Verseles\Flyclone\Logger;

// Enable debug mode to log all commands
Logger::setDebugMode(true);

// Run operations
$rclone->copy($source, $dest);

// Get the exact command that was executed
echo $rclone->getLastCommand();
// Output: "rclone copy source: dest: --transfers=4 ..."

// Get environment variables (secrets are redacted)
$envs = $rclone->getLastEnvs();
// ['RCLONE_CONFIG_MYS3_ACCESS_KEY_ID' => '[REDACTED]', ...]

// Get all debug logs
$logs = Logger::getLogs();
foreach ($logs as $log) {
    echo "[{$log['level']}] {$log['message']}\n";
}

// Clear logs
Logger::clearLogs();
```

### 6. Enhanced Exceptions

Exceptions now include rich context and retryable checking.

```php
use Verseles\Flyclone\Exception\TemporaryErrorException;
use Verseles\Flyclone\Exception\FileNotFoundException;

try {
    $rclone->copy($source, $dest);
} catch (TemporaryErrorException $e) {
    // Check if operation can be retried
    if ($e->isRetryable()) {
        // Implement retry logic or use RetryHandler
    }

    // Get detailed context
    $context = $e->getContext();
    // [
    //     'command' => 'copy',
    //     'provider' => 's3',
    //     'source' => 'bucket/path',
    //     'dest' => 'local/path',
    // ]

    // Get specific context value
    $command = $e->getContextValue('command', 'unknown');

    // Get detailed message with context
    echo $e->getDetailedMessage();

} catch (FileNotFoundException $e) {
    // Not retryable
    echo $e->isRetryable(); // false
}
```

**Exception Retryable Matrix:**

| Exception | `isRetryable()` |
|-----------|-----------------|
| `TemporaryErrorException` | `true` |
| `LessSeriousErrorException` | `true` |
| `FileNotFoundException` | `false` |
| `DirectoryNotFoundException` | `false` |
| `SyntaxErrorException` | `false` |
| `FatalErrorException` | `false` |
| `MaxTransferReachedException` | `false` |
| `NoFilesTransferredException` | `false` |

### 7. Secrets Redaction

Sensitive data is automatically redacted in errors and logs.

```php
use Verseles\Flyclone\SecretsRedactor;

// Automatic in exceptions and Logger output
// Passwords, tokens, and keys are replaced with [REDACTED]

// Manual redaction if needed
$safeMessage = SecretsRedactor::redact($sensitiveString);

// Patterns automatically redacted:
// - access_key_id=AKIAXXXXXXXX → access_key_id=[REDACTED]
// - secret_access_key=... → secret_access_key=[REDACTED]
// - password=... → password=[REDACTED]
// - token=... → token=[REDACTED]
// - Obscured strings (from Rclone::obscure())
```

### 8. New Commands

```php
// Bidirectional sync (sync changes both ways)
$result = $rclone->bisync($path1, $path2, ['resync' => true]);

// Get MD5 checksums
$checksums = $rclone->md5sum('/path/to/files');
// ['file1.txt' => '5eb63bbbe01eeed093cb22bb8f5acdc3', ...]

// Get SHA1 checksums
$checksums = $rclone->sha1sum('/path/to/files');
// ['file1.txt' => '2aae6c35c94fcfb415dbe95f408b9ce91ee846ed', ...]

// Static utility methods (no instance needed)
$remotes = Rclone::listRemotes();
// ['remote1', 'remote2', ...]

$configPath = Rclone::configFile();
// '/home/user/.config/rclone/rclone.conf'

$config = Rclone::configDump();
// (object) ['remote1' => ['type' => 's3', ...], ...]
```

### 9. Credential Validation

Warning when plaintext credentials are used.

```php
use Verseles\Flyclone\Exception\CredentialWarning;

// This will trigger a warning (in debug mode)
$s3 = new S3Provider('s3', [
    'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
    'secret_access_key' => 'plaintext-password',  // Warning!
]);

// Recommended: use obscure() for passwords
$s3 = new S3Provider('s3', [
    'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
    'secret_access_key' => Rclone::obscure('your-password'),
]);
```

---

## Architecture Changes (Internal)

v4 refactored the monolithic `Rclone` class into focused components:

| Component | Responsibility | Lines |
|-----------|---------------|-------|
| `Rclone` | Orchestration, public API | 1347 |
| `ProcessManager` | Process execution, error mapping | 250 |
| `CommandBuilder` | Command/env construction | 100 |
| `StatsParser` | Transfer statistics parsing | 150 |
| `ProgressParser` | Real-time progress parsing | 100 |
| `RetryHandler` | Retry logic with backoff | 150 |
| `FilterBuilder` | Filter pattern building | 200 |
| `SecretsRedactor` | Sensitive data redaction | 80 |
| `Logger` | Structured logging | 100 |

**This is an internal change.** The public API remains the same.

---

## Migration Steps

### Step 1: Update composer.json

```json
{
    "require": {
        "verseles/flyclone": "^4.0"
    }
}
```

```bash
composer update verseles/flyclone
```

### Step 2: Test Your Application

Your existing code should work without changes. Run your test suite:

```bash
./vendor/bin/phpunit
```

### Step 3: Adopt New Features (Optional)

Gradually adopt v4 features where they add value:

1. **High-value, low-effort:**
   - Add `healthCheck()` to monitoring
   - Enable `Logger::setDebugMode()` in development
   - Use `getLastCommand()` for debugging

2. **Medium effort:**
   - Replace manual retry loops with `RetryHandler`
   - Use `FilterBuilder` for complex filters
   - Add `dryRun()` for preview functionality

3. **Review error handling:**
   - Use `isRetryable()` to decide retry strategy
   - Log `getContext()` for better debugging

---

## FAQ

### Q: Will my v3 code break?

**No.** v4 is backward compatible. All public methods have the same signatures.

### Q: Do I need to change my exception handling?

**No.** Exceptions work the same way. The new methods (`isRetryable()`, `getContext()`) are additive.

### Q: What about performance?

**Similar or better.** The refactoring improved code organization without adding overhead. The new retry mechanism can actually improve reliability for flaky connections.

### Q: Can I use v3 and v4 features together?

**Yes.** You can mix old-style flags with new features:

```php
// Old-style flags still work
$rclone->copy($src, $dest, ['checksum' => true]);

// Combined with new features
$rclone
    ->retry(3)
    ->dryRun(true)
    ->copy($src, $dest, ['checksum' => true]);
```

### Q: What happened to ncdu()?

The `ncdu` command was removed because it's an interactive TUI that cannot be used programmatically. Use `size()` for disk usage or `ls()` with recursive flag for file listings.

---

## Getting Help

- **Issues:** [github.com/verseles/flyclone/issues](https://github.com/verseles/flyclone/issues)
- **Documentation:** [README.md](README.md)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
