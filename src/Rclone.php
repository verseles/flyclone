<?php


namespace Verseles\Flyclone;

use Verseles\Flyclone\Exception\DirectoryNotFoundException;
use Verseles\Flyclone\Exception\FatalErrorException;
use Verseles\Flyclone\Exception\FileNotFoundException;
use Verseles\Flyclone\Exception\LessSeriousErrorException;
use Verseles\Flyclone\Exception\MaxTransferReachedException;
use Verseles\Flyclone\Exception\NoFilesTransferredException;
use Verseles\Flyclone\Exception\ProcessTimedOutException;
use Verseles\Flyclone\Exception\SyntaxErrorException;
use Verseles\Flyclone\Exception\TemporaryErrorException;
use Verseles\Flyclone\Exception\UnknownErrorException;
use Verseles\Flyclone\Parser\ProgressParser;
use Verseles\Flyclone\Parser\StatsParser;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\ProviderInterface;
use Verseles\Flyclone\Util\DurationConverter;
use Verseles\Flyclone\Util\SizeConverter;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutExceptionAlias;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Rclone
{
  /** Default timeout for rclone processes in seconds */
  private const DEFAULT_TIMEOUT = 120;

  /** Default idle timeout for rclone processes in seconds */
  private const DEFAULT_IDLE_TIMEOUT = 100;

  /** Regex for parsing rclone version string */
  private const VERSION_REGEX = '/rclone\sv(.+)/m';

  /** Path to the rclone executable (cached) */
  private static string $BIN;

  /** The 'source' provider for rclone operations */
  private ProviderInterface $left_side;

  /** The 'destination' provider. Can be the same as left_side */
  private ProviderInterface $right_side;

  /** Process timeout in seconds for this instance */
  private int $timeout;

  /** Process idle timeout in seconds for this instance */
  private int $idleTimeout;

  /** Rclone flags for this instance */
  private array $flags;

  /** Custom environment variables for this instance */
  private array $envs;

  /** Input string for rclone commands (e.g., for rcat) */
  private string $input = '';

  /** Object to store rclone progress information */
  private object $progress;

  /**
   * Creates a new RcloneBuilder for fluent configuration.
   *
   * @param ProviderInterface $leftSide The primary (source) provider
   * @return RcloneBuilder Builder instance for fluent configuration
   *
   * @example
   * ```php
   * $rclone = Rclone::create($s3Provider)
   *     ->withTimeout(300)
   *     ->withFlags(['verbose' => true])
   *     ->build();
   * ```
   */
  public static function create(ProviderInterface $leftSide): RcloneBuilder
  {
    return new RcloneBuilder($leftSide);
  }

  /**
   * Constructor for Rclone.
   *
   * Prefer using Rclone::create() for a fluent builder pattern.
   *
   * @param ProviderInterface      $leftSide    The primary (source) provider
   * @param ProviderInterface|null $rightSide   The secondary (destination) provider. If null, defaults to $leftSide
   * @param int                    $timeout     Process timeout in seconds (default: 120)
   * @param int                    $idleTimeout Process idle timeout in seconds (default: 100)
   * @param array                  $flags       Rclone flags (e.g., ['verbose' => true])
   * @param array                  $envs        Custom environment variables
   */
  public function __construct(
    ProviderInterface $leftSide,
    ?ProviderInterface $rightSide = null,
    int $timeout = self::DEFAULT_TIMEOUT,
    int $idleTimeout = self::DEFAULT_IDLE_TIMEOUT,
    array $flags = [],
    array $envs = []
  ) {
    $this->left_side = $leftSide;
    $this->right_side = $rightSide ?? $leftSide;
    $this->timeout = $timeout;
    $this->idleTimeout = $idleTimeout;
    $this->flags = $flags;
    $this->envs = $envs;
    $this->resetProgress();
  }

  /**
   * Gets the process timeout value for this instance.
   *
   * @return int Timeout in seconds
   */
  public function getTimeout(): int
  {
    return $this->timeout;
  }

  /**
   * Gets the process idle timeout value for this instance.
   *
   * @return int Idle timeout in seconds
   */
  public function getIdleTimeout(): int
  {
    return $this->idleTimeout;
  }

  /**
   * Gets the rclone flags for this instance.
   *
   * @return array Array of flags
   */
  public function getFlags(): array
  {
    return $this->flags;
  }

  /**
   * Gets the custom environment variables for this instance.
   *
   * @return array Array of environment variables
   */
  public function getEnvs(): array
  {
    return $this->envs;
  }

  /**
   * Gets the input string for rclone commands.
   *
   * @return string The input string
   */
  public function getInput(): string
  {
    return $this->input;
  }

  /**
   * Sets the input string for rclone commands like 'rcat'.
   *
   * @param string $input The input string
   * @return self
   */
  public function setInput(string $input): self
  {
    $this->input = $input;

    return $this;
  }

  /**
   * Checks if the left-side provider is directory-agnostic (does not support empty directories).
   *
   * @return bool True if directory-agnostic, false otherwise
   */
  public function isLeftSideDirAgnostic(): bool
  {
    return $this->getLeftSide()->isDirAgnostic();
  }

  /**
   * Checks if the right-side provider is directory-agnostic.
   *
   * @return bool True if directory-agnostic, false otherwise
   */
  public function isRightSideDirAgnostic(): bool
  {
    return $this->getRightSide()->isDirAgnostic();
  }

  /**
   * Checks if the left-side provider treats buckets as directories.
   *
   * @return bool True if buckets are treated as directories, false otherwise
   */
  public function isLeftSideBucketAsDir(): bool
  {
    return $this->getLeftSide()->isBucketAsDir();
  }

  /**
   * Checks if the right-side provider treats buckets as directories.
   *
   * @return bool True if buckets are treated as directories, false otherwise
   */
  public function isRightSideBucketAsDir(): bool
  {
    return $this->getRightSide()->isBucketAsDir();
  }

  /**
   * Checks if the left-side provider lists contents as a flat tree (all items at once).
   *
   * @return bool True if it lists as a tree, false otherwise
   */
  public function isLeftSideListsAsTree(): bool
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
   * - Removes leading '--' from keys.
   * - Replaces hyphens '-' with underscores '_' in keys.
   * - Converts keys to uppercase.
   * - Ensures the final key starts with the provided $prefix, avoiding duplication like "RCLONE_RCLONE_".
   * - If a key already starts with "RCLONE_", and $prefix is more specific (e.g., "RCLONE_CONFIG_REMOTE_"),
   *   it correctly forms a key like "RCLONE_CONFIG_REMOTE_KEYNAME".
   * - Converts boolean values to "true" or "false" strings. All other values are cast to string.
   *
   * Example:
   *   prefix_flags(['my-flag' => true], 'RCLONE_')
   *     // Result: ['RCLONE_MY_FLAG' => 'true']
   *   prefix_flags(['RCLONE_VERBOSE' => true], 'RCLONE_')
   *     // Result: ['RCLONE_VERBOSE' => 'true'] (no double "RCLONE_")
   *   prefix_flags(['timeout' => 30], 'RCLONE_CONFIG_MYREMOTE_')
   *     // Result: ['RCLONE_CONFIG_MYREMOTE_TIMEOUT' => '30']
   *   prefix_flags(['RCLONE_TIMEOUT' => 30], 'RCLONE_CONFIG_MYREMOTE_')
   *     // Result: ['RCLONE_CONFIG_MYREMOTE_TIMEOUT' => '30']
   *
   * @param array  $arr    The input array of flags or parameters.
   * @param string $prefix The prefix to apply (e.g., 'RCLONE_', 'RCLONE_CONFIG_MYREMOTE_').
   *
   * @return array The processed array with prefixed keys and string-cast values.
   */
  public static function prefix_flags(array $arr, string $prefix = 'RCLONE_') : array
  {
    $newArr = [];
    // Patterns to transform original keys:
    // 1. Remove leading '--' (e.g., '--retries' -> 'retries')
    // 2. Replace hyphens '-' with underscores '_' (e.g., 'max-depth' -> 'max_depth')
    $replace_patterns = ['/^--/m' => '', '/-/m' => '_',];
    
    foreach ($arr as $key => $value) {
      // Apply transformations to the key to get a "base" key name.
      // Example: '--log-level' becomes 'LOG_LEVEL', 'RCLONE_BUFFER_SIZE' remains 'RCLONE_BUFFER_SIZE'.
      $base_key = preg_replace(array_keys($replace_patterns), array_values($replace_patterns), (string) $key);
      $base_key = strtoupper($base_key);
      
      $final_env_var_name = '';
      // Check if the base_key already starts with the "RCLONE_" substring.
      if (str_starts_with($base_key, 'RCLONE_')) {
        // Case 1: base_key is 'RCLONE_SOME_FLAG'.
        if ($prefix === 'RCLONE_') {
          // If the target prefix is also just 'RCLONE_', the base_key is already correct.
          // Example: prefix_flags(['RCLONE_VERBOSE' => true], 'RCLONE_') -> 'RCLONE_VERBOSE'
          $final_env_var_name = $base_key;
        } else {
          // If the target prefix is more specific (e.g., 'RCLONE_CONFIG_MYREMOTE_'),
          // we want to use the specific prefix and the part of the base_key *after* "RCLONE_".
          // Example: prefix_flags(['RCLONE_TIMEOUT' => 30], 'RCLONE_CONFIG_MYREMOTE_')
          //          -> 'RCLONE_CONFIG_MYREMOTE_' + 'TIMEOUT'
          //          -> 'RCLONE_CONFIG_MYREMOTE_TIMEOUT'
          $final_env_var_name = $prefix . substr($base_key, strlen('RCLONE_'));
        }
      } else {
        // Case 2: base_key is 'SOME_FLAG' (does not start with 'RCLONE_').
        // Simply prepend the target prefix.
        // Example: prefix_flags(['verbose' => true], 'RCLONE_') -> 'RCLONE_VERBOSE'
        // Example: prefix_flags(['type' => 's3'], 'RCLONE_CONFIG_MYREMOTE_') -> 'RCLONE_CONFIG_MYREMOTE_TYPE'
        $final_env_var_name = $prefix . $base_key;
      }
      
      // Convert boolean values to their "true" or "false" string representations.
      if (is_bool($value)) {
        $processed_value = $value ? 'true' : 'false';
      } else {
        // Ensure all other values are cast to string for environment variables.
        $processed_value = (string) $value;
      }
      $newArr[$final_env_var_name] = $processed_value;
    }
    
    return $newArr;
  }
  
  /**
   * Consolidates all environment variables for the rclone process.
   * This includes forced variables, provider-specific flags, global flags,
   * custom environment variables, and operation-specific flags.
   * The order of `array_merge` determines precedence (later merges override earlier ones).
   * Precedence: Operation-Specific > Custom Envs > Global Flags > Provider Flags > Forced Vars.
   *
   * @param array $additional_operation_flags Flags specific to the current rclone operation (e.g., for copy, move).
   *
   * @return array An array of environment variables to be passed to Symfony Process.
   */
  private function allEnvs(array $additional_operation_flags = []) : array
  {
    // 1. Forced environment variables (lowest precedence).
    // Boolean 'true' is used explicitly, as rclone expects "true"/"false" strings.
    $env_vars = [
      'RCLONE_LOCAL_ONE_FILE_SYSTEM' => 'true', // Ensures rclone stays on one filesystem for local ops.
      'RCLONE_CONFIG' => '/dev/null',   // Instructs rclone not to use any external config file.
    ];
    
    // 2. Provider-specific flags.
    // This now correctly handles wrapped providers (like crypt) by calling their custom flags() method.
    $left_flags = $this->left_side->flags();
    $right_flags = $this->right_side->flags();
    $env_vars = array_merge($env_vars, $left_flags, $right_flags);
    
    // 3. Global flags (configured via constructor or builder).
    // These are general rclone flags, prefixed with 'RCLONE_'.
    // Rclone::prefix_flags() handles key transformation and boolean-to-string conversion.
    $env_vars = array_merge($env_vars, self::prefix_flags($this->flags, 'RCLONE_'));

    // 4. Custom environment variables (configured via constructor or builder).
    // These are assumed to be rclone parameters that need the 'RCLONE_' prefix.
    // Rclone::prefix_flags() handles key transformation and boolean-to-string conversion.
    $env_vars = array_merge($env_vars, self::prefix_flags($this->envs, 'RCLONE_'));
    
    // 5. Operation-specific flags (passed as $additional_operation_flags) (highest precedence).
    // These are flags specific to the rclone command being run (e.g., 'copy', 'sync').
    // Prefixed with 'RCLONE_'.
    // Rclone::prefix_flags() handles key transformation and boolean-to-string conversion.
    $env_vars = array_merge($env_vars, self::prefix_flags($additional_operation_flags, 'RCLONE_'));
    
    return $env_vars;
  }
  
  /**
   * Executes the given Symfony Process instance and handles exceptions.
   *
   * @param Process       $process    The process to execute.
   * @param callable|null $onProgress Optional callback for real-time progress.
   *
   * @return Process The completed process instance.
   * @throws SyntaxErrorException
   * @throws DirectoryNotFoundException
   * @throws FileNotFoundException
   * @throws TemporaryErrorException
   * @throws LessSeriousErrorException
   * @throws FatalErrorException
   * @throws MaxTransferReachedException
   * @throws NoFilesTransferredException
   * @throws UnknownErrorException
   * @throws ProcessTimedOutException
   */
  private function executeProcess(Process $process, ?callable $onProgress = null) : Process
  {
    try {
      if ($onProgress) {
        $process->mustRun(function ($type, $buffer) use ($onProgress) {
          $this->parseProgress($type, $buffer);
          $onProgress($type, $buffer);
        });
      } else {
        $process->mustRun();
      }
      return $process;
    }
    catch (ProcessFailedException $e) {
      $this->handleProcessFailure($e);
    }
    catch (SymfonyProcessTimedOutExceptionAlias $e) {
      throw new ProcessTimedOutException($e);
    }
    catch (\Exception $e) {
      throw new UnknownErrorException($e, 'An unexpected error occurred: ' . $e->getMessage());
    } finally {
      $this->input = '';
    }
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
    $process = new Process([self::getBIN(), 'obscure', $secret]);
    $process->setTimeout(3); // Short timeout for a quick operation.
    
    $process->mustRun(); // Throws exception on failure.
    
    return trim($process->getOutput()); // Returns the obscured string.
  }
  
  /**
   * Handles a failed process by throwing a specific exception based on the exit code.
   *
   * @param ProcessFailedException $exception The original exception.
   *
   * @throws SyntaxErrorException
   * @throws DirectoryNotFoundException
   * @throws FileNotFoundException
   * @throws TemporaryErrorException
   * @throws LessSeriousErrorException
   * @throws FatalErrorException
   * @throws MaxTransferReachedException
   * @throws NoFilesTransferredException
   * @throws UnknownErrorException
   */
  #[\NoReturn]
  private function handleProcessFailure(ProcessFailedException $exception): never
  {
    $process = $exception->getProcess();
    $code = $process->getExitCode();
    $msg = trim($process->getErrorOutput());

    // Fallback to a generic message if stderr is empty
    if (empty($msg)) {
      $msg = 'Rclone process failed. Stdout: ' . trim($process->getOutput());
    }

    $exitCode = RcloneExitCode::tryFrom((int) $code);

    // Map rclone exit codes to specific exceptions.
    throw match ($exitCode) {
      RcloneExitCode::SYNTAX_ERROR => new SyntaxErrorException($exception, $msg, (int) $code),
      RcloneExitCode::DIRECTORY_NOT_FOUND => new DirectoryNotFoundException($exception, $msg, (int) $code),
      RcloneExitCode::FILE_NOT_FOUND => new FileNotFoundException($exception, $msg, (int) $code),
      RcloneExitCode::TEMPORARY_ERROR => new TemporaryErrorException($exception, $msg, (int) $code),
      RcloneExitCode::LESS_SERIOUS_ERROR => new LessSeriousErrorException($exception, $msg, (int) $code),
      RcloneExitCode::FATAL_ERROR => new FatalErrorException($exception, $msg, (int) $code),
      RcloneExitCode::MAX_TRANSFER_REACHED => new MaxTransferReachedException($exception, $msg, (int) $code),
      RcloneExitCode::NO_FILES_TRANSFERRED => new NoFilesTransferredException($exception, $msg, (int) $code),
      default => new UnknownErrorException($exception, "Rclone error (Code: $code): $msg", (int) $code),
    };
  }
  
  /**
   * Centralized method to prepare and execute an rclone command.
   *
   * @param string        $command         The rclone command (e.g., 'lsjson', 'copy').
   * @param array         $args            Arguments for the command.
   * @param array         $operation_flags Additional operation flags.
   * @param callable|null $onProgress      Optional progress callback.
   *
   * @return Process The completed process instance.
   */
  private function _run(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = null): Process
  {
    $process_args = array_merge([self::getBIN(), $command], $args);
    $final_envs = $this->allEnvs($operation_flags);
    
    $process = new Process($process_args, sys_get_temp_dir(), $final_envs);
    $process->setTimeout($this->timeout);
    $process->setIdleTimeout($this->idleTimeout);

    if (!empty($this->input)) {
      $process->setInput($this->input);
    }
    
    return $this->executeProcess($process, $onProgress);
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
  private function simpleRun(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = null) : string
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
  private function runAndGetStats(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = null) : object
  {
    $env_options = $operation_flags;
    
    // Add stats logging flag to capture final summary on stderr
    $env_options['stats'] = '1s'; // Log stats every second to force final summary.
    // Force stats to be logged at NOTICE level so they are always available.
    $env_options['stats-log-level'] = 'NOTICE';
    
    if ($onProgress) {
      $this->resetProgress();
      // Force rclone to output progress, as it might not be running in an interactive tty.
      $env_options['progress'] = true;
    }
    
    $completedProcess = $this->_run($command, $args, $env_options, $onProgress);
    
    $stderr = $completedProcess->getErrorOutput();
    
    $stats = $this->parseFinalStats($stderr);
    
    // If stats parsing failed but the operation was a simple move/copy,
    // and was successful, assume 1 file and 0 bytes transferred.
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
   * Parses the final statistics block from rclone's stderr output.
   *
   * @param string $output The stderr output from rclone.
   *
   * @return object An object containing parsed statistics.
   */
  private function parseFinalStats(string $output): object
  {
    return StatsParser::parse($output);
  }
  
  /**
   * Converts a size string (e.g., "1.5GiB") to bytes.
   *
   * @param string $sizeStr The size string from rclone.
   * @return int The size in bytes.
   * @deprecated Use SizeConverter::toBytes() directly
   */
  private function convertSizeToBytes(string $sizeStr): int
  {
    return SizeConverter::toBytes($sizeStr);
  }

  /**
   * Converts a duration string (e.g., "1m33.4s") to seconds.
   *
   * @param string $durationStr The duration string.
   * @return float The duration in seconds.
   * @deprecated Use DurationConverter::toSeconds() directly
   */
  private function convertDurationToSeconds(string $durationStr): float
  {
    return DurationConverter::toSeconds($durationStr);
  }

  /**
   * Formats bytes into a human-readable string (KiB, MiB, etc.).
   *
   * @param int $bytes The number of bytes.
   * @return string The formatted string.
   * @deprecated Use SizeConverter::toHuman() directly
   */
  private function formatBytes(int $bytes): string
  {
    return SizeConverter::toHuman($bytes);
  }
  
  
  /**
   * Executes an rclone command targeting a single provider path.
   * This is a helper for non-transfer commands.
   *
   * @param string        $command    The rclone command.
   * @param string|null   $path       The path on the left-side provider.
   * @param array         $flags      Additional flags for the operation.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return string The output of the command.
   */
  private function directRun(string $command, ?string $path = null, array $flags = [], ?callable $onProgress = null): string
  {
    return $this->simpleRun($command, [
      $this->left_side->backend($path), // Builds the path like 'myremote:path/to/file'
    ],                            $flags, $onProgress);
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
  private function directTwinRun(string $command, ?string $left_path = null, ?string $right_path = null, array $flags = [], ?callable $onProgress = null): string
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
  public function version(bool $numeric = false): string|float
  {
    $cmd_output = $this->simpleRun('version'); // Executes 'rclone version'.

    // Parses version string like "rclone v1.2.3"
    preg_match_all(self::VERSION_REGEX, $cmd_output, $version_matches, PREG_SET_ORDER, 0);

    if (isset($version_matches[0][1])) {
      $version_string = $version_matches[0][1];
      return $numeric ? (float) $version_string : $version_string;
    }
    return $numeric ? 0.0 : ''; // Should not happen with a valid rclone installation.
  }
  
  /**
   * Gets the path to the rclone binary.
   *
   * @return string Path to rclone.
   */
  public static function getBIN() : string
  {
    return self::$BIN ?? self::guessBIN(); // Uses cached path or guesses it.
  }
  
  /**
   * Sets the path to the rclone binary.
   *
   * @param string $BIN Path to rclone.
   */
  public static function setBIN(string $BIN) : void
  {
    self::$BIN = $BIN;
  }
  
  /**
   * Tries to find the rclone binary in common system paths.
   * Uses spatie/once to ensure it runs only once.
   *
   * @return string Path to rclone.
   * @throws \RuntimeException If rclone binary is not found.
   */
  public static function guessBIN() : string
  {
    // spatie/once ensures this heavy search operation runs only once.
    $BIN_path = once(static function () {
      $finder = new ExecutableFinder();
      $rclone_path = $finder->find('rclone', '/usr/bin/rclone', [
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/local/sbin',
        '/var/lib/snapd/snap/bin', // Common path for snap installations
      ]);
      if ($rclone_path === null) {
        throw new \RuntimeException('Rclone binary not found. Please ensure rclone is installed and in your PATH, or set the path manually using Rclone::setBIN().');
      }
      return $rclone_path;
    });
    
    self::setBIN($BIN_path); // Cache the found path.
    
    return self::getBIN();
  }
  
  /**
   * Parses rclone progress output.
   * This method is called internally when a progress callback is active.
   *
   * @param string $type   The type of output (Process::OUT or Process::ERR).
   * @param string $buffer The output buffer content.
   */
  private function parseProgress(string $type, string $buffer): void
  {
    // Rclone progress output is expected on STDOUT (Process::OUT).
    if ($type !== Process::OUT) {
      return;
    }

    $parsed = ProgressParser::parse($buffer);
    if ($parsed !== null) {
      $this->progress = $parsed;
    }
  }
  
  /**
   * Sets the internal progress object with parsed data.
   *
   * @deprecated Use ProgressParser::parse() instead
   */
  private function setProgressData(string $raw, string $dataSent, string $dataTotal, int $sentPercentage, string $speed, string $eta, ?string $xfr = '1/1'): void
  {
    $this->progress = (object) [
      'raw' => trim($raw),
      'dataSent' => trim($dataSent),
      'dataTotal' => trim($dataTotal),
      'sent' => $sentPercentage,
      'speed' => trim($speed),
      'eta' => trim($eta),
      'xfr' => $xfr ?? '1/1',
    ];
  }
  
  /**
   * Gets the current progress object.
   *
   * @return object The progress object.
   */
  public function getProgress() : object
  {
    return $this->progress;
  }
  
  /**
   * Resets the progress object to its default state.
   */
  private function resetProgress(): void
  {
    $this->progress = ProgressParser::getDefault();
  }
  
  
  /**
   * Lists objects at the source path. (rclone lsjson)
   *
   * @param string $path  Path to list.
   * @param array  $flags Additional flags.
   *
   * @return array Array of objects, each representing a file or directory.
   *               ModTime is converted to UNIX timestamp.
   * @throws \JsonException If JSON decoding fails.
   */
  public function ls(string $path, array $flags = []) : array
  {
    $result_json = $this->simpleRun('lsjson', [$this->left_side->backend($path)], $flags);
    
    $items_array = json_decode($result_json, false, 512, JSON_THROW_ON_ERROR);
    
    // Process ModTime for each item
    foreach ($items_array as $item) {
      if (isset($item->ModTime) && is_string($item->ModTime)) {
        // Rclone's ModTime format is like "2023-08-15T10:20:30.123456789Z"
        // PHP strtotime handles this format well, especially RFC3339_EXTENDED.
        // Removing excessive nanoseconds for broader compatibility if necessary.
        $time_string = preg_replace('/\.(\d{6})\d*Z$/', '.$1Z', $item->ModTime);
        $timestamp = strtotime($time_string);
        $item->ModTime = ($timestamp !== false) ? $timestamp : null;
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
   * This method lists the parent directory and then filters for the specific item.
   *
   * @param string $path The path to check.
   * @param string $type The type to check for ('file' or 'dir').
   *
   * @return object An object with properties:
   *                - bool 'exists': True if the item exists and matches the type.
   *                - mixed 'details': The item's details from 'lsjson' if it exists, else empty array.
   *                - mixed 'error': The Exception object if an error occurred during 'ls', else empty string.
   */
  public function exists(string $path, string $type) : object
  {
    $dirname = dirname($path);
    // If dirname is '.', it means the path is at the remote's root.
    // rclone lsjson remote: needs just 'remote:' for root, not 'remote:.'
    if ($dirname === '.') {
      $dirname = ''; // For root listing
    }
    $basename = basename($path);
    
    try {
      $listing = $this->ls($dirname); // List parent directory contents.
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
      // If ls fails (e.g., parent directory not found), the item doesn't exist or is inaccessible.
      return (object) ['exists' => false, 'details' => [], 'error' => $e];
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
  public function touch(string $path, array $flags = [], ?callable $onProgress = null) : bool
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
  public function mkdir(string $path, array $flags = [], ?callable $onProgress = null) : bool
  {
    $this->directRun('mkdir', $path, $flags, $onProgress);
    return true;
  }
  
  /**
   * Removes an empty directory. (rclone rmdir)
   * Will not remove the path if it has any objects in it.
   *
   * @see https://rclone.org/commands/rclone_rmdir/
   *
   * @param string        $path       Path to remove.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  public function rmdir(string $path, array $flags = [], ?callable $onProgress = null) : bool
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
  public function rmdirs(string $path, array $flags = [], ?callable $onProgress = null) : bool
  {
    $this->directRun('rmdirs', $path, $flags, $onProgress);
    return true;
  }
  
  /**
   * Removes the path and all its contents. (rclone purge)
   * Does not obey include/exclude filters.
   *
   * @see https://rclone.org/commands/rclone_purge/
   *
   * @param string        $path       Path to purge.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function purge(string $path, array $flags = [], ?callable $onProgress = null) : object
  {
    return $this->runAndGetStats('purge', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  /**
   * Removes the files in path. (rclone delete)
   * Obeys include/exclude filters. Leaves directory structure.
   *
   * @see https://rclone.org/commands/rclone_delete/
   *
   * @param string|null   $path       Path containing files to delete.
   * @param array         $flags      Additional flags (e.g. --include, --exclude).
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function delete(?string $path = null, array $flags = [], ?callable $onProgress = null): object
  {
    return $this->runAndGetStats('delete', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  /**
   * Removes a single file from remote. (rclone deletefile)
   * Does not obey filters. Cannot remove a directory.
   *
   * @see https://rclone.org/commands/rclone_deletefile/
   *
   * @param string        $path       Path to the file to delete.
   * @param array         $flags      Additional flags.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function deletefile(string $path, array $flags = [], ?callable $onProgress = null) : object
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
  public function size(?string $path = null, array $flags = [], ?callable $onProgress = null): object
  {
    // Ensure --json flag is added for parsable output.
    $size_flags = array_merge($flags, ['json' => 'true']);
    $result_json = $this->simpleRun('size', [$this->left_side->backend($path)], $size_flags, $onProgress);

    return json_decode($result_json, false, 512, JSON_THROW_ON_ERROR);
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
  public function cat(string $path, array $flags = [], ?callable $onProgress = null) : string
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
  public function rcat(string $path, string $input, array $flags = [], ?callable $onProgress = null) : object
  {
    $this->setInput($input);
    return $this->runAndGetStats('rcat', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  
  /**
   * Uploads a single local file to a remote path using the 'moveto' command for efficiency.
   * This effectively moves the local file to the remote (copies then deletes local original).
   *
   * @param string        $local_path  Path to the local file.
   * @param string        $remote_path Destination path on the remote (current Rclone instance's left_side).
   * @param array         $flags       Additional flags.
   * @param callable|null $onProgress  Optional progress callback.
   *
   * @return object Object with 'success' status and 'stats'.
   */
  public function upload_file(string $local_path, string $remote_path, array $flags = [], ?callable $onProgress = null) : object
  {
    // Create a temporary Rclone setup: local provider as source, current Rclone's left_side as destination.
    $uploader = new self(left_side: new LocalProvider('local_temp_upload'), right_side: $this->left_side);
    
    // Use moveto for direct transfer. Rclone 'moveto' from 'local:' to a remote deletes the original local file.
    return $uploader->moveto($local_path, $remote_path, $flags, $onProgress);
  }
  
  /**
   * Downloads a file from a remote path to local storage.
   *
   * @param string    $remote_path            The path of the file on the remote server (current Rclone instance's left_side).
   * @param ?string   $local_destination_path The local path where the file should be saved.
   *                                          If a directory, original filename is used.
   *                                          If null, a temporary directory with original filename is used.
   * @param array     $flags                  Additional flags for the download operation.
   * @param ?callable $onProgress             A callback function to track download progress.
   *
   * @return object The result object from the copy operation, with an added `local_path` property on success.
   */
  public function download_to_local(string $remote_path, ?string $local_destination_path = null, array $flags = [], ?callable $onProgress = null): object
  {
    $remote_filename = basename($remote_path);

    if ($local_destination_path === null) {
      // Create a temporary directory and append the remote filename.
      $temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_download_' . uniqid();
      if (!mkdir($temp_dir, 0700, true) && !is_dir($temp_dir)) {
        // @codeCoverageIgnoreStart
        // This case is hard to test reliably without manipulating system permissions.
        throw new \RuntimeException("Failed to create temporary directory: $temp_dir");
        // @codeCoverageIgnoreEnd
      }
      $final_local_path = $temp_dir . DIRECTORY_SEPARATOR . $remote_filename;
    } elseif (is_dir($local_destination_path)) {
      // If a directory is provided, append the remote filename.
      $final_local_path = rtrim($local_destination_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $remote_filename;
    } else {
      // A specific file path is provided. Ensure parent directory exists.
      $parent_dir = dirname($local_destination_path);
      if (!is_dir($parent_dir)) {
        if (!mkdir($parent_dir, 0700, true) && !is_dir($parent_dir)) {
          // @codeCoverageIgnoreStart
          throw new \RuntimeException("Failed to create parent directory for download: $parent_dir");
          // @codeCoverageIgnoreEnd
        }
      }
      $final_local_path = $local_destination_path;
    }
    
    // Temporary Rclone setup: current Rclone's left_side as source, local provider as destination.
    $downloader = new self(left_side: $this->left_side, right_side: new LocalProvider('local_temp_download'));
    
    // Use copyto for direct file-to-file transfer.
    $result = $downloader->copyto($remote_path, $final_local_path, $flags, $onProgress);
    
    if ($result->success) {
      $result->local_path = $final_local_path;
    }
    
    return $result;
  }
  
  /**
   * Copies files from source to dest, skipping already copied. (rclone copy)
   * Source is a file/directory on left_side, dest_DIR_path is a directory on right_side.
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
  public function copy(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = null) : object
  {
    return $this->runAndGetStats('copy', [$this->left_side->backend($source_path), $this->right_side->backend($dest_DIR_path)], $flags, $onProgress);
  }
  
  /**
   * Copies a single file or directory from source to a specific destination file/directory path. (rclone copyto)
   * If src is a file, dst is a file. If src is a dir, dst is a dir.
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
  public function copyto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = null) : object
  {
    return $this->runAndGetStats('copyto', [$this->left_side->backend($source_path), $this->right_side->backend($dest_path)], $flags, $onProgress);
  }
  
  /**
   * Moves files from source to dest. (rclone move)
   * Source is a file/directory on left_side, dest_DIR_path is a directory on right_side.
   * Deletes original files from source after successful transfer.
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
  public function move(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = null) : object
  {
    return $this->runAndGetStats('move', [$this->left_side->backend($source_path), $this->right_side->backend($dest_DIR_path)], $flags, $onProgress);
  }
  
  /**
   * Moves file or directory from source to a specific destination file/directory path. (rclone moveto)
   * If source:path is a file or directory, it moves it to a file or directory named dest:path.
   * This can be used to rename files or upload single files with names different from existing ones.
   * Deletes original from source after successful transfer.
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
  public function moveto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = null) : object
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
  public function sync(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = null) : object
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
   * @return bool True if check succeeds (typically means no differences or errors based on flags).
   *              Note: rclone 'check' has specific exit codes for differences found. This method
   *              will throw an exception if rclone exits non-zero, unless specific
   *              error handling flags like --one-way and --differ are used and result in exit code 0.
   */
  public function check(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = null) : bool
  {
    // Rclone 'check' can exit non-zero if differences are found.
    // The 'simpleRun' method will throw an exception for non-zero exit codes.
    // For 'check', a successful run (exit code 0) means no differences were found (or ignored by flags).
    $this->directTwinRun('check', $source_path, $dest_path, $flags, $onProgress);
    return true; // If directTwinRun doesn't throw, it means rclone exited with 0.
  }
  
  /**
   * Gets quota information from the provider (rclone about).
   *
   * @see https://rclone.org/commands/rclone_about/
   *
   * @param string|null $path  Path on the provider. Some providers require this.
   * @param array       $flags Additional flags for the operation.
   *
   * @return object An object with quota details (total, used, free, etc.).
   * @throws \JsonException
   */
  public function about(?string $path = null, array $flags = []): object
  {
    $flags['json'] = true; // Force JSON output for parsing.
    $result_json = $this->simpleRun('about', [$this->left_side->backend($path)], $flags);
    return json_decode($result_json, false, 512, JSON_THROW_ON_ERROR);
  }
  
  /**
   * Lists the contents of a path in a tree-like format (rclone tree).
   *
   * @see https://rclone.org/commands/rclone_tree/
   *
   * @param string|null $path  The root path to list from.
   * @param array       $flags Additional rclone flags (e.g., ['max-depth' => 2]).
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
   * @param string $mode  Deduplication strategy (e.g., 'newest', 'oldest', 'rename'). Default is 'interactive'.
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
   * This provides a generic way to access commands not exposed as dedicated methods.
   * E.g., `rclone backend drives drive:` becomes `backend('drives', null)`
   *
   * @see https://rclone.org/commands/rclone_backend/
   *
   * @param string      $command   The backend command to run (e.g., 'drives', 'get-url').
   * @param string|null $path      The remote path for the command.
   * @param array       $options   Associative array of options (e.g., ['option' => 'value'] becomes --option=value).
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
      // Correct syntax for backend options is `-o key=value` or `--option key=value`
      $command_array[] = '-o';
      $command_array[] = "{$key}={$value}";
    }
    
    if (!empty($arguments)) {
      array_push($command_array, ...$arguments);
    }
    
    $final_envs = $this->allEnvs();
    $process = new Process($command_array, sys_get_temp_dir(), $final_envs);
    $process->setTimeout($this->timeout);
    $process->setIdleTimeout($this->idleTimeout);
    
    $completedProcess = $this->executeProcess($process);
    
    return trim($completedProcess->getOutput());
  }
  
  /**
   * Gets the left-side (source) provider.
   *
   * @return ProviderInterface The left-side provider instance.
   */
  public function getLeftSide(): ProviderInterface
  {
    return $this->left_side;
  }

  /**
   * Sets the left-side (source) provider.
   *
   * @param ProviderInterface $left_side The provider instance.
   */
  public function setLeftSide(ProviderInterface $left_side): void
  {
    $this->left_side = $left_side;
  }

  /**
   * Gets the right-side (destination) provider.
   *
   * @return ProviderInterface The right-side provider instance.
   */
  public function getRightSide(): ProviderInterface
  {
    return $this->right_side;
  }

  /**
   * Sets the right-side (destination) provider.
   *
   * @param ProviderInterface $right_side The provider instance.
   */
  public function setRightSide(ProviderInterface $right_side): void
  {
    $this->right_side = $right_side;
  }
}