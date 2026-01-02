# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [4.0.0] - 2025-01-XX

### Added

#### Feature 1: Core Refactoring (alpha)
- Extracted `ProcessManager` class - binary detection, process execution, error mapping
- Extracted `CommandBuilder` class - rclone command and environment variable construction
- Extracted `StatsParser` class - transfer statistics parsing from rclone output
- Extracted `ProgressParser` class - real-time progress parsing
- Fixed `CryptProvider` - now fully functional with all tests passing
- Fixed `UnionProvider` - now fully functional with all tests passing
- Added `ConfigurationTest` - 13 tests covering configuration behavior
- Added `EdgeCasesTest` - 13 tests covering edge cases and special characters

#### Feature 2: Security & DX (beta)
- Added `SecretsRedactor` - automatic redaction of sensitive data in errors and logs
- Added `RetryHandler` - exponential backoff retry mechanism for transient failures
- Added `FilterBuilder` - fluent API for include/exclude patterns
- Added `Logger` - structured logging with optional debug mode
- Added `CredentialWarning` exception for plaintext credential detection
- Added `healthCheck()` method for provider connectivity verification
- Added `dryRun()` / `isDryRun()` for simulation mode
- Added `withRetry()` / `retry()` for configurable retry behavior
- Added `withFilter()` / `filter()` / `clearFilter()` for filtering
- Added `getLastCommand()` / `getLastEnvs()` for debugging
- Added `isRetryable()` and `getContext()` to exceptions
- Added per-operation timeout configuration
- Added `Feature2Test` - 28 tests for security and DX features

#### Feature 3: Polish & Release (rc)
- Integrated PHPStan at level 5 with CI
- Integrated Laravel Pint (PSR-12) with CI
- Added `bisync()` - bidirectional synchronization
- Added `ncdu()` - disk usage analysis as JSON
- Added `md5sum()` - MD5 checksums for files
- Added `sha1sum()` - SHA1 checksums for files
- Added `listRemotes()` - list configured remotes (static)
- Added `configFile()` - get config file path (static)
- Added `configDump()` - get config as JSON (static)
- Formatted entire codebase with `declare(strict_types=1)`

### Changed
- Rclone class refactored to use extracted components (~1200 lines reduced)
- Migrated from docker-compose to podman-compose
- CI workflow now runs PHPStan and Pint before tests
- All exceptions now support context and retryable checking

### Fixed
- Race condition in `guessBin()` method
- Input validation in `obscure()` method
- Edge case in `formatBytes()` for zero/negative values
- JSON type validation in `ls()` method
- Buffer handling in ProgressParser for fragmented output

### Removed
- Unused `resetProgress()` method
- Unused `OBSCURED_PATTERN` constant

## [3.x] - Previous Versions

- Transfer operations return detailed statistics object
- Progress tracking improvements
- Initial provider implementations

[Unreleased]: https://github.com/verseles/flyclone/compare/v4.0.0...HEAD
[4.0.0]: https://github.com/verseles/flyclone/compare/v3.0.0...v4.0.0
