# CloudAtlas\Flyclone
PHP wrapper for rclone

## Installation

```shell script
composer require cloudatlas/flyclone
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
use CloudAtlas\Flyclone\Providers\MegaProvider;

$left_side = new MegaProvider('myserver',[
    'host'=>'222.222.222.222',
    'user'=>'johnivy',
    'pass'=> Rclone::obscure('applesux')
]);

$rclone = new Rclone($left_side);

var_dump($rclone->ls('/public_html')); // returns array
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
## Tips - READ BEFORE USE.
* Of course, you need known how [rclone works](https://rclone.org/docs).
* Rclone class and Providers classes always support any flag listed at [rclone documentation](https://rclone.org/flags/), often as 3rd argument. But
* Any flag, parameter or option passed like `--parameter-here`, in this lib is a array like `['parameter-here'='value', 'max-depth' => 3, 'any'=>'1']` 
* Be careful, some flags wasn't implemented yet, like `-P` / `--progress`, others will never be implemented like `--ask-password`.
* We propably will never support default `ls` since we want a clear flow about how things work. You always can change the `--max-depth` flag. _**Our**_ default is `1`, default rclone `ls` behavior is `0`.
* If you inform only one provider (_'left side'_), in commands like `copy`/`move` we assume _'right side'_ as the same _'left side'_ provider. Which means a copying/moving to the same disk.
* We don't have a great doc for now so open a issue always you have a doubt. Remember to be descriptful.
## WIP
- [ ] Add progress support (_work-in-progress_)
- [ ] Add timeout support
- [ ] Add more commands
- [ ] Add tests

## Contribution
> You know how to do that.

## License
MIT - 2020
