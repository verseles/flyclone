<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Verseles\Flyclone\ProcessManager;

class ProcessManagerTest extends TestCase
{
    public function testGuessBinThrowsExceptionWhenNotFound(): void
    {
        // Use reflection to reset the static property $bin to trigger guessBin
        $reflection = new ReflectionClass(ProcessManager::class);
        $property = $reflection->getProperty('bin');
        $property->setAccessible(true);
        $property->setValue(null, ''); // Set to empty string

        // Mock PATH to an empty directory so rclone isn't found
        putenv('PATH=/empty/path');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rclone binary not found');

        try {
            ProcessManager::guessBin();
        } finally {
            // Restore original PATH
            putenv('PATH=' . getenv('PATH'));
        }
    }
}
