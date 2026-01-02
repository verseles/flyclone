<?php

declare(strict_types=1);

namespace Verseles\Flyclone;

use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\Provider;
use Symfony\Component\Process\Process;

class Rclone
{
  private Provider       $left_side;
  private Provider       $right_side;
  private ProgressParser $progressParser;
  private ProcessManager $processManager;

  // Static configuration - delegated to ProcessManager but kept for backward compatibility
  private static array  $flags = [];
  private static array  $envs  = [];

  /** @var RetryHandler|null Retry handler for transient failures */
  private ?RetryHandler $retryHandler = null;

  /** @var bool Whether dry-run mode is enabled for this instance */
  private bool $dryRun = false;

  /** @var FilterBuilder|null Current filter configuration */
  private ?FilterBuilder $filter = null;
  
  /**
   * Constructor for Rclone.
   *
   * @param Provider      $left_side  The primary (source) provider.
   * @param Provider|null $right_side The secondary (destination) provider. If null, defaults to $left_side.
   */
  public function __construct(Provider $left_side, ?Provider $right_side = null)
  {
    $this->progressParser = new ProgressParser();
    $this->processManager = new ProcessManager();

    // Extract secrets from providers for redaction
    $secrets = array_merge(
      $left_side->extractSecrets(),
      $right_side ? $right_side->extractSecrets() : []
    );
    $this->processManager->setSecrets($secrets);

    $this->setLeftSide($left_side);
    $this->setRightSide($right_side ?? $left_side);
  }

  /**
   * Enable dry-run mode for this instance.
   *
   * In dry-run mode, operations show what would be done without actually doing it.
   *
   * @param bool $enabled Whether to enable dry-run mode.
   */
  public function dryRun(bool $enabled = true): self
  {
    $this->dryRun = $enabled;
    return $this;
  }

  /**
   * Check if dry-run mode is enabled.
   */
  public function isDryRun(): bool
  {
    return $this->dryRun;
  }

  /**
   * Set a retry handler for transient failures.
   *
   * @param RetryHandler|null $handler The retry handler, or null to disable.
   */
  public function withRetry(?RetryHandler $handler): self
  {
    $this->retryHandler = $handler;
    return $this;
  }

  /**
   * Configure retry with default settings.
   *
   * @param int $maxAttempts Maximum number of retry attempts.
   * @param int $baseDelayMs Base delay between retries in milliseconds.
   */
  public function retry(int $maxAttempts = 3, int $baseDelayMs = 1000): self
  {
    $this->retryHandler = RetryHandler::create()
      ->maxAttempts($maxAttempts)
      ->baseDelay($baseDelayMs);
    return $this;
  }

  /**
   * Set filter configuration for operations.
   *
   * @param FilterBuilder $filter The filter configuration.
   */
  public function withFilter(FilterBuilder $filter): self
  {
    $this->filter = $filter;
    return $this;
  }

  /**
   * Create and return a new filter builder.
   *
   * Usage: $rclone->filter()->include('*.txt')->exclude('*.tmp')
   */
  public function filter(): FilterBuilder
  {
    if ($this->filter === null) {
      $this->filter = FilterBuilder::create();
    }
    return $this->filter;
  }

  /**
   * Clear the current filter configuration.
   */
  public function clearFilter(): self
  {
    $this->filter = null;
    return $this;
  }

  /**
   * Check provider connectivity and health.
   *
   * @param string|null $path Optional path to check.
   *
   * @return object Health check result with 'healthy', 'latency_ms', and 'error' properties.
   */
  public function healthCheck(?string $path = null): object
  {
    $start = microtime(true);

    try {
      // Try to list the root or specified path
      $this->ls($path ?? '');

      $latency = (microtime(true) - $start) * 1000;

      return (object) [
        'healthy' => true,
        'latency_ms' => round($latency, 2),
        'error' => null,
        'provider' => $this->left_side->provider(),
      ];
    } catch (\Exception $e) {
      $latency = (microtime(true) - $start) * 1000;

      return (object) [
        'healthy' => false,
        'latency_ms' => round($latency, 2),
        'error' => $e->getMessage(),
        'provider' => $this->left_side->provider(),
      ];
    }
  }

