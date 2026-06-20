<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Verseles\Flyclone\CommandBuilder;
use Verseles\Flyclone\FilterBuilder;
use Verseles\Flyclone\ProcessManager;
use Verseles\Flyclone\Providers\CryptProvider;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Rclone;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function set_and_get_timeout(): void
    {
        $original = Rclone::getTimeout();

        Rclone::setTimeout(300);
        self::assertEquals(300, Rclone::getTimeout());
        self::assertEquals(300, ProcessManager::getTimeout());

        Rclone::setTimeout($original);
    }

    #[Test]
    public function set_and_get_idle_timeout(): void
    {
        $original = Rclone::getIdleTimeout();

        Rclone::setIdleTimeout(120);
        self::assertEquals(120, Rclone::getIdleTimeout());
        self::assertEquals(120, ProcessManager::getIdleTimeout());

        Rclone::setIdleTimeout($original);
    }

    #[Test]
    public function set_and_get_flags(): void
    {
        $original = Rclone::getFlags();

        Rclone::setFlags(['retries' => 5, 'verbose' => true]);
        $flags = Rclone::getFlags();

        self::assertEquals(5, $flags['retries']);
        self::assertTrue($flags['verbose']);

        Rclone::setFlags($original);
    }

    #[Test]
    public function set_and_get_envs(): void
    {
        $original = Rclone::getEnvs();

        Rclone::setEnvs(['RCLONE_LOG_LEVEL' => 'DEBUG', 'CUSTOM_VAR' => 'value']);
        $envs = Rclone::getEnvs();

        self::assertEquals('DEBUG', $envs['RCLONE_LOG_LEVEL']);
        self::assertEquals('value', $envs['CUSTOM_VAR']);

        Rclone::setEnvs($original);
    }

    #[Test]
    public function set_and_get_input(): void
    {
        $original = Rclone::getInput();

        Rclone::setInput('test input content');
        self::assertEquals('test input content', Rclone::getInput());
        self::assertEquals('test input content', ProcessManager::getInput());

        Rclone::setInput($original);
    }

    #[Test]
    public function flags_boolean_conversion_in_prefix(): void
    {
        $input = ['flag_true' => true, 'flag_false' => false];
        $result = CommandBuilder::prefixFlags($input, 'RCLONE_');

        self::assertEquals('true', $result['RCLONE_FLAG_TRUE']);
        self::assertEquals('false', $result['RCLONE_FLAG_FALSE']);
    }

    #[Test]
    public function envs_boolean_conversion_in_prefix(): void
    {
        $input = ['env_true' => true, 'env_false' => false];
        $result = CommandBuilder::prefixFlags($input, 'RCLONE_');

        self::assertEquals('true', $result['RCLONE_ENV_TRUE']);
        self::assertEquals('false', $result['RCLONE_ENV_FALSE']);
    }

    #[Test]
    public function prefix_flags_transforms_keys(): void
    {
        $input = ['key1' => 'value1', 'key_two' => 'value2'];
        $result = CommandBuilder::prefixFlags($input, 'RCLONE_');

        self::assertArrayHasKey('RCLONE_KEY1', $result);
        self::assertArrayHasKey('RCLONE_KEY_TWO', $result);
        self::assertEquals('value1', $result['RCLONE_KEY1']);
        self::assertEquals('value2', $result['RCLONE_KEY_TWO']);
    }

    #[Test]
    public function prefix_flags_with_config_prefix(): void
    {
        $input = ['access_key_id' => 'AKID123', 'secret' => 'secret123'];
        $result = CommandBuilder::prefixFlags($input, 'RCLONE_CONFIG_MYREMOTE_');

        self::assertArrayHasKey('RCLONE_CONFIG_MYREMOTE_ACCESS_KEY_ID', $result);
        self::assertArrayHasKey('RCLONE_CONFIG_MYREMOTE_SECRET', $result);
    }

    #[Test]
    public function get_and_set_bin(): void
    {
        $original = Rclone::getBIN();

        Rclone::setBIN('/custom/path/rclone');
        self::assertEquals('/custom/path/rclone', Rclone::getBIN());
        self::assertEquals('/custom/path/rclone', ProcessManager::getBin());

        Rclone::setBIN($original);
    }

    #[Test]
    public function guess_bin_finds_rclone(): void
    {
        $bin = Rclone::guessBIN();
        self::assertNotEmpty($bin);
        self::assertStringContainsString('rclone', $bin);
    }

    #[Test]
    public function provider_flags_are_included_in_environment(): void
    {
        $provider = new LocalProvider('test_local');
        $rclone = new Rclone($provider);

        $providerFlags = $provider->flags();

        self::assertArrayHasKey('RCLONE_CONFIG_TESTLOCAL_TYPE', $providerFlags);
        self::assertEquals('local', $providerFlags['RCLONE_CONFIG_TESTLOCAL_TYPE']);
    }

    #[Test]
    public function build_environment_merges_all_sources(): void
    {
        $provider = new LocalProvider('env_test');

        Rclone::setFlags(['global_flag' => 'global_value']);
        Rclone::setEnvs(['CUSTOM_ENV' => 'custom_value']);

        $env = CommandBuilder::buildEnvironment(
            $provider,
            $provider,
            Rclone::getFlags(),
            Rclone::getEnvs(),
            ['operation_flag' => 'op_value']
        );

        self::assertArrayHasKey('RCLONE_CONFIG_ENVTEST_TYPE', $env);
        self::assertArrayHasKey('RCLONE_GLOBAL_FLAG', $env);
        self::assertArrayHasKey('RCLONE_OPERATION_FLAG', $env);

        Rclone::setFlags([]);
        Rclone::setEnvs([]);
    }

    #[Test]
    public function provider_names_must_not_normalize_to_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider name must contain');

        new LocalProvider('---');
    }

    #[Test]
    public function build_environment_rejects_provider_env_collisions_with_different_values(): void
    {
        $left = new LocalProvider('same.name', ['root' => '/tmp/one']);
        $right = new LocalProvider('same_name', ['root' => '/tmp/two']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SAMENAME');

        CommandBuilder::buildEnvironment($left, $right, [], []);
    }

    #[Test]
    public function build_environment_rejects_same_remote_with_disjoint_config(): void
    {
        $left = new LocalProvider('same_remote', ['root' => '/tmp/one']);
        $right = new LocalProvider('same-remote', ['nounc' => true]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SAMEREMOTE');

        CommandBuilder::buildEnvironment($left, $right, [], []);
    }

    #[Test]
    public function prefix_flags_rejects_duplicate_normalized_keys_with_different_values(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('RCLONE_CONFIG_TEST_KEY_PEM');

        CommandBuilder::prefixFlags([
            'key_pem' => 'one',
            'KEY-PEM' => 'two',
        ], 'RCLONE_CONFIG_TEST_');
    }

    #[Test]
    public function prefix_flags_allows_duplicate_normalized_keys_with_same_value(): void
    {
        $flags = CommandBuilder::prefixFlags([
            'key_pem' => 'same',
            'KEY-PEM' => 'same',
        ], 'RCLONE_CONFIG_TEST_');

        self::assertSame('same', $flags['RCLONE_CONFIG_TEST_KEY_PEM']);
    }

    #[Test]
    public function build_environment_allows_duplicate_provider_env_when_values_match(): void
    {
        $left = new LocalProvider('same.name', ['root' => '/tmp/shared']);
        $right = new LocalProvider('same_name', ['root' => '/tmp/shared']);

        $env = CommandBuilder::buildEnvironment($left, $right, [], []);

        self::assertSame('/tmp/shared', $env['RCLONE_CONFIG_SAMENAME_ROOT']);
        self::assertSame('local', $env['RCLONE_CONFIG_SAMENAME_TYPE']);
    }

    #[Test]
    public function compound_provider_flags_reject_wrapped_provider_name_collisions(): void
    {
        $wrapped = new LocalProvider('archive');
        $crypt = new CryptProvider('archive', ['remote' => $wrapped]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ARCHIVE');

        $crypt->flags();
    }

    #[Test]
    public function compound_provider_flags_merge_when_remote_names_are_unique(): void
    {
        $wrapped = new LocalProvider('archive');
        $crypt = new CryptProvider('encrypted', ['remote' => $wrapped]);

        $flags = $crypt->flags();

        self::assertSame('crypt', $flags['RCLONE_CONFIG_ENCRYPTED_TYPE']);
        self::assertSame('local', $flags['RCLONE_CONFIG_ARCHIVE_TYPE']);
        self::assertSame('ARCHIVE:', $flags['RCLONE_CONFIG_ENCRYPTED_REMOTE']);
    }

    #[Test]
    public function related_rclones_inherit_parent_filter(): void
    {
        $parent = (new Rclone(new LocalProvider('parent')))
            ->withFilter(FilterBuilder::create()->include('*.txt'));

        $method = new ReflectionMethod(Rclone::class, 'newRelatedRclone');
        $method->setAccessible(true);

        $child = $method->invoke($parent, new LocalProvider('child'));

        $reflection = new ReflectionClass(Rclone::class);
        $filterProperty = $reflection->getProperty('filter');
        $filterProperty->setAccessible(true);

        $childFilter = $filterProperty->getValue($child);

        self::assertInstanceOf(FilterBuilder::class, $childFilter);
        self::assertNotSame($filterProperty->getValue($parent), $childFilter);
        self::assertSame(['--include', '*.txt'], $childFilter->toArgs());
    }
}
