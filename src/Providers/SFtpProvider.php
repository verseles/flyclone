<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Providers;

use InvalidArgumentException;

class SFtpProvider extends Provider
{
    protected string $provider = 'sftp';

    public function __construct(string $name, array $flags = [])
    {
        $flags = $this->normalizeKeyFlags($flags);

        parent::__construct($this->provider, $name, $flags);
    }

    private function normalizeKeyFlags(array $flags): array
    {
        $keyPemKey = $this->findFlagKey($flags, 'key_pem');
        $keyFileKey = $this->findFlagKey($flags, 'key_file');
        $privateKeyKey = $this->findFlagKey($flags, 'private_key');

        if ($keyPemKey !== null && $keyFileKey !== null) {
            throw new InvalidArgumentException('SFTP provider cannot use both key_pem and key_file.');
        }

        if ($privateKeyKey !== null) {
            if ($keyPemKey !== null || $keyFileKey !== null) {
                throw new InvalidArgumentException('SFTP private_key alias cannot be combined with key_pem or key_file.');
            }

            $flags['key_pem'] = $flags[$privateKeyKey];
            unset($flags[$privateKeyKey]);
        }

        return $flags;
    }

    private function findFlagKey(array $flags, string $normalizedKey): ?string
    {
        foreach (array_keys($flags) as $key) {
            $normalized = strtolower(str_replace('-', '_', (string) $key));

            if ($normalized === $normalizedKey) {
                return (string) $key;
            }
        }

        return null;
    }
}
