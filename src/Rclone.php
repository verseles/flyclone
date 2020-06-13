<?php


namespace CloudAtlas\Flyclone;


use CloudAtlas\Flyclone\Providers\Provider;
use Symfony\Component\Process\Process;

class Rclone
{
   private static $BIN = '/usr/bin/rclone';
   private Provider $left_side;
   private ?Provider $right_side;

   public static function prefix_flags(array $arr)
   : array
   {
      $newArr = [];
      foreach ($arr as $key => $value) {
         $newArr[ 'RCLONE_' . strtoupper($key) ] = $value;
      }

      return $newArr;
   }


   public function allFlags(array $add = [])
   : array
   {
      $forced[ 'RCLONE_LOCAL_ONE_FILE_SYSTEM' ] = TRUE;

      $add = self::prefix_flags($add);

      return array_merge($this->left_side->flags(), $this->right_side->flags(), $add, $forced);

   }

   public function __construct(Provider $left_side, ?Provider $right_side = NULL)
   {
      $this->left_side  = $left_side;
      $this->right_side = $right_side ?? $left_side;
   }


   public static function obscure(string $secret)
   {
      $process = new Process([ self::bin(), 'obscure', $secret ]);
      $process->mustRun();

      return trim($process->getOutput());
   }

   protected static function simpleRun(string $command, array $flags = [], array $envs = [])
   {
      $process = new Process([ self::bin(), $command, ...$flags ], NULL, $envs);
      $process->mustRun();

      return trim($process->getOutput());
   }

   protected function directRun(string $command, $path = NULL, array $flags = [])
   {
      self::simpleRun($command, [
          $this->left_side->backend($path),
      ], $this->allFlags($flags));

      return TRUE;
   }

   protected function directTwinRun(string $command, $left_path = NULL, $right_path = NULL, array $flags = [])
   {
      self::simpleRun($command, [
          $this->left_side->backend($left_path),
          $this->right_side->backend($right_path),
      ], $this->allFlags($flags));

      return TRUE;
   }

   protected function inputRun(string $command, $input, array $flags = [], array $envs = [])
   {
      $process = new Process([ self::bin(), $command, ...$flags ], NULL, $envs);
      $process->setInput($input);
      $process->mustRun();

      return trim($process->getOutput());
   }

   public static function version()
   : string
   {
      $version = self::simpleRun('version');

      preg_match_all('/rclone\sv(.+)/m', $version, $semver, PREG_SET_ORDER, 0);

      return $semver[ 0 ][ 1 ];
   }

   public static function bin(string $set = NULL)
   : string
   {
      if (isset($set)) {
         self::$BIN = $set;
      }

      return self::$BIN;
   }

   public function ls(string $path = NULL, array $flags = [])
   {
      $result = self::simpleRun('lsjson', [
          $this->left_side->backend($path),
      ], $this->allFlags($flags));

      return json_decode($result);
   }

   public function mkdir(string $path = NULL, array $flags = [])
   : bool
   {
      return $this->directRun('mkdir', $path, $flags);
   }

   public function rmdir(string $path = NULL, array $flags = [])
   : bool
   {
      return $this->directRun('rmdir', $path, $flags);
   }

   public function purge(string $path = NULL, array $flags = [])
   : bool
   {
      return $this->directRun('purge', $path, $flags);
   }

   public function delete(string $path = NULL, array $flags = [])
   : bool
   {
      return $this->directRun('delete', $path, $flags);
   }

   public function size(string $path = NULL, array $flags = [])
   {
      $result = self::simpleRun('size', [
          $this->left_side->backend($path),
          '--json',
      ], $this->allFlags($flags));

      return json_decode($result);
   }

   public function cat(string $path = NULL, array $flags = [])
   {
      $result = $this->simpleRun('cat', [
          $this->left_side->backend($path),
      ], $this->allFlags($flags));

      return $result;
   }


   public function rcat(string $path = NULL, array $flags = [])
   {
      $result = $this->inputRun('rcat', [
          $this->left_side->backend($path),
      ], $this->allFlags($flags));

      return $result;
   }

   public function copy(string $source_path, string $dest_path, array $flags = [])
   : bool
   {
      return $this->directTwinRun('copy', $source_path, $dest_path, $flags);
   }

   public function move(string $source_path, string $dest_path, array $flags = [])
   : bool
   {
      return $this->directTwinRun('move', $source_path, $dest_path, $flags);
   }

   public function sync(string $source_path, string $dest_path, array $flags = [])
   : bool
   {
      return $this->directTwinRun('sync', $source_path, $dest_path, $flags);
   }

   public function check(string $source_path, string $dest_path, array $flags = [])
   : bool
   {
      return $this->directTwinRun('check', $source_path, $dest_path, $flags);
   }
}
