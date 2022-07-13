<?php


namespace CloudAtlas\Flyclone;

use CloudAtlas\Flyclone\Exception\DirectoryNotFoundException;
use CloudAtlas\Flyclone\Exception\FatalErrorException;
use CloudAtlas\Flyclone\Exception\FileNotFoundException;
use CloudAtlas\Flyclone\Exception\LessSeriousErrorException;
use CloudAtlas\Flyclone\Exception\MaxTransferReachedException;
use CloudAtlas\Flyclone\Exception\NoFilesTransferredException;
use CloudAtlas\Flyclone\Exception\ProcessTimedOutException;
use CloudAtlas\Flyclone\Exception\SyntaxErrorException;
use CloudAtlas\Flyclone\Exception\TemporaryErrorException;
use CloudAtlas\Flyclone\Exception\UnknownErrorException;
use CloudAtlas\Flyclone\Providers\LocalProvider;
use CloudAtlas\Flyclone\Providers\Provider;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutExceptionAlias;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Rclone
{

   private static string $BIN;
   private Provider      $left_side;
   private ?Provider     $right_side;

   private static int    $timeout     = 120;
   private static int    $idleTimeout = 100;
   private static array  $flags       = [];
   private static array  $envs        = [];
   private static string $input;
   private object        $progress;
   private static array  $reset       = [
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

   public function __construct ( Provider $left_side, ?Provider $right_side = NULL )
   {
      $this->reset();

		$this->setLeftSide($left_side);

		$this->setRightSide($right_side ?? $left_side);
   }

   private function reset (): void
   {
      self::$timeout     = self::$reset[ 'timeout' ];
      self::$idleTimeout = self::$reset[ 'idleTimeout' ];
      self::$flags       = self::$reset[ 'flags' ];
      self::$envs        = self::$reset[ 'envs' ];
      self::$input       = self::$reset[ 'input' ];

      $this->resetProgress();
   }

   public function isLeftSideFolderAgnostic (): bool
   {
      return $this->getLeftSide()->isFolderAgnostic();
   }

   public function isRightSideFolderAgnostic (): bool
   {
      return $this->getRightSide()->isFolderAgnostic();
   }


   public static function prefix_flags ( array $arr, string $prefix = 'RCLONE_' ): array
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

   private function allFlags ( array $add = [] ): array
   {
      return array_merge($this->left_side->flags(), $this->right_side->flags(), self::$flags, $add);
   }

   private function allEnvs ( array $add = [] ): array
   {
      $forced[ 'RCLONE_LOCAL_ONE_FILE_SYSTEM' ] = TRUE;

      $fluent = self::prefix_flags(self::$envs);
      $add    = self::prefix_flags($add);

      return array_merge($this->allFlags(), $fluent, $add, $forced);

   }


   public static function obscure ( string $secret ): string
   {
      $process = new Process([ self::getBIN(), 'obscure', $secret ]);
      $process->setTimeout(3);

      $process->mustRun();

      return trim($process->getOutput());
   }

   private function simpleRun ( string $command, array $flags = [], array $envs = [], callable $onProgress = NULL ): string
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

      try {
         if ($onProgress) {
            $process->mustRun(function ( $type, $buffer ) use ( $onProgress ) {
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
      } catch (ProcessFailedException $exception) {
         $regex = '/Exit\sCode:\s(\d+?).*Error\sOutput:.*?={10,20}\s(.*)/mis';

         preg_match_all($regex, $exception->getMessage(), $matches, PREG_SET_ORDER, 0);

         if (count($matches[ 0 ]) === 3) {
            [ , $code, $msg ] = $matches[ 0 ];
            $msg = trim($msg);
            switch ($code) {
               case 1:
                  throw new SyntaxErrorException($exception, $msg, $code);
                  break;
               // case 2 is default
               case 3:
                  throw new DirectoryNotFoundException($exception, $msg, $code);
                  break;
               case 4:
                  throw new FileNotFoundException($exception, $msg, $code);
                  break;
               case 5:
                  throw new TemporaryErrorException($exception, $msg, $code);
                  break;
               case 6:
                  throw new LessSeriousErrorException($exception, $msg, $code);
                  break;
               case 7:
                  throw new FatalErrorException($exception, $msg, $code);
                  break;
               case 8:
                  throw new MaxTransferReachedException($exception, $msg, $code);
                  break;
               case 9:
                  throw new NoFilesTransferredException($exception, $msg, $code);
                  break;
               default:
                  throw new UnknownErrorException($exception, $msg, $code);
            }
         }
         else {
            throw new UnknownErrorException($exception);
         }
      } catch (SymfonyProcessTimedOutExceptionAlias $exception) {
         throw new ProcessTimedOutException($exception);
      } catch (\Exception $exception) {
         throw new UnknownErrorException($exception);
      }
   }

   protected function directRun ( string $command, $path = NULL, array $flags = [], callable $onProgress = NULL ): bool
   {
      $this->simpleRun($command, [
          $this->left_side->backend($path),
      ],               $this->allEnvs($flags), $onProgress);

      return TRUE;
   }

   protected function directTwinRun ( string $command, $left_path = NULL, $right_path = NULL, array $flags = [], callable $onProgress = NULL ): bool
   {
      $this->simpleRun($command,
                       [ $this->left_side->backend($left_path), $this->right_side->backend($right_path) ],
                       $this->allEnvs($flags),
                       $onProgress);

      return TRUE;
   }

   private function inputRun ( string $command, string $input, array $flags = [], array $envs = [], callable $onProgress = NULL ): bool
   {
      $this->setInput($input);

      return (bool) $this->simpleRun($command, $flags, $envs, $onProgress);
   }

   public function version ( bool $numeric = FALSE ): string
   {
      $cmd = $this->simpleRun('version');

      preg_match_all('/rclone\sv(.+)/m', $cmd, $version, PREG_SET_ORDER, 0);

      return $numeric ? (float) $version[ 0 ][ 1 ] : $version[ 0 ][ 1 ];
   }

   public static function getBIN (): string
   {
      return self::$BIN ?? self::guessBIN();
   }

   public static function setBIN ( string $BIN ): void
   {
      self::$BIN = (string) $BIN;
   }

   public static function guessBIN (): string
   {
      $BIN = once(static function () {
         return (new ExecutableFinder)->find('rclone', '/usr/bin/rclone', [
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

   private function parseProgress ( string $type, string $buffer, callable $onProgress = NULL ): void
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

   private function setProgress ( array $data ): void
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

   public function getProgress (): object
   {
      return $this->progress;
   }

   private function resetProgress (): void
   {
      $this->progress = (object) self::$reset[ 'progress' ];
   }

   public function setInput ( string $input ): void
   {
      self::$input = $input;
   }

   public function setIdleTimeout ( $idleTimeout ): void
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
   public function ls ( string $path, array $flags = [] ): array
   {
      $result = $this->simpleRun('lsjson', [
          $this->left_side->backend($path),
      ],                         $this->allEnvs($flags));

      $arr = json_decode($result, FALSE, 10, JSON_THROW_ON_ERROR);

      foreach ($arr as &$item) {
         if ($item->ModTime) {
            $time_string   = preg_replace('/\.0{8,}Z/m', '.0Z', $item->ModTime);
            $item->ModTime = strtotime($time_string);
         }
      }

      return $arr;
   }

   public function is_file ( $path ): object
   {
      return $this->exists($path, 'file');
   }

   public function is_dir ( string $path ): object
   {
      return $this->exists($path, 'dir');
   }

   /**
    * @param string $path
    * @param string $type 'dir' or 'file'
    *
    * @return object
    */
   public function exists ( string $path, string $type ): object
   {
      $dirname  = dirname($path);
      $basename = basename($path);

      try {
         $ls    = $this->ls($dirname);
         $found = array_filter($ls, static fn( $i ) => $i->Name === $basename && $i->IsDir === ($type === 'dir'));
         return (object) [ 'exists' => count($found) === 1, 'details' => array_change_key_case($found[ 0 ]) ?? [], 'error' => '' ];
      } catch (\Exception $e) {
         return (object) [ 'exists' => FALSE, 'error' => $e, ];
      }
   }


   /**
    * Create new file or change file modification time.
    *
    * @see https://rclone.org/commands/rclone_touch/
    *
    * @param string        $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function touch ( string $path, array $flags = [], callable $onProgress = NULL ): bool
   {
      // @FIXME https://github.com/cloudatlasid/flyclone/issues/2
      // return $this->directRun('touch', $path, $flags, $onProgress);
      $this->rcat($path, '', $flags, $onProgress);

      return true;
   }

   /**
    * Make the path if it doesn't already exist.
    *
    * @see https://rclone.org/commands/rclone_mkdir/
    *
    * @param string        $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function mkdir ( string $path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directRun('mkdir', $path, $flags, $onProgress);
   }

   /**
    * This removes empty directory given by path. Will not remove the path if it has any objects in it, not even empty
    * subdirectories. Use command rmdirs (or delete with option --rmdirs) to do that.
    *
    * @see https://rclone.org/commands/rclone_rmdir/
    *
    * @param string        $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function rmdir ( string $path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directRun('rmdir', $path, $flags, $onProgress);
   }

   /**
    * Remove empty directories under the path.
    *
    * @see https://rclone.org/commands/rclone_rmdirs/
    *
    * @param string        $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function rmdirs ( string $path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directRun('rmdirs', $path, $flags, $onProgress);
   }

   /**
    * Remove the path and all of its contents. Note that this does not obey include/exclude filters - everything will
    * be removed. Use the delete command if you want to selectively delete files. To delete empty directories only, use
    * command rmdir or rmdirs.
    *
    * @see https://rclone.org/commands/rclone_purge/
    *
    * @param string        $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function purge ( string $path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directRun('purge', $path, $flags, $onProgress);
   }

   /**
    * Remove the files in path. Unlike purge it obeys include/exclude filters so can be used to selectively delete
    * files.
    * rclone delete only deletes files but leaves the directory structure alone. If you want to delete a directory and
    * all of its contents use the purge command.
    *
    * @see https://rclone.org/commands/rclone_delete/
    *
    * @param string|null   $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function delete ( string $path = NULL, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directRun('delete', $path, $flags, $onProgress);
   }

   /**
    * Remove a single file from remote. Unlike delete it cannot be used to remove a directory and it doesn't obey
    * include/exclude filters - if the specified file exists, it will always be removed.
    *
    * @see https://rclone.org/commands/rclone_deletefile/
    *
    * @param string|null   $path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function deletefile ( string $path = NULL, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directRun('deletefile', $path, $flags, $onProgress);
   }

   public function size ( string $path = NULL, array $flags = [], callable $onProgress = NULL )
   {
      $result = $this->simpleRun('size', [
          $this->left_side->backend($path),
          '--json',
      ],                         $this->allEnvs($flags), $onProgress);

      return json_decode($result, FALSE, 10, JSON_THROW_ON_ERROR);
   }

   public function cat ( string $path, array $flags = [], callable $onProgress = NULL ): string
   {
      return $this->simpleRun('cat', [
          $this->left_side->backend($path),
      ],                      $this->allEnvs($flags), $onProgress);
   }


   public function rcat ( string $path, string $input, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->inputRun('rcat', $input, [
          $this->left_side->backend($path),
      ],                     $this->allEnvs($flags), $onProgress);
   }


   public function upload_file ( string $local_path, string $remote_path, array $flags = [], callable $onProgress = NULL ): bool
   {
      $left_local = new LocalProvider('local');
      $right_mix  = $this->left_side;

      $rclone = new self($left_local, $right_mix);

      return $rclone->moveto($local_path, $remote_path, $flags, $onProgress);
   }

   public function copy ( string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directTwinRun('copy', $source_path, $dest_path, $flags, $onProgress);
   }

   public function move ( string $source_path, string $dest_DIR_path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directTwinRun('move', $source_path, $dest_DIR_path, $flags, $onProgress);
   }

   /**
    * Move file or directory from source to dest.
    * If source:path is a file or directory then it moves it to a file or directory named dest:path.
    * This can be used to rename files or upload single files to other than their existing name. If the source is a
    * directory then it acts exactly like the move command.
    *
    * @see https://rclone.org/commands/rclone_moveto/
    *
    * @param string        $source_path
    * @param string        $dest_path
    * @param array         $flags
    * @param callable|null $onProgress
    *
    * @return bool
    */
   public function moveto ( string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directTwinRun('moveto', $source_path, $dest_path, $flags, $onProgress);
   }

   public function sync ( string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directTwinRun('sync', $source_path, $dest_path, $flags, $onProgress);
   }

   public function check ( string $source_path, string $dest_path, array $flags = [], callable $onProgress = NULL ): bool
   {
      return $this->directTwinRun('check', $source_path, $dest_path, $flags, $onProgress);
   }

  /**
	* @return Provider
	*/
  public function getLeftSide (): Provider
  {
	 return $this->left_side;
  }

  /**
	* @param Provider $left_side
	*/
  public function setLeftSide ( Provider $left_side ): void
  {
	 $this->left_side = $left_side;
  }

  /**
	* @return Provider
	*/
  public function getRightSide (): Provider
  {
	 return $this->right_side;
  }

  /**
	* @param Provider $right_side
	*/
  public function setRightSide ( Provider $right_side ): void
  {
	 $this->right_side = $right_side;
  }
}
