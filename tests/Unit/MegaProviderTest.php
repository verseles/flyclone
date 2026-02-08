<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\MegaProvider;
use Verseles\Flyclone\Rclone;

class MegaProviderTest extends AbstractProviderTest
{
    public function setUp(): void
    {
        $left_disk_name = 'mega_disk';
        $this->setLeftProviderName($left_disk_name);
        $this->working_directory = '/flyclone';

        self::assertEquals($left_disk_name, $this->getLeftProviderName());
    }

    #[Test]
    final public function instantiate_left_provider(): MegaProvider
    {
        $left_side = new MegaProvider($this->getLeftProviderName(), [
            'USER' => $_ENV['MEGA_USER'],
            'PASS' => Rclone::obscure($_ENV['MEGA_PASS']),
        ]);

        self::assertInstanceOf(get_class($left_side), $left_side);

        return $left_side;
    }
}