  /**
   * Get the last executed command (for debugging).
   */
  public function getLastCommand(): string
  {
    return $this->processManager->getLastCommandString();
  }

  /**
   * Get the last environment variables (for debugging).
   * Sensitive values are redacted.
   */
  public function getLastEnvs(): array
  {
    return $this->processManager->getLastEnvs();
  }
  
  /**
   * Gets the current process timeout value.
   *
   * @return int Timeout in seconds.
   */
  public static function getTimeout() : int
  {
    return ProcessManager::getTimeout();
  }
  
  /**
   * Sets the process timeout value.
   *
   * @param int $timeout Timeout in seconds.
   */
  public static function setTimeout(int $timeout) : void
  {
    ProcessManager::setTimeout($timeout);
  }
  
  /**
   * Gets the current process idle timeout value.
   *
   * @return int Idle timeout in seconds.
   */
  public static function getIdleTimeout() : int
  {
    return ProcessManager::getIdleTimeout();
  }
  
  /**
   * Sets the process idle timeout value.
   *
   * @param int $idleTimeout Idle timeout in seconds.
   */
  public static function setIdleTimeout(int $idleTimeout) : void
  {
    ProcessManager::setIdleTimeout($idleTimeout);
  }
  
  /**
   * Gets the globally set rclone flags.
   *
   * @return array Array of flags.
   */
  public static function getFlags() : array
  {
    return self::$flags;
  }
  
  /**
   * Sets global rclone flags. These flags are applied to most rclone commands.
   * Example: ['retries' => 3, 'verbose' => true]
   *
   * @param array $flags Array of flags. Boolean true will be converted to "true", false to "false".
   */
  public static function setFlags(array $flags) : void
  {
    self::$flags = $flags;
  }
  
  /**
   * Gets the custom environment variables.
   *
   * @return array Array of environment variables.
   */
  public static function getEnvs() : array
  {
    return self::$envs;
  }
  
  /**
   * Sets custom environment variables, typically used for rclone parameters.
   *
   * @param array $envs Array of environment variables. Boolean true will be converted to "true", false to "false".
   */
  public static function setEnvs(array $envs) : void
  {
    self::$envs = $envs;
  }
  
  /**
   * Gets the input string for rclone commands like 'rcat'.
   *
   * @return string The input string.
   */
  public static function getInput() : string
  {
    return ProcessManager::getInput();
  }
  
  /**
   * Sets the input string for rclone commands.
   *
   * @param string $input The input string.
   */
  public static function setInput(string $input) : void
  {
    ProcessManager::setInput($input);
  }
  
  /**
   * Checks if the left-side provider is directory-agnostic (does not support empty directories).
   *
   * @return bool True if directory-agnostic, false otherwise.
   */
  public function isLeftSideDirAgnostic() : bool
  {
    return $this->getLeftSide()->isDirAgnostic();
  }
  
  /**
   * Checks if the right-side provider is directory-agnostic.
   *
   * @return bool True if directory-agnostic, false otherwise.
   */
  public function isRightSideDirAgnostic() : bool
  {
    return $this->getRightSide()->isDirAgnostic();
  }
  
  /**
   * Checks if the left-side provider treats buckets as directories.
   *
   * @return bool True if buckets are treated as directories, false otherwise.
   */
  public function isLeftSideBucketAsDir() : bool
  {
    return $this->getLeftSide()->isBucketAsDir();
  }
  
  /**
   * Checks if the right-side provider treats buckets as directories.
   *
   * @return bool True if buckets are treated as directories, false otherwise.
   */
  public function isRightSideBucketAsDir() : bool
  {
    return $this->getRightSide()->isBucketAsDir();
  }
  
