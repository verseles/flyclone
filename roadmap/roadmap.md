---
feature: "Flyclone v4"
spec: |
  Major refactoring release focused on code quality, stability, and developer experience. Break monolithic Rclone class into focused components, stabilize experimental providers, improve security and error handling, add comprehensive testing and static analysis. Maintain backward compatibility where possible but prioritize clean architecture.
---

## Task List

### Feature 1: v4.0-alpha: Core Refactoring
Description: Break monolithic Rclone class into focused components and stabilize experimental providers
- [x] 1.01 Extract ProcessManager class - binary detection, execution, timeouts (standalone class ready for integration)
- [x] 1.02 Extract CommandBuilder class - building rclone commands with flags (standalone class ready for integration)
- [x] 1.03 Extract StatsParser class - parsing transfer statistics (standalone class ready for integration)
- [x] 1.04 Extract ProgressParser class - parsing progress output (standalone class ready for integration)
- [x] 1.05 Refactor Rclone class to use extracted components (reduced from 1491 to ~990 lines)
- [x] 1.06 Fix CryptProvider - make tests pass (stored wrapped_provider in dedicated property, fixed test path isolation)
- [x] 1.07 Fix UnionProvider - make tests pass (fixed test path isolation with static paths)
- [x] 1.08 Enable CryptProvider/UnionProvider in makefile test targets (switched to podman-compose)
- [x] 1.09 Add configuration behavior tests - 13 tests covering setFlags, setEnvs, setTimeout, prefix_flags, buildEnvironment
- [x] 1.10 Add edge case tests - 13 tests covering empty files, special chars, hidden files, parser edge cases
- [x] 1.11 Document test dependency pattern - PHPUnit #[Depends] + setUp() requires static paths for shared state
- [x] 1.12 Code review fixes - guessBin() race condition, obscure() validation, formatBytes() edge case, ls() JSON type check

### Feature 2: v4.0-beta: Security & DX
Description: Security hardening, retry mechanism, logging, and developer experience improvements

#### Security Hardening
- [x] 2.01 Add secrets redaction in error messages and debug output (SecretsRedactor class with handleFailure integration)
- [x] 2.02 Add credential validation warnings when plaintext used without obscure() (CredentialWarning exception, looksObscured() check)
- [x] 2.03 Add provider validation in constructor - fail fast on invalid config (validateConfig/checkCredentials in Provider)

#### Error Handling & Debugging
- [x] 2.04 Improve error context - include command, provider, path in exceptions (RcloneException::setContext/getContext)
- [x] 2.05 Add command introspection - get exact rclone command for debugging (getLastCommand/getLastEnvs)
- [x] 2.06 Add structured logging with optional debug mode for commands/responses (Logger class with debug mode)

#### Reliability
- [x] 2.07 Implement retry mechanism with configurable backoff for transient failures (RetryHandler class)
- [x] 2.08 Add healthCheck() method for provider connectivity verification (Rclone::healthCheck)
- [x] 2.09 Improve ProgressParser to handle fragmented output buffers gracefully (lineBuffer + flush)

#### Developer Experience
- [x] 2.10 Add filtering helpers - fluent API for include/exclude patterns (FilterBuilder class)
- [x] 2.11 Add native dry-run mode with result inspection (dryRun()/isDryRun())
- [x] 2.12 Add progress callback support: copy($src, $dest, onProgress: fn) (already existed, documented)
- [x] 2.13 Add per-operation timeout configuration (ProcessManager::run accepts timeout, passed through _run)

### Feature 3: v4.0-rc: Polish & Release
Description: Documentation, static analysis, code style, and release preparation
- [x] 3.01 Integrate PHPStan at level 5 with CI (level max deferred to 3.04)
- [x] 3.02 Add Laravel Pint with CI integration (PSR-12 + custom rules)
- [ ] 3.03 Cover @codeCoverageIgnore blocks with mock infrastructure
- [ ] 3.04 Improve return type hints with proper array docblocks (raise PHPStan to max)
- [x] 3.05 Expose additional rclone commands (listRemotes, configFile, configDump, bisync, ncdu, md5sum, sha1sum)
- [ ] 3.06 Add version-aware stats parsing for different rclone versions
- [ ] 3.07 Generate API documentation (phpDocumentor or similar)
- [ ] 3.08 Add advanced usage examples in docs (retry, filtering, dry-run, batch)
- [ ] 3.09 Create per-provider configuration guides
- [x] 3.10 Add changelog automation with conventional commits (CHANGELOG.md created)
- [x] 3.11 Update README with v4 changes and migration guide (Feature 3 documented)
- [ ] 3.12 Tag and release v4.0.0
