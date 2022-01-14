# cloudatlas\flyclone
PHP wrapper for [rclone](https://rclone.org/)

supports [local](https://rclone.org/local/) disk, [dropbox](https://rclone.org/dropbox/), [ftp](https://rclone.org/ftp/), [sftp](https://rclone.org/sftp/), [google drive](https://rclone.org/sftp/), [mega](https://rclone.org/mega/), [s3](https://rclone.org/s3/) ([any compatible](https://rclone.org/overview/)) and others can be easily added via pr.

progress support.

![](https://img.shields.io/badge/php-777bb4?style=for-the-badge&logo=php&logoColor=white)
![](http://img.shields.io/badge/-phpstorm-7256fe?style=for-the-badge&logo=phpstorm&logoColor=white)
![](https://img.shields.io/badge/composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![](https://img.shields.io/badge/Docker-2CA5E0?style=for-the-badge&logo=docker&logoColor=white)
![](https://img.shields.io/badge/GIT-E44C30?style=for-the-badge&logo=git&logoColor=white)

## installation

```shell script
composer require cloudatlas/flyclone
```

## usage
<details open><summary>list local files</summary>

```php
use cloudatlas\flyclone\rclone;
use cloudatlas\flyclone\providers\localprovider;

$left_side = new localprovider('mydisk'); // nickname
$rclone = new rclone($left_side);

var_dump($rclone->ls('/home/')); // returns array
```
</details>
<details><summary>list files from mega server</summary>

```php
use cloudatlas\flyclone\rclone;
use cloudatlas\flyclone\providers\megaprovider;

$left_side = new megaprovider('myserver',[
    'user'=>'johnivy@pear.com',
    'pass'=> rclone::obscure('applesux')
]);

$rclone = new rclone($left_side);

var_dump($rclone->ls('/docs')); // returns array
```
</details>
<details><summary>copy from local disk to mega</summary>

```php
use cloudatlas\flyclone\rclone;
use cloudatlas\flyclone\providers\localprovider;
use cloudatlas\flyclone\providers\megaprovider;

$left_side = new localprovider('mydisk'); // name

$right_side = new megaprovider('myremote',[
    'user'=>'your@email.com',
    'pass'=> rclone::obscure('4ppl35u*')
]);

$rclone = new rclone($left_side, $right_side);

$rclone->copy('/home/appleinc/index.html', '/docs'); // always true, otherwise throws error
```
</details>
<details><summary>move from local disk to the same local disk</summary>

```php
use cloudatlas\flyclone\rclone;
use cloudatlas\flyclone\providers\localprovider;

$samedisk = new localprovider('mydisk'); // name

$rclone = new rclone($samedisk);

$rclone->copy('/home/appleinc/index.html', '/home/www/'); // always true, otherwise throws error
```
</details>
<details><summary>copy to dropbox with progress every sec</summary>

```php
use cloudatlas\flyclone\rclone;
use cloudatlas\flyclone\providers\localprovider;
use cloudatlas\flyclone\providers\dropboxprovider;

$left_side = new localprovider('mydisk'); // nickname
$right_side = new dropboxprovider('myremote', [
    'client_id'     => 'your_dropbox_client_id',
    'client_secret' => 'your_dropbox_client_secret',
    'token'         => 'your_dropbox_token',
]);

$rclone = new rclone($left_side, $right_side);

$rclone->copy('/home/appleinc/index.html', '/home/www/', [], static function ($type, $buffer) use ($rclone) {
   var_dump($rclone->getprogress());
});
```
</details>

## tips - read before use.
* of course, you need known how [rclone works](https://rclone.org/docs).
* rclone class and providers classes always support any flag listed at [rclone documentation](https://rclone.org/flags/), often as 3rd argument. but
* any flag, parameter or option passed like `--parameter-here`, in this lib is a array like `['parameter-here'='value', 'max-depth' => 3, 'any'=>'1']`
* if you inform only one provider (_'left side'_), in commands like `copy`/`move` we assume _'right side'_ as the same _'left side'_ provider. which means a copying/moving to the same disk.
* we don't have a great doc for now so open a issue always you have a doubt. remember to be descriptful.
## ~~wip~~ to-do
- [x] ~~add progress support~~
- [x] ~~add timeout support~~
- [x] ~~add more commands~~
- [x] ~~add tests~~
  - [x] use docker and docker compose for tests
- [ ] send meta details like file id in some storage system like google drive

## testing
  install docker and docker compose, then run:
```shell
cp .env.example .env
make test
```

> there are others tests (test_all, test_gdrive, etc), but you'll need fill `.env` file properly.

## contribution
> you know how to do that.

## license
[Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International](LICENSE.md)