  /**
   * Checks if the left-side provider lists contents as a flat tree (all items at once).
   *
   * @return bool True if it lists as a tree, false otherwise.
   */
  public function isLeftSideListsAsTree() : bool
  {
    return $this->getLeftSide()->isListsAsTree();
  }
  
  /**
   * Checks if the right-side provider lists contents as a flat tree.
   *
   * @return bool True if it lists as a tree, false otherwise.
   */
  public function isRightSideListsAsTree() : bool
  {
    return $this->getRightSide()->isListsAsTree();
  }
  
  
  /**
   * Prefixes array keys for rclone environment variables and transforms them.
   *
   * @param array  $arr    The input array of flags or parameters.
   * @param string $prefix The prefix to apply (e.g., 'RCLONE_', 'RCLONE_CONFIG_MYREMOTE_').
   *
   * @return array The processed array with prefixed keys and string-cast values.
   */
  public static function prefix_flags(array $arr, string $prefix = 'RCLONE_') : array
  {
    return CommandBuilder::prefixFlags($arr, $prefix);
  }
  
  /**
   * Consolidates all environment variables for the rclone process.
   *
   * @param array $additional_operation_flags Flags specific to the current rclone operation.
   *
   * @return array An array of environment variables to be passed to Symfony Process.
   */
  private function allEnvs(array $additional_operation_flags = []) : array
  {
    return CommandBuilder::buildEnvironment(
      $this->left_side,
      $this->right_side,
      self::getFlags(),
      self::getEnvs(),
      $additional_operation_flags
    );
  }
  
  /**
   * Obscures a password or secret using 'rclone obscure'.
   *
   * @param string $secret The secret to obscure.
   *
   * @return string The obscured secret.
   */
  public static function obscure(string $secret) : string
  {
    return ProcessManager::obscure($secret);
  }
  
  /**
   * Centralized method to prepare and execute an rclone command.
   *
   * @param string        $command         The rclone command (e.g., 'lsjson', 'copy').
   * @param array         $args            Arguments for the command.
   * @param array         $operation_flags Additional operation flags.
   * @param callable|null $onProgress      Optional progress callback.
   * @param int|null      $timeout         Optional per-operation timeout in seconds.
   *
   * @return Process The completed process instance.
   */
  private function _run(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = null, ?int $timeout = null): Process
  {
    // Apply dry-run flag if enabled
    if ($this->dryRun) {
      $operation_flags['dry-run'] = true;
    }

    // Apply filter flags if set
    if ($this->filter !== null && $this->filter->hasFilters()) {
      $operation_flags = array_merge($operation_flags, $this->filter->toFlags());
    }

    $commandArgs = CommandBuilder::buildCommandArgs(self::getBIN(), $command, $args);
    $finalEnvs = $this->allEnvs($operation_flags);

    $progressCallback = null;
    if ($onProgress) {
      $progressCallback = function ($type, $buffer) use ($onProgress) {
        $this->progressParser->parse($type, $buffer);
        $onProgress($type, $buffer);
      };
    }

    // Log command execution in debug mode
    Logger::logCommand($this->processManager->getLastCommandString(), $finalEnvs);

    $startTime = microtime(true);

    // Execute with or without retry
    $executeOperation = fn() => $this->processManager->run($commandArgs, $finalEnvs, $progressCallback, $timeout);

    try {
      if ($this->retryHandler !== null) {
        $result = $this->retryHandler->execute($executeOperation);
      } else {
        $result = $executeOperation();
      }

      Logger::logResult(true, microtime(true) - $startTime);
      return $result;
    } catch (\Exception $e) {
      Logger::logResult(false, microtime(true) - $startTime);
      throw $e;
    }
  }
  
