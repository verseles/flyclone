<?php


namespace CloudAtlas\Flyclone;


use CloudAtlas\Flyclone\Exception\WriteOperationFailedException;
use CloudAtlas\Flyclone\Providers\LocalProvider;
use CloudAtlas\Flyclone\Providers\Provider;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Rclone
{

   private static string $BIN;
   private Provider $left_side;
   private ?Provider $right_side;

   private static int $timeout = 120;
   private static int $idleTimeout = 100;
   private static array $flags = [];
   private static array $envs = [];
   private static string $input;
   private object $progress;
   private static array $reset = [
       'timeout'     => 120,
       'idleTimeout' => 100,
       'flags'       => [],
       'envs'        => [],
       'input'       => '',
       'progress'    => [
           'raw'       => '',
           'dataSent'  => 0,
           'dataTotal' => 0,
           'sent'      => 0,
           'speed'     => 0,
           'eta'       => 0,
       ],
   ];

   public function __construct(Provider $left_side, ?Provider $right_side = NULL)
   {
      $this->reset();

      $this->left_side  = $left_side;
      $this->right_side = $right_side ?? $left_side;
   }

   private function reset()
   : void
   {
      self::$timeout     = self::$reset[ 'timeout' ];
      self::$idleTimeout = self::$reset[ 'idleTimeout' ];
      self::$flags       = self::$reset[ 'flags' ];
      self::$envs        = self::$reset[ 'envs' ];
      self::$input       = self::$reset[ 'input' ];

      $this->resetProgress();
   }

   public function isLeftSideFolderAgnostic()
   : bool
   {
      return $this->left_side->isFolderAgnostic();
   }

   public function isRightSideFolderAgnostic()
   : bool
   {
      return $this->right_side->isFolderAgnostic();
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

   private function simpleRun(string $command, array $flags = [], array $envs = [], callable $onProgress = NULL)
   : string
   {
      //      echo "$command\n";
      //      var_dump($flags);
      //      var_dump($envs);

      if ($onProgress) {
         $envs += [ 'RCLONE_STATS_ONE_LINE' => 1, 'RCLONE_PROGRESS' => 1 ]; // needed for $this->parseProgress()

      }
      $process = new Process([ self::getBIN(), $command, ...$flags ], sys_get_temp_dir(), $envs);

      $process->setTimeout(self::$timeout);
      $process->setIdleTimeout(self::$idleTimeout);
      if (!empty(self::$input)) {
         $process->setInput(self::$input);
      }

      if ($onProgress) {
         $process->mustRun(function ($type, $buffer) use ($onProgress) {
            $this->parseProgress($type, $buffer, $onProgress);
            $onProgress($type, $buffer);
         });
      }
      else {
         $process->mustRun();
      }

      $this->reset();

      $output = $process->getOutput();

      return trim($output);
   }

   protected function directRun(string $command, $path = NULL, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      $this->simpleRun($command, [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags), $onProgress);

      return TRUE;
   }

   protected function directTwinRun(string $command, $left_path = NULL, $right_path = NULL, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      $this->simpleRun($command, [
          $this->left_side->backend($left_path),
          $this->right_side->backend($right_path),
      ], $this->allEnvs($flags), $onProgress);

      return TRUE;
   }

   private function inputRun(string $command, string $input, array $flags = [], array $envs = [], callable $onProgress = NULL)
   : bool
   {
      $this->setInput($input);

      return (bool) $this->simpleRun($command, $flags, $envs, $onProgress);
   }

   public function version(bool $numeric = FALSE)
   : string
   {
      $cmd = $this->simpleRun('version');

      preg_match_all('/rclone\sv(.+)/m', $cmd, $version, PREG_SET_ORDER, 0);

      return $numeric ? (float) $version[ 0 ][ 1 ] : $version[ 0 ][ 1 ];
   }

   public static function getBIN()
   : string
   {
      return self::$BIN ?? self::guessBIN();
   }

   public static function setBIN(string $BIN)
   : void
   {
      self::$BIN = (string) $BIN;
   }

   public static function guessBIN()
   : string
   {
      $BIN = once(static function () {
         $executableFinder = new ExecutableFinder();

         return $executableFinder->find('rclone', '/usr/bin/rclone', [
             '/usr/local/bin',
             '/usr/bin',
             '/bin',
             '/usr/local/sbin',
             '/var/lib/snapd/snap/bin',
         ]);
      });

      self::setBIN($BIN);

      return self::getBIN();
   }

   private function parseProgress(string $type, string $buffer, callable $onProgress = NULL)
   : void
   {
      // @TODO throw "unreliable" error if $type === ERR

      if ($type === 'out') {
         $regex = '/([\d.]+\w)\s\/\s([\d.]+\s\wB)ytes\,\s(\d+)\%,\s([\d.]+\s\wB)ytes\/s,\sETA\s([\w]+)/miu';

         preg_match_all($regex, $buffer, $matches, PREG_SET_ORDER, 0);

         if (count($matches) === 1 && count($matches[ 0 ]) === 6) {
            $this->setProgress($matches[ 0 ]);
         }
      }
   }

   private function setProgress(array $data)
   : void
   {
      $this->progress = (object) [
          'raw'       => $data[ 0 ],
          'dataSent'  => $data[ 1 ],
          'dataTotal' => $data[ 2 ],
          'sent'      => $data[ 3 ],
          'speed'     => $data[ 4 ],
          'eta'       => $data[ 5 ],
      ];
   }

   public function getProgress()
   : object
   {
      return $this->progress;
   }

   private function resetProgress()
   : void
   {
      $this->progress = (object) self::$reset[ 'progress' ];
   }

   public function setInput(string $input)
   : void
   {
      self::$input = $input;
   }

   public function setIdleTimeout($idleTimeout)
   : void
   {
      self::$idleTimeout = $idleTimeout;
   }


   /**
    * @param string $path
    * @param array  $flags
    *
    * @return array
    * @throws \JsonException
    */
   public function ls(string $path, array $flags = [])
   : array
   {
      $result = $this->simpleRun('lsjson', [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags));

      return json_decode($result, FALSE, 10, JSON_THROW_ON_ERROR);
   }

   public function is_file($path)
   : object
   {
      try {
         $ls = $this->ls($path);

         if (count($ls) !== 1) {
            return (object) [ 'exists' => FALSE, 'details' => [], 'error' => NULL, ];
         }

         return (object) [ 'exists' => $ls[ 0 ]->IsDir === FALSE, 'details' => $ls[ 0 ], 'error' => NULL, ];
      } catch (\Exception $e) {
         return (object) [ 'exists' => FALSE, 'error' => $e, 'details' => [], ];
      }
   }

   public function is_dir($path)
   : object
   {
      try {
         $parent = dirname($path);
         $name   = basename($path);

         $ls = $this->ls($parent);

         $find = array_filter($ls, fn($i) => $i->Name === $name && $i->IsDir === TRUE);
         if (count($find) !== 1) {
            return (object) [ 'exists' => FALSE, 'details' => [], 'error' => '', ];
         }
         $found = reset($find);


         return (object) [ 'exists' => TRUE, 'details' => $found, ];
      } catch (\Exception $e) {
         return (object) [ 'exists' => FALSE, 'error' => $e, ];
      }
   }


   public function touch(string $path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directRun('touch', $path, $flags, $onProgress);
   }

   public function mkdir(string $path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directRun('mkdir', $path, $flags, $onProgress);
   }

   public function rmdir(string $path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directRun('rmdir', $path, $flags, $onProgress);
   }

   public function purge(string $path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directRun('purge', $path, $flags, $onProgress);
   }

   public function delete(string $path = NULL, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directRun('delete', $path, $flags, $onProgress);
   }

   public function size(string $path = NULL, array $flags = [], callable $onProgress = NULL)
   {
      $result = $this->simpleRun('size', [
          $this->left_side->backend($path),
          '--json',
      ], $this->allEnvs($flags), $onProgress);

      return json_decode($result, FALSE, 10, JSON_THROW_ON_ERROR);
   }

   public function cat(string $path, array $flags = [], callable $onProgress = NULL)
   : string
   {
      return $this->simpleRun('cat', [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags), $onProgress);
   }


   public function rcat(string $path, string $input, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->inputRun('rcat', $input, [
          $this->left_side->backend($path),
      ], $this->allEnvs($flags), $onProgress);
   }

   public function write_file(string $path, string $input, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      $temp_filepath = tempnam(sys_get_temp_dir(), 'flyclone_');

      $bytes_writted = file_put_contents($temp_filepath, $input, LOCK_EX);

      if ($bytes_writted === FALSE) {
         throw new WriteOperationFailedException($temp_filepath);
      }

      $left_local = new LocalProvider('local');
      $right_mix  = $this->left_side;

      $rclone = new self($left_local, $right_mix);

      return $rclone->moveto($temp_filepath, $path, $flags, $onProgress);
   }

   public function copy(string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directTwinRun('copy', $source_path, $dest_path, $flags, $onProgress);
   }

   public function move(string $source_path, string $dest_DIR_path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directTwinRun('move', $source_path, $dest_DIR_path, $flags, $onProgress);
   }

   public function moveto(string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directTwinRun('moveto', $source_path, $dest_path, $flags, $onProgress);
   }

   public function sync(string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directTwinRun('sync', $source_path, $dest_path, $flags, $onProgress);
   }

   public function check(string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL)
   : bool
   {
      return $this->directTwinRun('check', $source_path, $dest_path, $flags, $onProgress);
   }
}
