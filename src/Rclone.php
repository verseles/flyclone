<?php


namespace CloudAtlas\Flyclone;


use CloudAtlas\Flyclone\Providers\Provider;
use Symfony\Component\Process\Process;

class Rclone
{

   private static string $BIN = NULL;
   private Provider $left_side;
   private ?Provider $right_side;

   private static int $timeout = 60;
   private static int $idleTimeout = 60;
   private static $input = NULL;
   private static array $reset = [ 'timeout' => 60, 'idleTimeout' => 60, 'input' => NULL ];

   private static function reset()
   : void
   {
      self::$timeout     = self::$reset[ 'timeout' ];
      self::$idleTimeout = self::$reset[ 'idleTimeout' ];
      self::$input       = self::$reset[ 'input' ];
   }


   public static function prefix_flags(array $arr)
   : array
   {
      $newArr = [];
      foreach ($arr as $key => $value) {
         $newArr[ 'RCLONE_' . strtoupper(str_ireplace('-', '_', $key)) ] = $value;
      }

      return $newArr;
   }

   private function allFlags(array $add = [])
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
   : string
   {
      $process = new Process([ self::bin(), 'obscure', $secret ]);
      $process->setTimeout(3);
      $process->mustRun();

      return trim($process->getOutput());
   }

   protected static function simpleRun(string $command, array $flags = [], array $envs = [], callable $onProgress = NULL)
   : string
   {
      $process = new Process([ self::bin(), $command, ...$flags ], NULL, $envs);

      $process->setTimeout(self::$timeout);
      $process->setIdleTimeout(self::$idleTimeout);
      if (isset(self::$input)) {
         $process->setInput(self::$input);
      }

      if ($onProgress) {
         $process->mustRun($onProgress);
      }
      else {
         $process->mustRun();
      }

      self::reset();

      return trim($process->getOutput());
   }

   protected function directRun(string $command, $path = NULL, array $flags = [], callable $onProgress = NULL)
   {
      self::simpleRun($command, [
          $this->left_side->backend($path),
      ], $this->allFlags($flags), $onProgress);

      return TRUE;
   }

   protected function directTwinRun(string $command, $left_path = NULL, $right_path = NULL, array $flags = [], callable $onProgress = NULL)
   {
      self::simpleRun($command, [
          $this->left_side->backend($left_path),
          $this->right_side->backend($right_path),
      ], $this->allFlags($flags), $onProgress);

      return TRUE;
   }

   protected function inputRun(string $command, $input, array $flags = [], array $envs = [], callable $onProgress = NULL)
   {
      $process = new Process([ self::bin(), $command, ...$flags ], NULL, $envs);
      $process->setInput($input);
      $process->mustRun();

      return trim($process->getOutput());
   }

   public static function version($numeric = FALSE)
   : string
   {
      $cmd = self::simpleRun('version');

      preg_match_all('/rclone\sv(.+)/m', $cmd, $version, PREG_SET_ORDER, 0);

      return $numeric ? (float) $version[ 0 ][ 1 ] : $version[ 0 ][ 1 ];
   }

   public static function bin()
   : string
   {
      $in_system = once(static function () {
         $process = new Process([ 'which', 'rclone' ]);
         $process->setTimeout(3);
         $process->run();

         return trim($process->getOutput()) ?: NULL;
      });

      return self::$BIN ?? $in_system ?? '/usr/bin/rclone';
   }

   public function setBIN(string $BIN)
   {
      self::$BIN = $BIN;
   }

   public function input($input)
   : self
   {
      self::$input = $input;

      return $this;
   }

   public function timeout(int $timeout)
   : self
   {
      self::$timeout = $timeout;

      return $this;
   }

   public function idleTimeout($idleTimeout)
   : self
   {
      self::$idleTimeout = $idleTimeout;

      return $this;
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

   public function copy(string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL)
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

   public function setTimeout(int $timeout)
   : self
   {
      $this->timeout = $timeout;

      return $this;
   }
}
