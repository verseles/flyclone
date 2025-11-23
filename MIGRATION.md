# Migration Guide: v2.x to v3.0.0

This guide helps you migrate your code from Flyclone v2.x to v3.0.0.

## Overview of Breaking Changes

Version 3.0.0 introduces **instance-based configuration** instead of static configuration. This change makes the library thread-safe, more testable, and eliminates global state issues.

### Removed Static Methods

The following static methods have been **removed**:

| Removed Method | Replacement |
|----------------|-------------|
| `Rclone::setTimeout($seconds)` | Constructor parameter or `->withTimeout($seconds)` |
| `Rclone::setIdleTimeout($seconds)` | Constructor parameter or `->withIdleTimeout($seconds)` |
| `Rclone::setFlags($flags)` | Constructor parameter or `->withFlags($flags)` |
| `Rclone::setEnvs($envs)` | Constructor parameter or `->withEnvs($envs)` |
| `Rclone::getTimeout()` | `$rclone->getTimeout()` (instance method) |
| `Rclone::getIdleTimeout()` | `$rclone->getIdleTimeout()` (instance method) |
| `Rclone::getFlags()` | `$rclone->getFlags()` (instance method) |
| `Rclone::getEnvs()` | `$rclone->getEnvs()` (instance method) |

### Methods That Remain Static

These methods are still static and work the same way:

- `Rclone::getBIN()` - Get rclone binary path
- `Rclone::setBIN($path)` - Set rclone binary path
- `Rclone::guessBIN()` - Auto-detect rclone binary
- `Rclone::obscure($secret)` - Obscure passwords for rclone config
- `Rclone::prefix_flags($flags, $prefix)` - Helper for flag transformation
- `Rclone::create($provider)` - **NEW** Factory method for builder pattern

## Migration Examples

### Basic Usage (No Changes Required)

If you were using the simple instantiation pattern, **no changes are needed**:

```php
// v2.x - Still works in v3.0.0
$provider = new S3Provider('my-s3', [...]);
$rclone = new Rclone($provider);
$rclone->ls('/path');

// Two providers - Still works
$rclone = new Rclone($sourceProvider, $destProvider);
$rclone->copy('/source', '/dest');
```

### Setting Timeout

**Before (v2.x):**
```php
Rclone::setTimeout(300);
Rclone::setIdleTimeout(200);

$rclone = new Rclone($provider);
$rclone->sync('/source', '/dest'); // Uses 300s timeout
```

**After (v3.0.0) - Option 1: Constructor:**
```php
$rclone = new Rclone(
    leftSide: $provider,
    timeout: 300,
    idleTimeout: 200
);
$rclone->sync('/source', '/dest');
```

**After (v3.0.0) - Option 2: Builder Pattern:**
```php
$rclone = Rclone::create($provider)
    ->withTimeout(300)
    ->withIdleTimeout(200)
    ->build();
$rclone->sync('/source', '/dest');
```

### Setting Flags

**Before (v2.x):**
```php
Rclone::setFlags([
    'verbose' => true,
    'dry-run' => true,
    'transfers' => 8,
]);

$rclone = new Rclone($provider);
```

**After (v3.0.0) - Option 1: Constructor:**
```php
$rclone = new Rclone(
    leftSide: $provider,
    flags: [
        'verbose' => true,
        'dry-run' => true,
        'transfers' => 8,
    ]
);
```

**After (v3.0.0) - Option 2: Builder Pattern:**
```php
$rclone = Rclone::create($provider)
    ->withFlags([
        'verbose' => true,
        'dry-run' => true,
        'transfers' => 8,
    ])
    ->build();
```

### Setting Environment Variables

**Before (v2.x):**
```php
Rclone::setEnvs([
    'RCLONE_BUFFER_SIZE' => '64M',
    'RCLONE_CHECKERS' => '16',
]);

$rclone = new Rclone($provider);
```

**After (v3.0.0):**
```php
$rclone = Rclone::create($provider)
    ->withEnvs([
        'RCLONE_BUFFER_SIZE' => '64M',
        'RCLONE_CHECKERS' => '16',
    ])
    ->build();
```

### Combined Configuration

**Before (v2.x):**
```php
Rclone::setTimeout(600);
Rclone::setIdleTimeout(300);
Rclone::setFlags(['verbose' => true, 'transfers' => 4]);
Rclone::setEnvs(['RCLONE_BUFFER_SIZE' => '128M']);

$rclone = new Rclone($source, $dest);
$rclone->sync('/data', '/backup');
```

**After (v3.0.0):**
```php
$rclone = Rclone::create($source)
    ->withRightSide($dest)
    ->withTimeout(600)
    ->withIdleTimeout(300)
    ->withFlags(['verbose' => true, 'transfers' => 4])
    ->withEnvs(['RCLONE_BUFFER_SIZE' => '128M'])
    ->build();

$rclone->sync('/data', '/backup');
```

### Multiple Rclone Instances with Different Configs

**Before (v2.x) - Problematic:**
```php
// This was problematic because config was global
Rclone::setTimeout(60);
$fastRclone = new Rclone($provider1);

Rclone::setTimeout(600); // This affected ALL instances!
$slowRclone = new Rclone($provider2);

// $fastRclone now also had 600s timeout - unexpected!
```

