# Verseles\Flyclone
PHP wrapper for [rclone](https://rclone.org/)

[![PHPUnit](https://img.shields.io/github/actions/workflow/status/verseles/flyclone/phpunit.yml?style=for-the-badge&label=PHPUnit)](https://github.com/verseles/flyclone/actions)

Flyclone provides an intuitive, object-oriented interface for interacting with `rclone`, the powerful command-line program for managing files on cloud storage.

## Key Features
*   **Broad Provider Support**: Works with numerous storage backends supported by rclone (see below).
*   **Fluent API**: Simplifies rclone command execution.
*   **Progress Reporting**: Built-in support for tracking transfer progress.
*   **Process Management**: Handles rclone process execution, timeouts, and errors.
*   **Easy Configuration**: Configure providers and rclone flags directly in PHP.

## Supported Providers
Flyclone supports a wide array of rclone providers, including:
*   Local filesystem ([local](https://rclone.org/local/))
*   Amazon S3 & S3-compatible (e.g., MinIO) ([s3](https://rclone.org/s3/))
*   SFTP ([sftp](https://rclone.org/sftp/))
*   FTP ([ftp](https://rclone.org/ftp/))
*   Dropbox ([dropbox](https://rclone.org/dropbox/))
*   Google Drive ([drive](https://rclone.org/drive/))
*   Mega ([mega](https://rclone.org/mega/))
*   Backblaze B2 ([b2](https://rclone.org/b2/))
*   ...and [many others](https://rclone.org/overview/#features) supported by rclone. New providers can often be used by leveraging the generic `Provider` class or by adding specific classes via PR.

![](https://img.shields.io/badge/php-777bb4?style=for-the-badge&logo=php&logoColor=white)
![](http://img.shields.io/badge/-phpstorm-7256fe?style=for-the-badge&logo=phpstorm&logoColor=white)
![](https://img.shields.io/badge/composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![](https://img.shields.io/badge/Docker-2CA5E0?style=for-the-badge&logo=docker&logoColor=white)
![](https://img.shields.io/badge/GIT-E44C30?style=for-the-badge&logo=git&logoColor=white)

## Installation

```shell script
composer require verseles/flyclone
```
Requires PHP >= 8.4.

## Usage

### Configuration Basics

**1. Provider Setup:**
Each storage backend (local disk, S3 bucket, SFTP server, etc.) is represented by a `Provider` class. You'll instantiate a provider with a unique nickname and its rclone configuration parameters.

**2. Obscuring Secrets:**
Rclone (and therefore Flyclone) often requires sensitive information like API keys or passwords. It's highly recommended to use rclone's `obscure` feature for passwords. Flyclone provides a helper for this:
```php
use Verseles\Flyclone\Rclone;

$obscuredPassword = Rclone::obscure('your-sftp-password');
// This $obscuredPassword can then be used in the provider configuration.
```

**3. Rclone Binary Path (Optional):**
Flyclone attempts to locate the `rclone` binary automatically. If it's installed in a non-standard location, you can specify the path:
```php
Rclone::setBIN('/path/to/your/rclone_binary');
```

### Instantiating Rclone
You create an `Rclone` instance with one or two providers:
*   **One Provider**: For operations on a single remote (e.g., listing files, creating directories, moving files within the same remote).
*   **Two Providers**: For operations between two different remotes (e.g., copying from local to S3, syncing SFTP to Dropbox).

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;

// Operations on a single local disk
$localDisk = new LocalProvider('myLocalDisk');
$rcloneLocal = new Rclone($localDisk);

// Operations between local disk and an S3 bucket
$s3Bucket = new S3Provider('myS3Remote', [
    'region' => 'us-east-1',
    'access_key_id' => 'YOUR_ACCESS_KEY',
    'secret_access_key' => 'YOUR_SECRET_KEY',
    // 'endpoint' => 'https://your.minio.server' // For S3-compatible like MinIO
]);
$rcloneS3Transfer = new Rclone($localDisk, $s3Bucket); // $localDisk is source, $s3Bucket is destination
```

### Common Operations

<details open><summary>List files (<code>ls</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;

// Example 1: List local files
$local = new LocalProvider('homeDir');
$rclone = new Rclone($local);
$files = $rclone->ls('/home/user/documents'); // Path on the 'homeDir' remote
/*
$files will be an array of objects, e.g.:
[
    (object) [
        "Path" => "report.docx",
        "Name" => "report.docx",
        "Size" => 12345,
        "MimeType" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "ModTime" => 1678886400, // Unix timestamp
        "IsDir" => false
    ],
    (object) [
        "Path" => "archive",
        "Name" => "archive",
        "Size" => -1, // Typically -1 for directories with rclone lsjson
        "MimeType" => "inode/directory",
        "ModTime" => 1678886500,
        "IsDir" => true
    ]
]
*/
var_dump($files);

// Example 2: List files from an S3 bucket
$s3 = new S3Provider('myS3', [ /* S3 config */ ]);
$rcloneS3 = new Rclone($s3);
$s3Files = $rcloneS3->ls('my-bucket-name/path/to/folder');
var_dump($s3Files);
```
</details>

<details><summary>Create a directory (<code>mkdir</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\SFtpProvider;

$sftp = new SFtpProvider('mySFTP', [
    'host' => 'sftp.example.com',
    'user' => 'user',
    'pass' => Rclone::obscure('password')
]);
$rclone = new Rclone($sftp);

$rclone->mkdir('/remote/path/new_directory'); // Creates 'new_directory' on SFTP server
```
</details>

<details><summary>Copy files/directories (<code>copy</code>, <code>copyto</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;

$local = new LocalProvider('myDisk');
$s3 = new S3Provider('myS3', [ /* S3 config */ ]);
$rclone = new Rclone($local, $s3); // local is source, S3 is destination

// Copy a local directory to S3
// Copies contents of /local/data to s3://my-bucket/backups/data
$rclone->copy('/local/data', 'my-bucket/backups/data');

// Copy a single local file to S3 with a specific name
// Copies /local/file.txt to s3://my-bucket/target/renamed.txt
$rclone->copyto('/local/file.txt', 'my-bucket/target/renamed.txt');
```
</details>

<details><summary>Move files/directories (<code>move</code>, <code>moveto</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;

$localDisk = new LocalProvider('myDisk');
$rclone = new Rclone($localDisk); // Operations on the same local disk

// Move a file to another location on the same disk (effectively renaming)
$rclone->moveto('/old/path/file.txt', '/new/path/renamed_file.txt');

// To move between different remotes:
$sftp = new SFtpProvider('mySFTP', [ /* config */ ]);
$rcloneTransfer = new Rclone($localDisk, $sftp); // Local to SFTP
$rcloneTransfer->move('/local/source_folder', '/remote_sftp/destination_folder');
```
</details>

<details><summary>Sync directories (<code>sync</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\SFtpProvider;

$local = new LocalProvider('myDocs');
$sftpBackup = new SFtpProvider('sftpBackup', [ /* config */ ]);
$rclone = new Rclone($local, $sftpBackup); // Sync from local to SFTP

// Make SFTP /backup/documents identical to local /user/documents
// Only transfers changed files, deletes files on SFTP not present locally.
$rclone->sync('/user/documents', '/backup/documents');
```
</details>

<details><summary>Delete files/directories (<code>delete</code>, <code>deletefile</code>, <code>purge</code>, <code>rmdir</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\S3Provider;

$s3 = new S3Provider('myS3', [ /* config */ ]);
$rclone = new Rclone($s3);

// Delete a single file
$rclone->deletefile('my-bucket/path/to/file.txt');

// Delete all *.log files in a directory (respects filters)
$rclone->delete('my-bucket/logs/', ['include' => '*.log']);

// Remove an empty directory
$rclone->rmdir('my-bucket/empty_folder');

// Remove a directory and all its contents (does NOT respect filters)
$rclone->purge('my-bucket/old_stuff_to_delete_completely');
```
</details>

<details><summary>Check existence (<code>is_file</code>, <code>is_dir</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;

$local = new LocalProvider('myDisk');
$rclone = new Rclone($local);

$fileExists = $rclone->is_file('/path/to/some/file.txt');
if ($fileExists->exists) {
    echo "File exists. Size: " . $fileExists->details->Size;
}

$dirExists = $rclone->is_dir('/path/to/some/directory');
if ($dirExists->exists) {
    echo "Directory exists.";
}
/*
 $fileExists / $dirExists object structure:
 (object) [
     'exists' => true, // or false
     'details' => (object) [...], // rclone lsjson item details if exists, or empty array []
     'error' => '' // or Exception object if ls failed
 ]
*/
```
</details>

<details><summary>Read file content (<code>cat</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;

$local = new LocalProvider('myDisk');
$rclone = new Rclone($local);

$content = $rclone->cat('/path/to/config.ini');
echo $content;
```
</details>

<details><summary>Write content to a file (<code>rcat</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\SFtpProvider;

$sftp = new SFtpProvider('mySFTP', [ /* config */ ]);
$rclone = new Rclone($sftp);

$newContent = "Hello from Flyclone!";
$rclone->rcat('/remote/path/newfile.txt', $newContent);
```
</details>

<details><summary>Get size of files/directories (<code>size</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\S3Provider;

$s3 = new S3Provider('myS3', [ /* config */ ]);
$rclone = new Rclone($s3);

$sizeInfo = $rclone->size('my-bucket/some_folder');
/*
$sizeInfo will be an object, e.g.:
(object) [
    "count" => 150,
    "bytes" => 1073741824 // 1 GiB
]
*/
echo "Total files: {$sizeInfo->count}, Total bytes: {$sizeInfo->bytes}";
```
</details>

<details><summary>Upload a local file (<code>upload_file</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\S3Provider;

$s3 = new S3Provider('myS3', [ /* S3 config */ ]);
$rclone = new Rclone($s3); // $s3 is the destination for uploads

// Uploads /tmp/local_file.zip to s3://my-bucket/uploads/local_file.zip
// The local file /tmp/local_file.zip is removed after successful upload (uses rclone moveto).
$rclone->upload_file('/tmp/local_file.zip', 'my-bucket/uploads/local_file.zip');
```
</details>

<details><summary>Download a remote file (<code>download_to_local</code>)</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\SFtpProvider;

$sftp = new SFtpProvider('mySFTP', [ /* config */ ]);
$rclone = new Rclone($sftp); // $sftp is the source for downloads

// Download from SFTP to a specific local path
$localPath = $rclone->download_to_local('/remote/path/on_sftp/document.pdf', '/home/user/downloads/document.pdf');
if ($localPath) {
    echo "Downloaded to: " . $localPath;
}

// Download to a temporary directory (filename preserved)
$tempPath = $rclone->download_to_local('/remote/path/on_sftp/image.jpg');
if ($tempPath) {
    echo "Downloaded to temporary location: " . $tempPath;
    // Remember to unlink($tempPath) and rmdir(dirname($tempPath)) when done if temporary.
}
```
</details>

<details><summary>Copy with progress reporting</summary>

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\DropboxProvider; // Example with Dropbox

$local = new LocalProvider('myLocal');
$dropbox = new DropboxProvider('myDropbox', [
    'client_id'     => 'YOUR_DROPBOX_CLIENT_ID',
    'client_secret' => 'YOUR_DROPBOX_CLIENT_SECRET',
    'token'         => 'YOUR_DROPBOX_TOKEN', // Get this via rclone config
]);

$rclone = new Rclone($local, $dropbox);

$sourceFile = '/path/to/large_local_file.zip';
$destinationPath = '/dropbox_folder/'; // Directory on Dropbox

$rclone->copy($sourceFile, $destinationPath, [], static function ($type, $buffer) use ($rclone) {
    // $type is \Symfony\Component\Process\Process::OUT or \Symfony\Component\Process\Process::ERR
    // $buffer contains the raw rclone progress line
    if ($type === \Symfony\Component\Process\Process::OUT && !empty(trim($buffer))) {
        $progress = $rclone->getProgress(); // Get structured progress object
        /*
        $progress might look like:
        (object) [
            'raw' => '1.234 GiB / 2.000 GiB, 61%, 12.345 MiB/s, ETA 1m2s (xfr#1/1)',
            'dataSent' => '1.234 GiB',
            'dataTotal' => '2.000 GiB',
            'sent' => 61, // Percentage
            'speed' => '12.345 MiB/s',
            'eta' => '1m2s',
            'xfr' => '1/1' // Files being transferred / total files in this batch
        ]
        */
        printf(
            "\rProgress: %d%% (%s / %s) at %s, ETA: %s, Files: %s",
            $progress->sent,
            $progress->dataSent,
            $progress->dataTotal,
            $progress->speed,
            $progress->eta,
            $progress->xfr
        );
    }
});
echo "\nCopy complete!\n";
```
</details>

## Advanced Usage & Tips

*   **Rclone Documentation**: Always refer to the official [rclone documentation](https://rclone.org/docs/) for detailed information on commands and flags. This library is a wrapper, so understanding rclone itself is beneficial.
*   **Flags**: Any rclone flag (e.g., `--retries`, `--max-depth`) can be passed as the last array argument to most Flyclone methods. Convert flags like `--some-flag value` to `['some-flag' => 'value']` or `--boolean-flag` to `['boolean-flag' => true]`.
    ```php
    $rclone->copy('/src', '/dest', ['retries' => 5, 'max-depth' => 3, 'dry-run' => true]);
    ```
*   **Single Provider Operations**: If you instantiate `Rclone` with only one provider, operations like `copy` or `move` will assume the source and destination are on that same provider (e.g., moving files within the same S3 bucket).
*   **Global Rclone Settings**:
  *   `Rclone::setFlags(['checksum' => true, 'verbose' => true])`: Set global flags for all subsequent rclone commands.
  *   `Rclone::setEnvs(['RCLONE_BUFFER_SIZE' => '64M'])`: Set environment variables for rclone (these are usually prefixed with `RCLONE_` automatically if not already).
  *   `Rclone::setTimeout(300)`: Set the maximum execution time for rclone processes (seconds).
  *   `Rclone::setIdleTimeout(120)`: Set the idle timeout for rclone processes (seconds).
*   **Error Handling**: Flyclone throws specific exceptions based on rclone's exit codes (e.g., `FileNotFoundException`, `DirectoryNotFoundException`, `TemporaryErrorException`). Catch these for robust error management.

## To-do
- [x] ~~Add progress support~~
- [x] ~~Add timeout support~~
- [x] ~~Add more commands~~
- [x] ~~Add tests~~
  - [x] ~~Use docker and docker compose for tests~~
- [ ] Send meta details like file id in some storage system like google drive (e.g. for `lsjson` output).

## Testing
Install Docker and Docker Compose, then run:
```shell
cp .env.example .env # Fill in any necessary credentials if you want to test against real cloud providers
make test-offline    # Runs tests against local, SFTP (Dockerized), S3/MinIO (Dockerized)
# or simply:
make                 # Default goal runs 'test-offline'
```

> There are other test targets in the `makefile` (e.g., `test_dropbox`, `test_gdrive`), but they require you to fill the `.env` file with actual credentials for those services.

## Contribution
> You know the drill: Fork, branch, code, test, PR! Contributions are welcome.

## License
[Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International](LICENSE.md)