  /**
   * Executes a simple rclone command that returns a string output.
   *
   * @param string        $command         The rclone command (e.g., 'lsjson').
   * @param array         $args            Arguments for the command.
   * @param array         $operation_flags Additional operation flags.
   * @param callable|null $onProgress      Optional progress callback.
   *
   * @return string The trimmed standard output.
   */
  private function simpleRun(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = NULL) : string
  {
    $completedProcess = $this->_run($command, $args, $operation_flags, $onProgress);
    return trim($completedProcess->getOutput());
  }
  
  /**
   * Executes an rclone command that performs a transfer and returns statistics.
   *
   * @param string        $command         The rclone command (e.g., 'copy', 'sync').
   * @param array         $args            Arguments for the command (source, destination).
   * @param array         $operation_flags Additional operation flags.
   * @param callable|null $onProgress      Optional progress callback.
   *
   * @return object An object containing the success status and transfer statistics.
   */
  private function runAndGetStats(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = NULL) : object
  {
    $env_options = $operation_flags;
    
    $env_options['stats'] = '1s';
    $env_options['stats-log-level'] = 'NOTICE';
    
    if ($onProgress) {
      $this->progressParser->reset();
      $env_options['progress'] = true;
    }
    
    $completedProcess = $this->_run($command, $args, $env_options, $onProgress);
    
    $stderr = $completedProcess->getErrorOutput();
    
    $stats = StatsParser::parse($stderr);
    
    if (empty(trim($stderr)) && in_array($command, ['moveto', 'copyto'])) {
      $stats->files = 1;
    }
    
    return (object) [
      'success'    => true,
      'stats'      => $stats,
      'raw_output' => $stderr,
    ];
  }
  
  /**
   * Executes an rclone command targeting a single provider path.
   *
   * @param string        $command    The rclone command.
   * @param string|null   $path       The path on the left-side provider.
   * @param array         $flags      Additional flags for the operation.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return string The output of the command.
   */
  private function directRun(string $command, $path = NULL, array $flags = [], ?callable $onProgress = NULL) : string
  {
    return $this->simpleRun($command, [
      $this->left_side->backend($path),
    ], $flags, $onProgress);
  }
  
  /**
   * Executes an rclone command involving two provider paths (source and destination).
   *
   * @param string        $command    The rclone command.
   * @param string|null   $left_path  Path on the left-side provider.
   * @param string|null   $right_path Path on the right-side provider.
   * @param array         $flags      Additional flags for the operation.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return string The output of the command.
   */
  private function directTwinRun(string $command, ?string $left_path = NULL, ?string $right_path = NULL, array $flags = [], ?callable $onProgress = NULL) : string
  {
    return $this->simpleRun($command,
                            [$this->left_side->backend($left_path), $this->right_side->backend($right_path)],
                            $flags,
                            $onProgress);
  }
  
  /**
   * Gets the rclone version.
   *
   * @param bool $numeric If true, attempts to return a numeric (float) version. Otherwise, returns string.
   *
   * @return string|float The rclone version.
   */
  public function version(bool $numeric = FALSE) : string|float
  {
    $cmd_output = $this->simpleRun('version');
    
    preg_match_all('/rclone\sv(.+)/m', $cmd_output, $version_matches, PREG_SET_ORDER, 0);
    
    if (isset($version_matches[0][1])) {
      $version_string = $version_matches[0][1];
      return $numeric ? (float) $version_string : $version_string;
    }
    return $numeric ? 0.0 : '';
  }
  
  /**
   * Gets the path to the rclone binary.
   *
   * @return string Path to rclone.
   */
  public static function getBIN() : string
  {
    return ProcessManager::getBin();
  }
  
  /**
   * Sets the path to the rclone binary.
   *
   * @param string $BIN Path to rclone.
   */
  public static function setBIN(string $BIN) : void
  {
    ProcessManager::setBin($BIN);
  }
  
  /**
   * Tries to find the rclone binary in common system paths.
   *
   * @return string Path to rclone.
   * @throws \RuntimeException If rclone binary is not found.
   */
  public static function guessBIN() : string
  {
    return ProcessManager::guessBin();
  }
  
