<?php


namespace CloudAtlas\Flyclone;


use CloudAtlas\Flyclone\Exception\WriteOperationFailedException;
use CloudAtlas\Flyclone\Providers\LocalProvider;
use CloudAtlas\Flyclone\Providers\Provider;
use Symfony\Component\Process\Process;

class Rclone
{

   private static $BIN = NULL;
   private Provider $left_side;
   private ?Provider $right_side;

   private static int $timeout = 60;
   private static int $idleTimeout = 60;
   private static array $flags = [];
   private static array $envs = [];
   private static $input = NULL;
   private static array $reset = [
       'timeout'     => 60,
       'idleTimeout' => 60,
       'flags'       => [],
       'envs'        => [],
       'input'       => NULL,
   ];

   public function __construct(Provider $left_side, ?Provider $right_side = NULL)
   {
      $this->left_side  = $left_side;
      $this->right_side = $right_side ?? $left_side;
   }

   private static function reset()
   : void
   {
      self::$timeout     = self::$reset[ 'timeout' ];
      self::$idleTimeout = self::$reset[ 'idleTimeout' ];
      self::$flags       = self::$reset[ 'flags' ];
      self::$envs        = self::$reset[ 'envs' ];
      self::$input       = self::$reset[ 'input' ];
   }


   public static function prefix_flags(array $arr, string $prefix = 'RCLONE_')
   : array
   {
      $newArr  = [];
      $replace = [ '/^--/m' => '', '/-/m' => '_', ];
      foreach ($arr as $key => $value) {
         $key = preg_replace(array_keys($replace), array_values($replace), $key);
         $key = strtoupper($key);

         $newArr[ $prefix . $key ] = $value;
      }

      return $newArr;
   }

   private function allFlags(array $add = [])
   : array
   {
      return array_merge($this->left_side->flags(), $this->right_side->flags(), self::$flags, $add);
   }

   private function allEnvs(array $add = [])
   : array
   {
      $forced[ 'RCLONE_LOCAL_ONE_FILE_SYSTEM' ] = TRUE;

      $fluent = self::prefix_flags(self::$envs);
      $add    = self::prefix_flags($add);

      return array_merge($this->allFlags(), $fluent, $add, $forced);

   }


   public static function obscure(string $secret)
   : string
   {
      $process = new Process([ self::getBIN(), 'obscure', $secret ]);
      $process->setTimeout(3);
      $process->mustRun();

      return trim($process->getOutput());
   }

   private static function simpleRun(string $command, array $flags = [], array $envs = [], callable $onProgress = NULL)
   : string
   {
      $process = new Process([ self::getBIN(), $command, ...$flags ], NULL, $envs);

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
   : bool
   {
      self::simpleRun($command, [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags), $onProgress);

      return TRUE;
   }

   protected function directTwinRun(string $command, $left_path = NULL, $right_path = NULL, array $flags = [], callable $onProgress = NULL)
   {
      self::simpleRun($command, [
          $this->left_side->backend($left_path),
          $this->right_side->backend($right_path),
      ], $this->allEnvs($flags), $onProgress);

      return TRUE;
   }

   private function inputRun(string $command, $input, array $flags = [], array $envs = [], callable $onProgress = NULL)
   : bool
   {
      $this->input($input);

      return (bool) self::simpleRun($command, $flags, $envs, $onProgress);
   }

   public static function version(bool $numeric = FALSE)
   : string
   {
      $cmd = self::simpleRun('version');

      preg_match_all('/rclone\sv(.+)/m', $cmd, $version, PREG_SET_ORDER, 0);

      return $numeric ? (float) $version[ 0 ][ 1 ] : $version[ 0 ][ 1 ];
   }

   public static function getBIN()
   : string
   {
      return self::$BIN ?? self::guessBIN();
   }

   public static function setBIN(string $BIN)
   {
      self::$BIN = (string) $BIN;
   }

   public static function guessBIN()
   {
      $BIN = once(static function () {
             $process = new Process([ 'where', 'rclone' ]);
             $process->setTimeout(3);
             $process->run();

             $tryWindows = trim($process->getOutput()) ?: NULL;

             $process = new Process([ 'which', 'rclone' ]);
             $process->setTimeout(3);
             $process->run();

             $tryUnix = trim($process->getOutput()) ?: NULL;

             return $tryWindows ?? $tryUnix ?? NULL;
          }) ?? '/usr/bin/rclone';

      self::setBIN($BIN);

      return self::getBIN();
   }

   public function input($input)
   {
      self::$input = $input;
   }

   public function setIdleTimeout($idleTimeout)
   : void
   {
      self::$idleTimeout = $idleTimeout;
   }


   public function ls(string $path, array $flags = [])
   : array
   {
      $result = self::simpleRun('lsjson', [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags));

      return json_decode($result);
   }

   public function touch(string $path, array $flags = [])
   : bool
   {
      return $this->directRun('touch', $path, $flags);
   }

   public function mkdir(string $path, array $flags = [])
   : bool
   {
      return $this->directRun('mkdir', $path, $flags);
   }

   public function rmdir(string $path, array $flags = [])
   : bool
   {
      return $this->directRun('rmdir', $path, $flags);
   }

   public function purge(string $path, array $flags = [])
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
      ], $this->allEnvs($flags));

      return json_decode($result);
   }

   public function cat(string $path, array $flags = [])
   {
      return self::simpleRun('cat', [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags));
   }


   public function rcat(string $path, $input, array $flags = [])
   {
      return $this->inputRun('rcat', $input, [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags));
   }

   public function write_file(string $path, $input, array $flags = [])
   : bool
   {
      $temp_filepath = tempnam(sys_get_temp_dir(), 'flyclone_');

      $bytes_writted = file_put_contents($temp_filepath, $input, LOCK_EX);

      if ($bytes_writted === FALSE) {
         throw new WriteOperationFailedException($temp_filepath);
      }

      $left_local = new LocalProvider('local');
      $right_mix = $this->left_side;

      $rclone = new self($left_local, $right_mix);

      return $rclone->moveto($temp_filepath, $path, $flags);
   }

   public function copy(string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directTwinRun('copy', $source_path, $dest_path, $flags);
   }

   public function move(string $source_path, string $dest_DIR_path, array $flags = [])
   : bool
   {
      return $this->directTwinRun('move', $source_path, $dest_DIR_path, $flags);
   }

   public function moveto(string $source_path, string $dest_path, array $flags = [])
   : bool
   {
      return $this->directTwinRun('moveto', $source_path, $dest_path, $flags);
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
