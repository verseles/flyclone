<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\GDriveProvider;

class GDriveProviderTest extends AbstractProviderTest
{
    public function setUp(): void
    {
        $left_disk_name = 'gdrive_disk';
        $this->setLeftProviderName($left_disk_name);
        $this->working_directory = '/flyclone';

        self::assertEquals($left_disk_name, $this->getLeftProviderName());
    }

    #[Test]
    final public function instantiate_left_provider(): GDriveProvider
    {
        $left_side = new GDriveProvider($this->getLeftProviderName(), [
            'CLIENT_ID' => $_ENV['GDRIVE_CLIENT_ID'],
            'CLIENT_SECRET' => $_ENV['GDRIVE_CLIENT_SECRET'],
            'TOKEN' => $_ENV['GDRIVE_TOKEN'],
        ]);

        self::assertInstanceOf(get_class($left_side), $left_side);

        return $left_side;
    }
}
