<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Exception;

/**
 * Warning thrown when credentials appear to be unobscured (plaintext).
 *
 * This is a non-fatal warning that can be caught and logged without
 * stopping execution. It's triggered when sensitive provider configuration
 * values appear to be in plaintext format instead of using Rclone::obscure().
 */
class CredentialWarning extends \Exception
{
    private string $providerName;
    private string $fieldName;

    public function __construct(string $providerName, string $fieldName, string $message = '')
    {
        $this->providerName = $providerName;
        $this->fieldName = $fieldName;

        if ($message === '') {
            $message = sprintf(
                'Credential field "%s" in provider "%s" appears to be in plaintext. ' .
                'Consider using Rclone::obscure() for sensitive values.',
                $fieldName,
                $providerName
            );
        }

        parent::__construct($message);
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }
}
