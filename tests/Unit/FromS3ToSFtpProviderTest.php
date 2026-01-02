<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use Verseles\Flyclone\Providers\S3Provider;
use Verseles\Flyclone\Providers\SFtpProvider;
use Verseles\Flyclone\Rclone;

class FromS3ToSFtpProviderTest extends AbstractTwoProvidersTest
{
    public function setUp(): void
    {
        $left_disk_name = 's3_disk';
        $this->leftProviderName = $left_disk_name;
        $this->left_working_directory = 'flyclone/flyclone';  // bucket/folder

        self::assertEquals($left_disk_name, $this->leftProviderName);

        $right_disk_name = 'sftp_disk';
        $this->rightProviderName = $right_disk_name;
        $working_directory = '/upload';
        $this->right_working_directory = $working_directory . '/' . $this->random_string();
        self::assertEquals($right_disk_name, $this->rightProviderName);
    }

    /**
     * @throws ExpectationFailedException
     * @throws Exception
     */
    #[Test]
    final public function instantiate_left_provider(): S3Provider
    {
        $left_side = new S3Provider($this->leftProviderName, [
            'REGION' => $_ENV['S3_REGION'],
            'ENDPOINT' => $_ENV['S3_ENDPOINT'],
            'ACCESS_KEY_ID' => $_ENV['S3_ACCESS_KEY_ID'],
            'SECRET_ACCESS_KEY' => $_ENV['S3_SECRET_ACCESS_KEY'],
        ]);

        self::assertInstanceOf(get_class($left_side), $left_side);

        return $left_side;
    }

    /**
     * @throws ExpectationFailedException
     * @throws Exception
     */
    #[Test]
    final public function instantiate_right_provider(): SFtpProvider
    {
        $right_side = new SFtpProvider($this->rightProviderName, [
            'HOST' => $_ENV['SFTP_HOST'],
            'USER' => $_ENV['SFTP_USER'],
            'PASS' => Rclone::obscure($_ENV['SFTP_PASS']),
            'PORT' => $_ENV['SFTP_PORT'],
        ]);

        self::assertInstanceOf(get_class($right_side), $right_side);

        return $right_side;
    }
}
