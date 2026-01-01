# Verseles\Flyclone

PHP wrapper for [rclone](https://rclone.org/) - the Swiss army knife of cloud storage.

[![PHPUnit](https://img.shields.io/github/actions/workflow/status/verseles/flyclone/phpunit.yml?style=for-the-badge&label=PHPUnit)](https://github.com/verseles/flyclone/actions)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777bb4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-CC--BY--NC--SA--4.0-green?style=for-the-badge)](LICENSE.md)

Flyclone provides an intuitive, object-oriented interface for interacting with rclone. Transfer files between 70+ cloud providers with progress tracking, detailed statistics, and robust error handling.

## Features

- **70+ Storage Backends** - Local, S3, SFTP, FTP, Dropbox, Google Drive, Mega, B2, and more
- **Fluent API** - Clean, chainable interface for all rclone operations
- **Progress Tracking** - Real-time transfer progress with speed, ETA, and percentage
- **Transfer Statistics** - Detailed stats (bytes, files, speed, errors) after each operation
- **Encryption Support** - Transparent encryption via CryptProvider
- **Union Filesystems** - Merge multiple providers into a single virtual filesystem
- **Type-Safe Errors** - Specific exceptions for each rclone exit code

## Requirements

- PHP >= 8.4
- [rclone](https://rclone.org/install/) binary in PATH

## Installation

```bash
composer require verseles/flyclone
```

## Quick Start

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;

// Single provider - operations on one remote
$local = new LocalProvider('myDisk');
$rclone = new Rclone($local);
$files = $rclone->ls('/path/to/files');

// Two providers - transfer between remotes
$s3 = new S3Provider('myS3', [
    'access_key_id' => 'YOUR_KEY',
    'secret_access_key' => 'YOUR_SECRET',
    'region' => 'us-east-1',
]);
$rclone = new Rclone($local, $s3);
$result = $rclone->copy('/local/data', 'my-bucket/backup');

if ($result->success) {
    echo "Transferred {$result->stats->bytes} bytes at {$result->stats->speed_human}";
}
```

## Supported Providers

| Provider | Class | Notes |
|----------|-------|-------|
| Local filesystem | `LocalProvider` | |
| Amazon S3 / MinIO | `S3Provider` | S3-compatible |
| SFTP | `SFtpProvider` | SSH File Transfer |
| FTP | `FtpProvider` | |
| Dropbox | `DropboxProvider` | |
| Google Drive | `GDriveProvider` | |
| Mega.nz | `MegaProvider` | |
| Backblaze B2 | `B2Provider` | |
| **Encryption** | `CryptProvider` | Wraps any provider |
| **Union** | `UnionProvider` | Merges multiple providers |

> All [70+ rclone backends](https://rclone.org/overview/) can be used via the generic `Provider` class.

## Advanced Features

### Encryption with CryptProvider

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Providers\CryptProvider;

$s3 = new S3Provider('myS3', [/* config */]);
$encrypted = new CryptProvider('encrypted', [
    'password' => Rclone::obscure('my-secret-password'),
    'password2' => Rclone::obscure('my-salt'),
], $s3);

$rclone = new Rclone($encrypted);
$rclone->copy('/local/sensitive-data', '/encrypted-bucket/backup');
// Files are transparently encrypted before upload
```

### Union Filesystem

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Providers\UnionProvider;

$local = new LocalProvider('cache', ['root' => '/tmp/cache']);
$s3 = new S3Provider('archive', [/* config */]);

$union = new UnionProvider('combined', [
    'action_policy' => 'all',
    'create_policy' => 'ff',
], [$local, $s3]);

$rclone = new Rclone($union);
$files = $rclone->ls('/'); // Lists files from both local and S3
```

### Global Configuration

```php
// Set rclone binary path (auto-detected by default)
Rclone::setBIN('/custom/path/to/rclone');

// Set global flags for all operations
Rclone::setFlags(['checksum' => true, 'verbose' => true]);

// Set environment variables
Rclone::setEnvs(['RCLONE_BUFFER_SIZE' => '64M']);

// Set timeouts
Rclone::setTimeout(300);     // Max execution time (seconds)
Rclone::setIdleTimeout(120); // Idle timeout (seconds)

// Obscure passwords
$obscured = Rclone::obscure('plain-password');
```

### Error Handling

```php
use Verseles\Flyclone\Exception\FileNotFoundException;
use Verseles\Flyclone\Exception\DirectoryNotFoundException;
use Verseles\Flyclone\Exception\TemporaryErrorException;

try {
    $rclone->copy($source, $dest);
} catch (FileNotFoundException $e) {
    // File doesn't exist - no retry needed
} catch (DirectoryNotFoundException $e) {
    // Directory doesn't exist
} catch (TemporaryErrorException $e) {
    // Temporary error - retry may succeed
}
```

## Testing

```bash
# Install dependencies
composer install

# Run quick tests (local provider only)
make test

# Run full offline test suite (requires podman-compose)
make test-offline

# Run specific provider tests (requires .env configuration)
make test_dropbox
make test_gdrive
```

## Architecture

Flyclone v4 uses a modular architecture:

| Component | Responsibility |
|-----------|---------------|
| `Rclone` | Main orchestrator, public API |
| `ProcessManager` | Process execution, binary detection, error mapping |
| `CommandBuilder` | Command construction, environment variables |
| `StatsParser` | Transfer statistics parsing |
| `ProgressParser` | Real-time progress parsing |

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass: `make test-offline`
5. Submit a pull request

## Changelog

### v4.0.0 (In Development)
- **Architecture**: Extracted `ProcessManager`, `CommandBuilder`, `StatsParser`, `ProgressParser` from monolithic `Rclone` class
- **Fixed**: `CryptProvider` and `UnionProvider` now fully functional with passing tests
- **Tests**: Added `ConfigurationTest` (13 tests) and `EdgeCasesTest` (13 tests)
- **Infrastructure**: Migrated to `podman-compose`, 97+ tests passing

### v3.x
- Transfer operations return detailed statistics object
- Progress tracking improvements

## License

[Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International](LICENSE.md)