<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Process;
use Verseles\Flyclone\Rclone;

// Important for Process::OUT
// Explicitly use PHPUnit's Assert for static calls within trait

trait ProgressTrackingTrait
{
    use Helpers; // To use random_string if needed for filenames

    /**
     * Creates a temporary file with a specified size.
     *
     * @param int $sizeInMB The desired size of the file in megabytes.
     * @return string The path to the created temporary file.
     */
    protected function create_large_temp_file(int $sizeInMB = 1): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_progress_test_src_' . $this->random_string();
        // Attempt to create the directory.
        if (! mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
            // @codeCoverageIgnoreStart
            // This is a safeguard; mkdir should throw an error on failure if display_errors is On.
            Assert::fail("Could not create temporary directory for large file: {$tempDir}");
            // @codeCoverageIgnoreEnd
        }
        $filePath = $tempDir . DIRECTORY_SEPARATOR . 'large_file_' . $this->random_string() . '.tmp';
        $fp = fopen($filePath, 'wb');
        // @codeCoverageIgnoreStart
        // fopen can fail under certain system conditions.
        if ($fp === false) {
            Assert::fail("Could not open temporary file for writing: {$filePath}");
        }
        // @codeCoverageIgnoreEnd

        // Write 1MB chunks
        for ($i = 0; $i < $sizeInMB; $i++) {
            fwrite($fp, str_repeat("\0", 1024 * 1024));
        }
        fclose($fp);
        Assert::assertFileExists($filePath);
        Assert::assertEquals($sizeInMB * 1024 * 1024, filesize($filePath));

        return $filePath;
    }

    /**
     * Asserts that rclone progress tracking is working for a given operation.
     *
     * @param Rclone $rclone The Rclone instance configured for the transfer.
     * @param string $operation The rclone operation to test (e.g., 'copy', 'move', 'copyto', 'moveto').
     * @param string $sourcePath The source path for the operation (on the rclone instance's left_side).
     * @param string $destinationPath The destination path for the operation (on the rclone instance's right_side).
     * @param array $flags Additional rclone flags for the operation.
     */
    protected function assert_progress_tracking(
        Rclone $rclone,
        string $operation,
        string $sourcePath,
        string $destinationPath,
        array $flags = []
    ): void {
        $progressCallbackCalled = false;
        $lastProgressData = null;

        // Dynamically call the rclone operation (e.g., $rclone->copy(), $rclone->moveto())
        $rclone->{$operation}($sourcePath, $destinationPath, $flags, static function ($type, $buffer) use ($rclone, &$progressCallbackCalled, &$lastProgressData) {
            // Progress information is expected on STDOUT.
            if ($type === Process::OUT && ! empty(trim($buffer))) {
                $progressCallbackCalled = true;
                $currentProgress = $rclone->getProgress(); // Get the parsed progress object

                // Assertions about the structure and type of progress data
                Assert::assertIsObject($currentProgress, 'Progress data should be an object.');
                Assert::assertObjectHasProperty('raw', $currentProgress, 'Progress object missing "raw" property.');
                Assert::assertObjectHasProperty('dataSent', $currentProgress, 'Progress object missing "dataSent" property.');
                Assert::assertObjectHasProperty('dataTotal', $currentProgress, 'Progress object missing "dataTotal" property.');
                Assert::assertObjectHasProperty('sent', $currentProgress, 'Progress object missing "sent" property.');
                Assert::assertObjectHasProperty('speed', $currentProgress, 'Progress object missing "speed" property.');
                Assert::assertObjectHasProperty('eta', $currentProgress, 'Progress object missing "eta" property.');
                Assert::assertObjectHasProperty('xfr', $currentProgress, 'Progress object missing "xfr" property.');

                Assert::assertIsString($currentProgress->raw);
                Assert::assertIsString($currentProgress->dataSent);
                Assert::assertIsString($currentProgress->dataTotal);
                Assert::assertIsInt($currentProgress->sent);
                Assert::assertIsString($currentProgress->speed);
                Assert::assertIsString($currentProgress->eta);
                Assert::assertIsString($currentProgress->xfr);

                // Check if 'sent' percentage is within a valid range [0, 100].
                Assert::assertGreaterThanOrEqual(0, $currentProgress->sent);
                Assert::assertLessThanOrEqual(100, $currentProgress->sent);

                $lastProgressData = $currentProgress; // Store the latest progress data
            }
        });

        // Assert that the progress callback was actually invoked.
        Assert::assertTrue($progressCallbackCalled, 'Progress callback was not called. Ensure the file is large enough or the transfer takes some time.');
        // Assert that some progress data was captured.
        Assert::assertNotNull($lastProgressData, 'Last progress data should not be null if callback was called.');

        // For a successfully completed transfer, the 'sent' percentage should be 100%.
        if ($lastProgressData) {
            Assert::assertEquals(100, $lastProgressData->sent, 'Final progress should indicate 100% sent for a successful transfer.');
        }
    }

    /**
     * Cleans up a temporary file and its parent directory if the directory was created by `create_large_temp_file`.
     *
     * @param string $filePath Path to the file to clean up.
     */
    protected function cleanup_temp_file_and_dir(string $filePath): void
    {
        $dirPath = dirname($filePath);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        // Only remove the directory if it matches the pattern of our temp dirs and is empty.
        if (str_contains($dirPath, 'flyclone_progress_test_src_') && is_dir($dirPath)) {
            // Check if directory is empty (scandir returns ['.', '..'] for empty dirs).
            if (count(scandir($dirPath)) === 2) {
                rmdir($dirPath);
            }
        }
    }
}
