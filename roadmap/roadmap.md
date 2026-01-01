---
feature: "Flyclone v4"
spec: |
  Major refactoring release focused on code quality, stability, and developer experience. Break monolithic Rclone class into focused components, stabilize experimental providers, improve security and error handling, add comprehensive testing and static analysis. Maintain backward compatibility where possible but prioritize clean architecture.
---

## Task List

### Feature 1: v4.0-alpha: Core Refactoring
Description: Break monolithic Rclone class into focused components and stabilize experimental providers
- [ ] 1.01 Extract ProcessManager class - binary detection, execution, timeouts
- [ ] 1.02 Extract CommandBuilder class - building rclone commands with flags
- [ ] 1.03 Extract StatsParser class - parsing transfer statistics
- [ ] 1.04 Extract ProgressParser class - parsing progress output
- [ ] 1.05 Refactor Rclone class to use extracted components
- [x] 1.06 Fix CryptProvider - make tests pass (stored wrapped_provider in dedicated property, fixed test path isolation)
- [x] 1.07 Fix UnionProvider - make tests pass (fixed test path isolation with static paths)
- [x] 1.08 Enable CryptProvider/UnionProvider in makefile test targets (switched to podman-compose)
- [ ] 1.09 Add configuration behavior tests (setFlags, setEnvs, setTimeout)
- [ ] 1.10 Add edge case tests (empty files, special chars, large files, invalid binary)
- [ ] 1.11 Reduce test dependency chains - use setup methods for common scenarios

### Feature 2: v4.0-beta: Security & DX
Description: Security hardening, retry mechanism, logging, and developer experience improvements
- [ ] 2.01 Add secrets redaction in error messages and debug output
- [ ] 2.02 Add credential validation warnings when plaintext used without obscure()
- [ ] 2.03 Implement retry mechanism with configurable backoff for transient failures
- [ ] 2.04 Add structured logging with optional debug mode for commands/responses
- [ ] 2.05 Add command introspection - get exact rclone command for debugging
- [ ] 2.06 Improve error context - include command, provider, path in exceptions
- [ ] 2.07 Add healthCheck() method for provider connectivity verification
- [ ] 2.08 Add filtering helpers - fluent API for include/exclude patterns
- [ ] 2.09 Add native dry-run mode with result inspection
- [ ] 2.10 Add progress callback support: copy($src, $dest, onProgress: fn)
- [ ] 2.11 Add per-operation timeout configuration
- [ ] 2.12 Add provider validation in constructor - fail fast on invalid config

### Feature 3: v4.0-rc: Polish & Release
Description: Documentation, static analysis, code style, and release preparation
- [ ] 3.01 Integrate PHPStan at max level with CI
- [ ] 3.02 Add PHP CS Fixer or Pint with CI integration
- [ ] 3.03 Cover @codeCoverageIgnore blocks with mock infrastructure
- [ ] 3.04 Improve return type hints with proper array docblocks
- [ ] 3.05 Expose additional rclone commands (config, listremotes, ncdu, bisync)
- [ ] 3.06 Add version-aware stats parsing for different rclone versions
- [ ] 3.07 Generate API documentation (phpDocumentor or similar)
- [ ] 3.08 Add advanced usage examples in docs (retry, filtering, dry-run, batch)
- [ ] 3.09 Create per-provider configuration guides
- [ ] 3.10 Add changelog automation with conventional commits
- [ ] 3.11 Update README with v4 changes and migration guide
- [ ] 3.12 Tag and release v4.0.0
