# CloudAtlas\Flyclone
PHP wrapper for rclone

Supports [Local](https://rclone.org/local/) disk, [Dropbox](https://rclone.org/dropbox/), [FTP](https://rclone.org/ftp/), [SFTP](https://rclone.org/sftp/), [Google Drive](https://drive.google.com), [MEGA](https://rclone.org/mega/), [S3](https://rclone.org/s3/) (any compatible) and others can be easily added via PR.

Progress support.

## Installation

```shell script
composer require cloudatlas/flyclone
```

## Usage
### List local files
```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;

$left_side = new LocalProvider('mydisk'); // nickname
$rclone = new Rclone($left_side);

var_dump($rclone->ls('/home/')); // returns array
```
### List files from MEGA server

```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\MegaProvider;

$left_side = new MegaProvider('myserver',[
    'user'=>'johnivy@pear.com',
    'pass'=> Rclone::obscure('applesux')
]);

$rclone = new Rclone($left_side);

var_dump($rclone->ls('/docs')); // returns array
```
### Copy from local disk to MEGA

```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;
use CloudAtlas\Flyclone\Providers\MegaProvider;

$left_side = new LocalProvider('mydisk'); // name

$right_side = new MegaProvider('myremote',[
    'user'=>'your@email.com',
    'pass'=> Rclone::obscure('4ppl35u*')
]);

$rclone = new Rclone($left_side, $right_side);

$rclone->copy('/home/appleinc/index.html', '/docs'); // always true, otherwise throws error
```
### Move from local disk to the same local disk
```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;

$samedisk = new LocalProvider('mydisk'); // name

$rclone = new Rclone($samedisk);

$rclone->copy('/home/appleinc/index.html', '/home/www/'); // always true, otherwise throws error
```

### Copy to dropbox with progress every sec

```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;
use CloudAtlas\Flyclone\Providers\DropboxProvider;

$left_side = new LocalProvider('mydisk'); // nickname
$right_side = new DropboxProvider('myremote', [
    'CLIENT_ID'     => 'YOUR_DROPBOX_CLIENT_ID',
    'CLIENT_SECRET' => 'YOUR_DROPBOX_CLIENT_SECRET',
    'TOKEN'         => 'YOUR_DROPBOX_TOKEN',
]);

$rclone = new Rclone($left_side, $right_side);

$rclone->copy('/home/appleinc/index.html', '/home/www/', [], static function ($type, $buffer) use ($rclone) {
   var_dump($rclone->getProgress());
});
```
## Tips - READ BEFORE USE.
* Of course, you need known how [rclone works](https://rclone.org/docs).
* Rclone class and Providers classes always support any flag listed at [rclone documentation](https://rclone.org/flags/), often as 3rd argument. But
* Any flag, parameter or option passed like `--parameter-here`, in this lib is a array like `['parameter-here'='value', 'max-depth' => 3, 'any'=>'1']`
* If you inform only one provider (_'left side'_), in commands like `copy`/`move` we assume _'right side'_ as the same _'left side'_ provider. Which means a copying/moving to the same disk.
* We don't have a great doc for now so open a issue always you have a doubt. Remember to be descriptful.
## ~~WIP~~
- [X] ~~Add progress support~~
- [X] ~~Add timeout support~~
- [X] ~~Add more commands~~
- [X] ~~Add tests~~

## Contribution
> You know how to do that.

## License
MIT - 2021