  /**
   * Gets the current progress object.
   *
   * @return object The progress object.
   */
  public function getProgress() : object
  {
    return $this->progressParser->getProgress();
  }
  
  /**
   * Resets the progress object to its default state.
   */
  private function resetProgress() : void
  {
    $this->progressParser->reset();
  }
  
  
  /**
   * Lists objects at the source path. (rclone lsjson)
   *
   * @param string $path  Path to list.
   * @param array  $flags Additional flags.
   *
   * @return array Array of objects, each representing a file or directory.
   * @throws \JsonException If JSON decoding fails.
   */
  public function ls(string $path, array $flags = []) : array
  {
    $result_json = $this->simpleRun('lsjson', [$this->left_side->backend($path)], $flags);

    if ($result_json === '' || $result_json === 'null') {
      return [];
    }

    $items_array = json_decode($result_json, FALSE, 512, JSON_THROW_ON_ERROR);

    if (!is_array($items_array)) {
      return [];
    }

    foreach ($items_array as $item) {
      if (isset($item->ModTime) && is_string($item->ModTime)) {
        $time_string = preg_replace('/\.(\d{6})\d*Z$/', '.$1Z', $item->ModTime);
        $timestamp = strtotime($time_string);
        $item->ModTime = ($timestamp !== FALSE) ? $timestamp : NULL;
      }
    }
    return $items_array;
  }
  
  /**
   * Checks if a path exists and is a file.
   *
   * @param string $path Path to check.
   *
   * @return object Object with 'exists' (bool), 'details' (object|array), and 'error' (string|\Exception) properties.
   */
  public function is_file(string $path) : object
  {
    return $this->exists($path, 'file');
  }
  
  /**
   * Checks if a path exists and is a directory.
   *
   * @param string $path Path to check.
   *
   * @return object Object with 'exists' (bool), 'details' (object|array), and 'error' (string|\Exception) properties.
   */
  public function is_dir(string $path) : object
  {
    return $this->exists($path, 'dir');
  }
  
  /**
   * Checks if a path exists and is of the specified type ('file' or 'dir').
   *
   * @param string $path The path to check.
   * @param string $type The type to check for ('file' or 'dir').
   *
   * @return object An object with properties exists, details, and error.
   */
  public function exists(string $path, string $type) : object
  {
    $dirname = dirname($path);
    if ($dirname === '.') {
      $dirname = '';
    }
    $basename = basename($path);
    
    try {
      $listing = $this->ls($dirname);
      $found_item = array_filter($listing, static fn($item) => isset($item->Name) && $item->Name === $basename &&
        isset($item->IsDir) && $item->IsDir === ($type === 'dir'),
      );
      
      $item_exists = count($found_item) === 1;
      return (object) [
        'exists' => $item_exists,
        'details' => $item_exists ? reset($found_item) : [],
        'error' => '',
      ];
    }
    catch (\Exception $e) {
      return (object) ['exists' => FALSE, 'details' => [], 'error' => $e];
    }
  }
  
  
  /**
   * Creates new file or change file modification time. (rclone touch)
   *
   * @see https://rclone.org/commands/rclone_touch/
   *
   * @param string        $path       Path to touch.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  public function touch(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->directRun('touch', $path, $flags, $onProgress);
    return true;
  }
  
  /**
   * Creates the path if it doesn't exist. (rclone mkdir)
   *
   * @see https://rclone.org/commands/rclone_mkdir/
   *
   * @param string        $path       Path to create.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  public function mkdir(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->directRun('mkdir', $path, $flags, $onProgress);
    return true;
  }
  
  /**
   * Removes an empty directory. (rclone rmdir)
   *
   * @see https://rclone.org/commands/rclone_rmdir/
   *
   * @param string        $path       Path to remove.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  public function rmdir(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->directRun('rmdir', $path, $flags, $onProgress);
    return true;
  }
  
  /**
   * Removes empty directories under the path. (rclone rmdirs)
   *
   * @see https://rclone.org/commands/rclone_rmdirs/
   *
   * @param string        $path       Root path to search for empty directories.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  public function rmdirs(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->directRun('rmdirs', $path, $flags, $onProgress);
    return true;
  }
  
  /**
   * Removes the path and all its contents. (rclone purge)
   *
   * @see https://rclone.org/commands/rclone_purge/
   *
   * @param string        $path       Path to purge.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function purge(string $path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('purge', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  /**
   * Removes the files in path. (rclone delete)
   *
   * @see https://rclone.org/commands/rclone_delete/
   *
   * @param string|null   $path       Path containing files to delete.
   * @param array         $flags      Additional flags (e.g. --include, --exclude).
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function delete(?string $path = NULL, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('delete', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  /**
   * Removes a single file from remote. (rclone deletefile)
   *
   * @see https://rclone.org/commands/rclone_deletefile/
   *
   * @param string        $path       Path to the file to delete.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function deletefile(string $path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('deletefile', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  /**
   * Prints the total size and number of objects in remote:path. (rclone size)
   *
   * @param string|null   $path       Path to get size of.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'count' and 'bytes' properties.
   * @throws \JsonException If JSON decoding fails.
   */
  public function size(?string $path = NULL, array $flags = [], ?callable $onProgress = NULL) : object
  {
    $size_flags = array_merge($flags, ['json' => 'true']);
    $result_json = $this->simpleRun('size', [$this->left_side->backend($path)], $size_flags, $onProgress);
    
    return json_decode($result_json, FALSE, 512, JSON_THROW_ON_ERROR);
  }
  
