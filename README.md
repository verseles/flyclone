# Verseles\Flyclone

PHP 8.4+ wrapper for [rclone](https://rclone.org/), focused on safe, testable file operations across local disks, S3-compatible storage, SFTP, FTP, Google Drive, Dropbox, Mega, B2, and the rest of rclone's backends.

[![PHPUnit](https://img.shields.io/github/actions/workflow/status/verseles/flyclone/phpunit.yml?style=for-the-badge&label=PHPUnit)](https://github.com/verseles/flyclone/actions)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777bb4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-CC--BY--NC--SA--4.0-green?style=for-the-badge)](LICENSE.md)

## Why Flyclone

- Object-oriented providers around rclone remotes.
- Single-remote and two-remote operations through the same `Rclone` class.
- Transfer statistics and optional progress callbacks.
- Typed exceptions for rclone exit codes.
- Retry support with exponential backoff.
- Fluent include/exclude filters.
- Debug logging with secret redaction.
- v5 safety hardening for long-lived workers: instance-scoped options, provider collision detection, safe temporary remotes, private temp directories, and inline SFTP key support.

## Requirements

- PHP >= 8.4
- `ext-json`
- `rclone` binary available in `PATH`, or configured with `Rclone::setBIN()`

## Installation

```bash
composer require verseles/flyclone:^5.0
```

## Quick Start

```php
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Rclone;

$local = new LocalProvider('local_disk');

$s3 = new S3Provider('archive_bucket', [
    'access_key_id' => 'YOUR_KEY',
    'secret_access_key' => 'YOUR_SECRET',
    'region' => 'us-east-1',
]);

$rclone = new Rclone($local, $s3);
$result = $rclone->copy('/var/backups', 'my-bucket/backups');

if ($result->success) {
    echo "Transferred {$result->stats->bytes} bytes at {$result->stats->speed_human}";
}
```

For one-remote operations, pass only one provider:

```php
$files = (new Rclone($local))->ls('/var/backups');
```

## Providers

| Backend | Class | Notes |
| --- | --- | --- |
| Local filesystem | `LocalProvider` | Local paths and temporary helper remotes |
| Amazon S3 / MinIO / R2 | `S3Provider` | S3-compatible flags map to rclone env config |
| SFTP | `SFtpProvider` | Supports password, `key_file`, and v5 inline `key_pem`/`private_key` |
| FTP | `FtpProvider` | FTP/FTPS |
| Google Drive | `GDriveProvider` | OAuth/config-driven rclone backend |
| Dropbox | `DropboxProvider` | OAuth/config-driven rclone backend |
| Mega.nz | `MegaProvider` | Mega backend |
| Backblaze B2 | `B2Provider` | B2 backend |
| Encrypted remote | `CryptProvider` | Wraps another provider |
| Union filesystem | `UnionProvider` | Merges upstream providers |
| Any other rclone backend | `Provider` | Generic provider class |

Provider names are normalized to rclone-safe uppercase remote names. In v5, names that normalize to an empty value are rejected, and conflicting provider environment variables fail fast instead of being silently overwritten.

## Core Operations

```php
$rclone = new Rclone($sourceProvider, $destProvider);

$rclone->copy('/source/dir', '/dest/dir');
$rclone->copyto('/source/file.txt', '/dest/file.txt');
$rclone->move('/source/dir', '/dest/dir');
$rclone->moveto('/source/file.txt', '/dest/file.txt');
$rclone->sync('/source/dir', '/dest/dir');
$rclone->delete('/path/to/delete');
```

Upload and download helpers create unique temporary local remotes internally:

```php
$rclone = new Rclone($s3);

$rclone->upload_file('/tmp/report.pdf', 'bucket/reports/report.pdf');
$download = $rclone->download_to_local('bucket/reports/report.pdf');

echo $download->local_path;
```

## SFTP Private Keys

Prefer inline PEM keys in v5. Flyclone passes the value to rclone as `RCLONE_CONFIG_<REMOTE>_KEY_PEM`; no key file is created by Flyclone.

```php
use Verseles\Flyclone\Providers\SFtpProvider;

$sftp = new SFtpProvider('deploy', [
    'host' => 'sftp.example.com',
    'user' => 'deploy',
    'private_key' => $privateKeyPem,
    'key_use_agent' => false,
]);
```

`private_key` is a convenience alias for `key_pem`. `key_pem` and `key_file` are mutually exclusive.

## Encryption

`CryptProvider` wraps another provider through the required `remote` flag.

```php
use Verseles\Flyclone\Providers\CryptProvider;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Rclone;

$s3 = new S3Provider('raw_archive', [
    'access_key_id' => 'YOUR_KEY',
    'secret_access_key' => 'YOUR_SECRET',
    'region' => 'us-east-1',
]);

$encrypted = new CryptProvider('encrypted_archive', [
    'remote' => $s3,
    'remote_path' => 'encrypted-prefix',
    'password' => Rclone::obscure('my-secret-password'),
    'password2' => Rclone::obscure('my-salt'),
]);

(new Rclone($encrypted))->copy('/local/sensitive-data', '/');
```

## Union Filesystems

`UnionProvider` receives upstream providers through `upstream_providers`.

```php
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Providers\UnionProvider;
use Verseles\Flyclone\Rclone;

$cache = new LocalProvider('cache', ['root' => '/tmp/cache']);
$archive = new S3Provider('archive', [/* config */]);

$union = new UnionProvider('combined', [
    'upstream_providers' => [$cache, $archive],
    'action_policy' => 'all',
    'create_policy' => 'ff',
]);

$files = (new Rclone($union))->ls('/');
```

## Configuration

Static configuration is still available and is captured by new `Rclone` instances at construction time:

```php
Rclone::setBIN('/custom/path/to/rclone');
Rclone::setFlags(['checksum' => true]);
Rclone::setEnvs(['RCLONE_BUFFER_SIZE' => '64M']);
Rclone::setTimeout(300);
Rclone::setIdleTimeout(120);
```

For long-lived workers, prefer instance-scoped options:

```php
$rclone = (new Rclone($source, $dest))
    ->withFlags(['checksum' => true])
    ->withEnvs(['RCLONE_BUFFER_SIZE' => '64M'])
    ->withTimeout(300)
    ->withIdleTimeout(120);
```

## Filtering

```php
use Verseles\Flyclone\FilterBuilder;

$filter = FilterBuilder::create()
    ->extensions(['jpg', 'png', 'gif'])
    ->minSize('100K')
    ->maxSize('50M')
    ->exclude('**/thumbnails/**');

$rclone->withFilter($filter)->copy('/source', '/dest');
```

## Progress, Retry, And Dry Run

```php
use Verseles\Flyclone\RetryHandler;

$handler = RetryHandler::create()
    ->maxAttempts(5)
    ->baseDelay(500)
    ->multiplier(2.0)
    ->maxDelay(30000)
    ->onRetry(fn (int $attempt, Throwable $e) => logger("Retry {$attempt}: {$e->getMessage()}"));

$progress = function (object $progress): void {
    echo $progress->percentage . "%\n";
};

$rclone->withRetry($handler)
    ->dryRun(false)
    ->copy('/source', '/dest', onProgress: $progress);
```

## Error Handling

```php
use Verseles\Flyclone\Exception\DirectoryNotFoundException;
use Verseles\Flyclone\Exception\FileNotFoundException;
use Verseles\Flyclone\Exception\TemporaryErrorException;

try {
    $rclone->copy('/source', '/dest');
} catch (FileNotFoundException|DirectoryNotFoundException $e) {
    // Permanent user/input error.
} catch (TemporaryErrorException $e) {
    if ($e->isRetryable()) {
        // Retry may succeed.
    }

    $context = $e->getContext();
}
```

## Debugging

```php
use Verseles\Flyclone\Logger;

Logger::setDebugMode(true);

$rclone->copy('/source', '/dest');

echo $rclone->getLastCommand();
$envs = $rclone->getLastEnvs(); // Secrets are redacted.
$logs = Logger::getLogs();
```

## Utilities

```php
$remotes = Rclone::listRemotes();
$config = Rclone::configDump();
$md5 = $rclone->md5sum('/path');
$sha1 = $rclone->sha1sum('/path');
$health = $rclone->healthCheck('/');
```

## v5 Breaking Changes

- Provider names that normalize to an empty rclone remote name now throw `InvalidArgumentException`.
- Conflicting provider env vars now throw `LogicException` instead of overwriting values.
- Two providers with the same normalized remote name and different config now fail fast.
- Existing `Rclone` instances no longer observe later changes to static flags/envs/timeouts; use `withFlags()`, `withEnvs()`, `withTimeout()`, and `withIdleTimeout()` for per-instance updates.
- `SFtpProvider` rejects ambiguous `key_pem` plus `key_file` configuration.

See [MIGRATION.md](MIGRATION.md) for migration notes.

## Testing

```bash
composer install

# Fast local/offline checks
composer test-local
make test

# Full offline provider suite, when podman-compose is available
make test-offline

# Static analysis and formatting
composer analyse
composer run-script format-check
```

Provider-specific tests such as `make test_dropbox` and `make test_gdrive` require a configured `.env`.

## Architecture

Flyclone v5 is organized around small components:

| Component | Responsibility |
| --- | --- |
| `Rclone` | Public API and operation orchestration |
| `Provider` and subclasses | rclone remote configuration |
| `CommandBuilder` | Command arguments and environment variables |
| `ProcessManager` | Symfony Process execution and binary detection |
| `StatsParser` | Transfer statistics parsing |
| `ProgressParser` | Real-time progress parsing |
| `RetryHandler` | Retry policy and backoff |
| `FilterBuilder` | Include/exclude filter construction |
| `TemporaryPath` | Private temp directories and unique temporary remote names |
| `SecretsRedactor` | Secret redaction for errors/logs/envs |
| `Logger` | Structured debug logging |

## Changelog

### v5.0.0

- Hardened provider configuration for long-lived workers.
- Added instance-scoped flags, envs, and timeouts.
- Added provider env collision detection.
- Added remote-name collision detection.
- Added safe temporary local provider names.
- Added private temporary download directories.
- Added SFTP inline private key support via `key_pem`/`private_key`.
- Added configuration/security tests to the fast test path.

### v4.x

- Modularized `Rclone` internals into process, command, stats, progress, retry, filter, logger, and redaction components.
- Added typed exception context, health checks, dry-run mode, command introspection, and static utilities.
- Added `CryptProvider` and `UnionProvider` support.

### v3.x

- Added detailed transfer statistics and progress tracking improvements.

## License

[Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International](LICENSE.md)
