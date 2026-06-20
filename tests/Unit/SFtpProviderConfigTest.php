<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Verseles\Flyclone\Providers\SFtpProvider;

class SFtpProviderConfigTest extends TestCase
{
    #[Test]
    public function private_key_alias_is_passed_as_key_pem(): void
    {
        $provider = new SFtpProvider('ssh_key', [
            'HOST' => 'example.test',
            'USER' => 'deploy',
            'PRIVATE_KEY' => "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----",
        ]);

        $flags = $provider->flags();

        self::assertSame(
            "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----",
            $flags['RCLONE_CONFIG_SSHKEY_KEY_PEM']
        );
        self::assertArrayNotHasKey('RCLONE_CONFIG_SSHKEY_PRIVATE_KEY', $flags);
        self::assertArrayNotHasKey('RCLONE_CONFIG_SSHKEY_KEY_FILE', $flags);
    }

    #[Test]
    public function key_pem_and_key_file_are_mutually_exclusive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('both key_pem and key_file');

        new SFtpProvider('ssh_key', [
            'HOST' => 'example.test',
            'USER' => 'deploy',
            'KEY_PEM' => 'pem-content',
            'KEY_FILE' => '/tmp/id_rsa',
        ]);
    }

    #[Test]
    public function private_key_and_key_pem_are_mutually_exclusive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private_key alias cannot be combined');

        new SFtpProvider('ssh_key', [
            'HOST' => 'example.test',
            'USER' => 'deploy',
            'private_key' => 'pem-content',
            'key_pem' => 'pem-content',
        ]);
    }

    #[Test]
    public function private_key_and_key_file_are_mutually_exclusive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private_key alias cannot be combined');

        new SFtpProvider('ssh_key', [
            'HOST' => 'example.test',
            'USER' => 'deploy',
            'PRIVATE-KEY' => 'pem-content',
            'KEY_FILE' => '/tmp/id_rsa',
        ]);
    }
}