**After (v3.0.0) - Each instance is independent:**
```php
$fastRclone = Rclone::create($provider1)
    ->withTimeout(60)
    ->build();

$slowRclone = Rclone::create($provider2)
    ->withTimeout(600)
    ->build();

// Each instance maintains its own configuration
echo $fastRclone->getTimeout();  // 60
echo $slowRclone->getTimeout();  // 600
```

## New Features in v3.0.0

### RcloneBuilder Class

A new `RcloneBuilder` class provides fluent configuration:

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\RcloneBuilder;

// Via factory method (recommended)
$rclone = Rclone::create($provider)
    ->withRightSide($destProvider)
    ->withTimeout(300)
    ->withIdleTimeout(200)
    ->withFlags(['verbose' => true])
    ->withEnvs(['CUSTOM' => 'value'])
    ->build();

// Or instantiate builder directly
$builder = new RcloneBuilder($provider);
$rclone = $builder
    ->withTimeout(300)
    ->build();
```

### Builder Methods

| Method | Description |
|--------|-------------|
| `withRightSide(ProviderInterface $provider)` | Set destination provider |
| `withTimeout(int $seconds)` | Set process timeout (default: 120) |
| `withIdleTimeout(int $seconds)` | Set idle timeout (default: 100) |
| `withFlags(array $flags)` | Add rclone flags (merges with existing) |
| `withEnvs(array $envs)` | Add environment variables (merges with existing) |
| `build()` | Create the configured Rclone instance |

### Constructor Signature

The `Rclone` constructor now accepts all configuration:

```php
public function __construct(
    ProviderInterface $leftSide,
    ?ProviderInterface $rightSide = null,
    int $timeout = 120,
    int $idleTimeout = 100,
    array $flags = [],
    array $envs = []
)
```

## ProviderInterface

Version 3.0.0 introduces `ProviderInterface` for better type safety. All providers implement this interface:

```php
use Verseles\Flyclone\Providers\ProviderInterface;

function transferFiles(ProviderInterface $source, ProviderInterface $dest): void
{
    $rclone = new Rclone($source, $dest);
    // ...
}
```

### Interface Methods

```php
interface ProviderInterface
{
    public function provider(): string;
    public function name(): string;
    public function flags(): array;
    public function backend(?string $path = null): string;
    public function isDirAgnostic(): bool;
    public function isBucketAsDir(): bool;
    public function isListsAsTree(): bool;
}
```

## CryptProvider and UnionProvider Improvements

These providers now properly use `ProviderInterface` for type checking:

### CryptProvider

```php
use Verseles\Flyclone\Providers\CryptProvider;
use Verseles\Flyclone\Providers\S3Provider;

$s3 = new S3Provider('my-s3', [...]);

$encrypted = new CryptProvider('encrypted-s3', [
    'remote' => $s3,  // Must be ProviderInterface
    'password' => Rclone::obscure('secret'),
    'password2' => Rclone::obscure('salt'),
]);

// Access the wrapped provider
$original = $encrypted->getWrappedProvider(); // Returns S3Provider
```

### UnionProvider

```php
use Verseles\Flyclone\Providers\UnionProvider;
use Verseles\Flyclone\Providers\LocalProvider;

$local1 = new LocalProvider('disk1');
$local2 = new LocalProvider('disk2');

$union = new UnionProvider('combined', [
    'upstream_providers' => [$local1, $local2],  // Must be ProviderInterface[]
    'create_policy' => 'epmfs',
]);

// Access upstream providers
$upstreams = $union->getUpstreamProviders(); // Returns array of providers
```

## Search and Replace Patterns

Use these patterns to find code that needs updating:

```bash
# Find static setTimeout calls
grep -r "Rclone::setTimeout" --include="*.php"

# Find static setIdleTimeout calls
grep -r "Rclone::setIdleTimeout" --include="*.php"

# Find static setFlags calls
grep -r "Rclone::setFlags" --include="*.php"

# Find static setEnvs calls
grep -r "Rclone::setEnvs" --include="*.php"

# Find static getter calls (now instance methods)
grep -r "Rclone::getTimeout\|Rclone::getIdleTimeout\|Rclone::getFlags\|Rclone::getEnvs" --include="*.php"
```

## FAQ

### Q: Do I need to update all my code?

Only if you used the static configuration methods (`setTimeout`, `setFlags`, etc.). Basic usage with `new Rclone($provider)` works unchanged.

### Q: Can I still change configuration after creating an instance?

No. Configuration is set at construction time and is immutable. Create a new instance if you need different settings:

```php
// Create instances with different configs as needed
$verboseRclone = Rclone::create($provider)->withFlags(['verbose' => true])->build();
$quietRclone = Rclone::create($provider)->build();
```

### Q: What about setInput()?

`setInput()` is still an instance method and works the same way. It's used internally for `rcat` operations:

```php
$rclone->setInput('file contents');
// Or use rcat directly
$rclone->rcat('/path/file.txt', 'file contents');
```

### Q: Is the library backwards compatible?

Partially. If you only used `new Rclone($provider)` without static config methods, your code works unchanged. If you used `Rclone::setTimeout()` or similar static methods, you need to update those calls.

## Need Help?

If you encounter issues during migration, please open an issue at:
https://github.com/verseles/flyclone/issues