  /**
   * Concatenates any files and sends them to stdout. (rclone cat)
   *
   * @see https://rclone.org/commands/rclone_cat/
   *
   * @param string        $path       Path to the file.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return string The file content.
   */
  public function cat(string $path, array $flags = [], ?callable $onProgress = NULL) : string
  {
    return $this->simpleRun('cat', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  
  /**
   * Copies standard input to remote:path. (rclone rcat)
   *
   * @see https://rclone.org/commands/rclone_rcat/
   *
   * @param string        $path       Destination path on remote.
   * @param string        $input      Content to send.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function rcat(string $path, string $input, array $flags = [], ?callable $onProgress = NULL) : object
  {
    self::setInput($input);
    return $this->runAndGetStats('rcat', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  
  /**
   * Uploads a single local file to a remote path using the 'moveto' command for efficiency.
   *
   * @param string        $local_path  Path to the local file.
   * @param string        $remote_path Destination path on the remote.
   * @param array         $flags       Additional flags.
   * @param callable|null $onProgress  Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function upload_file(string $local_path, string $remote_path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    $uploader = new self(left_side: new LocalProvider('local_temp_upload'), right_side: $this->left_side);
    
    return $uploader->moveto($local_path, $remote_path, $flags, $onProgress);
  }
  
  /**
   * Downloads a file from a remote path to local storage.
   *
   * @param string    $remote_path            The path of the file on the remote server.
   * @param ?string   $local_destination_path The local path where the file should be saved.
   * @param array     $flags                  Additional flags for the download operation.
   * @param ?callable $onProgress             A callback function to track download progress.
   *
   * @return object The result object from the copy operation, with an added `local_path` property on success.
   */
  public function download_to_local(string $remote_path, ?string $local_destination_path = NULL, array $flags = [], ?callable $onProgress = NULL) : object
  {
    $remote_filename = basename($remote_path);
    
    if ($local_destination_path === NULL) {
      $temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_download_' . uniqid();
      if (!mkdir($temp_dir, 0777, TRUE) && !is_dir($temp_dir)) {
        // @codeCoverageIgnoreStart
        throw new \RuntimeException("Failed to create temporary directory: $temp_dir");
        // @codeCoverageIgnoreEnd
      }
      $final_local_path = $temp_dir . DIRECTORY_SEPARATOR . $remote_filename;
    } elseif (is_dir($local_destination_path)) {
      $final_local_path = rtrim($local_destination_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $remote_filename;
    } else {
      $parent_dir = dirname($local_destination_path);
      if (!is_dir($parent_dir)) {
        if (!mkdir($parent_dir, 0777, TRUE) && !is_dir($parent_dir)) {
          // @codeCoverageIgnoreStart
          throw new \RuntimeException("Failed to create parent directory for download: $parent_dir");
          // @codeCoverageIgnoreEnd
        }
      }
      $final_local_path = $local_destination_path;
    }
    
    $downloader = new self(left_side: $this->left_side, right_side: new LocalProvider('local_temp_download'));
    
    $result = $downloader->copyto($remote_path, $final_local_path, $flags, $onProgress);
    
    if ($result->success) {
      $result->local_path = $final_local_path;
    }
    
    return $result;
  }
  
  /**
   * Copies files from source to dest, skipping already copied. (rclone copy)
   *
   * @see https://rclone.org/commands/rclone_copy/
   *
   * @param string        $source_path   Source path (file or directory).
   * @param string        $dest_DIR_path Destination directory path.
   * @param array         $flags         Additional flags.
   * @param callable|null $onProgress    Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function copy(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('copy', [$this->left_side->backend($source_path), $this->right_side->backend($dest_DIR_path)], $flags, $onProgress);
  }
  
  /**
   * Copies a single file or directory from source to a specific destination file/directory path. (rclone copyto)
   *
   * @see https://rclone.org/commands/rclone_copyto/
   *
   * @param string        $source_path Source file or directory path.
   * @param string        $dest_path   Destination file or directory path.
   * @param array         $flags       Additional flags.
   * @param callable|null $onProgress  Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function copyto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('copyto', [$this->left_side->backend($source_path), $this->right_side->backend($dest_path)], $flags, $onProgress);
  }
  
  /**
   * Moves files from source to dest. (rclone move)
   *
   * @see https://rclone.org/commands/rclone_move/
   *
   * @param string        $source_path   Source path (file or directory).
   * @param string        $dest_DIR_path Destination directory path.
   * @param array         $flags         Additional flags.
   * @param callable|null $onProgress    Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function move(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('move', [$this->left_side->backend($source_path), $this->right_side->backend($dest_DIR_path)], $flags, $onProgress);
  }
  
  /**
   * Moves file or directory from source to a specific destination file/directory path. (rclone moveto)
   *
   * @see https://rclone.org/commands/rclone_moveto/
   *
   * @param string        $source_path Source file or directory path.
   * @param string        $dest_path   Destination file or directory path.
   * @param array         $flags       Additional flags.
   * @param callable|null $onProgress  Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function moveto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('moveto', [$this->left_side->backend($source_path), $this->right_side->backend($dest_path)], $flags, $onProgress);
  }
  
  /**
   * Makes source and dest identical, modifying destination only. (rclone sync)
   *
   * @see https://rclone.org/commands/rclone_sync/
   *
   * @param string        $source_path Source directory path.
   * @param string        $dest_path   Destination directory path.
   * @param array         $flags       Additional flags.
   * @param callable|null $onProgress  Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function sync(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : object
  {
    return $this->runAndGetStats('sync', [$this->left_side->backend($source_path), $this->right_side->backend($dest_path)], $flags, $onProgress);
  }
  
  /**
   * Checks the files in the source and destination match. (rclone check)
   *
   * @see https://rclone.org/commands/rclone_check/
   *
   * @param string        $source_path Source directory path.
   * @param string        $dest_path   Destination directory path.
   * @param array         $flags       Additional flags.
   * @param callable|null $onProgress  Optional progress callback.
   *
   * @return bool True if check succeeds.
   */
  public function check(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->directTwinRun('check', $source_path, $dest_path, $flags, $onProgress);
    return TRUE;
  }
  
  /**
   * Gets quota information from the provider (rclone about).
   *
   * @see https://rclone.org/commands/rclone_about/
   *
   * @param string|null $path  Path on the provider.
   * @param array       $flags Additional flags for the operation.
   *
   * @return object An object with quota details.
   * @throws \JsonException
   */
  public function about(?string $path = null, array $flags = []): object
  {
    $flags['json'] = true;
    $result_json = $this->simpleRun('about', [$this->left_side->backend($path)], $flags);
    return json_decode($result_json, false, 512, JSON_THROW_ON_ERROR);
  }
  
  /**
   * Lists the contents of a path in a tree-like format (rclone tree).
   *
   * @see https://rclone.org/commands/rclone_tree/
   *
   * @param string|null $path  The root path to list from.
   * @param array       $flags Additional rclone flags.
   *
   * @return string The tree structure as a string.
   */
  public function tree(?string $path = null, array $flags = []): string
  {
    return $this->simpleRun('tree', [$this->left_side->backend($path)], $flags);
  }
  
  /**
   * Finds and deals with duplicate files (rclone dedupe).
   *
   * @see https://rclone.org/commands/rclone_dedupe/
   *
   * @param string $path  The path to check for duplicates.
   * @param string $mode  Deduplication strategy.
   * @param array  $flags Additional flags.
   *
   * @return object Object with 'success' status and 'stats' from the operation.
   */
  public function dedupe(string $path, string $mode = 'interactive', array $flags = []): object
  {
    $dedupe_flags = array_merge($flags, ['dedupe-mode' => $mode]);
    return $this->runAndGetStats('dedupe', [$this->left_side->backend($path)], $dedupe_flags);
  }
  
  /**
   * Cleans up the remote, removing old file versions or empty trash. (rclone cleanup)
   *
   * @see https://rclone.org/commands/rclone_cleanup/
   *
   * @param string|null $path  The path to clean up.
   * @param array       $flags Additional flags.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function cleanup(?string $path = null, array $flags = []): object
  {
    return $this->runAndGetStats('cleanup', [$this->left_side->backend($path)], $flags);
  }
  
  /**
   * Executes a backend-specific command (rclone backend).
   *
   * @see https://rclone.org/commands/rclone_backend/
   *
   * @param string      $command   The backend command to run.
   * @param string|null $path      The remote path for the command.
   * @param array       $options   Associative array of options.
   * @param array       $arguments Positional arguments for the command.
   *
   * @return string The raw output from the command.
   */
  public function backend(string $command, ?string $path = null, array $options = [], array $arguments = []): string
  {
    $command_array = [self::getBIN(), 'backend', $command];
    
    if ($path !== null) {
      $command_array[] = $this->left_side->backend($path);
    }
    
    foreach ($options as $key => $value) {
      $command_array[] = '-o';
      $command_array[] = "{$key}={$value}";
    }
    
    if (!empty($arguments)) {
      array_push($command_array, ...$arguments);
    }
    
    $processManager = new ProcessManager();
    $finalEnvs = $this->allEnvs();
    $completedProcess = $processManager->run($command_array, $finalEnvs);
    
    return trim($completedProcess->getOutput());
  }
  
  /**
   * Gets the left-side (source) provider.
   *
   * @return Provider The left-side provider instance.
   */
  public function getLeftSide() : Provider
  {
    return $this->left_side;
  }
  
  /**
   * Sets the left-side (source) provider.
   *
   * @param Provider $left_side The provider instance.
   */
  public function setLeftSide(Provider $left_side) : void
  {
    $this->left_side = $left_side;
  }
  
  /**
   * Gets the right-side (destination) provider.
   *
   * @return Provider The right-side provider instance.
   */
  public function getRightSide() : Provider
  {
    return $this->right_side;
  }
  
  /**
   * Sets the right-side (destination) provider.
   *
   * @param Provider $right_side The provider instance.
   */
  public function setRightSide(Provider $right_side) : void
  {
    $this->right_side = $right_side;
  }
}
