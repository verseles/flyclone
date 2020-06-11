# CloudAtlas\Flyclone
PHP wrapper for rclone

## Installation

```shell script
composer require cloudatlas/flycone
```

## Usage
### List local files
```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;

$left_side = new LocalProvider('mydisk'); // name
$rclone = new Rclone($left_side);

var_dump($rclone->ls('/home/')); // returns array
```
> Our `ls()` method use `lsjson` command, so the default `--max-depth` is `1`
### List FTP files
```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\FtpProvider;

$left_side = new FtpProvider('myserver',[
    'host'=>'222.222.222.222',
    'user'=>'johnivy',
    'pass'=> Rclone::obscure('applesux')
]);

$rclone = new Rclone($left_side);

var_dump($rclone->ls('/public_html')); // returns array
```
### Copy from local disk to FTP
```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;
use CloudAtlas\Flyclone\Providers\FtpProvider;

$left_side = new LocalProvider('mydisk'); // name

$right_side = new FtpProvider('myserver',[
    'host'=>'222.222.222.222',
    'user'=>'JohnIvy',
    'pass'=> Rclone::obscure('4ppl35u*')
]);

$rclone = new Rclone($left_side, $right_side);

$rclone->copy('/home/appleinc/index.html', '/public_html'); // always true, otherwise throws error
```
### Copy from local disk to the same local disk
```php
use CloudAtlas\Flyclone\Rclone;
use CloudAtlas\Flyclone\Providers\LocalProvider;


$left_side = new LocalProvider('mydisk'); // name

$rclone = new Rclone($left_side, $left_side);

$rclone->copy('/home/appleinc/index.html', '/home/www/'); // always true, otherwise throws error
```
## Tips
* Of course, you need known how [rclone works](https://rclone.org/docs).
* Classes Rclone and Providers always support any flag listed at [rclone documentation](https://rclone.org/flags/).
* Be careful, some flags wasn't implemented yet, like `-P` / `--progress`
## WIP
-[ ] Add progress support
-[ ] Add more commands
-[ ] Add tests

## Contribution
> You know how to do that.

## License
MIT - 2020
