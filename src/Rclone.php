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
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\Provider;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutExceptionAlias;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Rclone
{
  
  private static string $BIN; // Path to the rclone executable.
  private Provider      $left_side; // The 'source' provider for rclone operations.
  private Provider      $right_side; // The 'destination' provider. Can be the same as left_side.
  
  private static int    $timeout     = 120; // Default timeout for rclone processes in seconds.
  private static int    $idleTimeout = 100; // Default idle timeout for rclone processes in seconds.
  private static array  $flags       = [];  // Global rclone flags to be applied to all commands.
  private static array  $envs        = [];  // Custom environment variables (usually rclone parameters).
  private static string $input       = '';  // Input string to be passed to rclone commands (e.g., for rcat).
  private object        $progress;          // Object to store rclone progress information.
  private static array  $reset       = [    // Default values for resetting static properties.
                                            'timeout' => 120,
                                            'idleTimeout' => 100,
                                            'flags' => [],
                                            'envs' => [],
                                            'input' => '',
                                            'progress' => [ // Default structure for the progress object.
                                                            'raw' => '',
                                                            'dataSent' => '0 B', // Initialize with string value
                                                            'dataTotal' => '0 B', // Initialize with string value
                                                            'sent' => 0,
                                                            'speed' => '0 B/s', // Initialize with string value
                                                            'eta' => '-', // Initialize with string value
                                                            'xfr' => '0/0',      // Initialize with string value
                                            ],
  ];
  
  /**
   * Constructor for Rclone.
   *
   * @param Provider      $left_side  The primary (source) provider.
   * @param Provider|null $right_side The secondary (destination) provider. If null, defaults to $left_side.
   */
  public function __construct(Provider $left_side, ?Provider $right_side = NULL)
  {
    $this->reset(); // Initialize/reset static properties to defaults.
    
    $this->setLeftSide($left_side);
    // If no right_side provider is given, rclone operations will target the left_side provider itself (e.g., moving files within the same remote).
    $this->setRightSide($right_side ?? $left_side);
  }
  
  /**
   * Resets static configuration properties to their default values.
   * Also resets the progress object.
   */
  private function reset() : void
  {
    self::setTimeout(self::$reset['timeout']);
    self::setIdleTimeout(self::$reset['idleTimeout']);
    self::setFlags(self::$reset['flags']);
    self::setEnvs(self::$reset['envs']);
    self::setInput(self::$reset['input']);
    
    $this->resetProgress(); // Resets the progress tracking object.
  }
  
  /**
   * Gets the current process timeout value.
   *
   * @return int Timeout in seconds.
   */
  public static function getTimeout() : int
  {
    return self::$timeout;
  }
  
  /**
   * Sets the process timeout value.
   *
   * @param int $timeout Timeout in seconds.
   */
  public static function setTimeout(int $timeout) : void
  {
    self::$timeout = $timeout;
  }
  
  /**
   * Gets the current process idle timeout value.
   *
   * @return int Idle timeout in seconds.
   */
  public static function getIdleTimeout() : int
  {
    return self::$idleTimeout;
  }
  
  /**
   * Sets the process idle timeout value.
   *
   * @param int $idleTimeout Idle timeout in seconds.
   */
  public static function setIdleTimeout(int $idleTimeout) : void
  {
    self::$idleTimeout = $idleTimeout;
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
   * These are usually prefixed with 'RCLONE_' when passed to the process.
   * Example: ['BUFFER_SIZE' => '64M'] would become 'RCLONE_BUFFER_SIZE=64M' if default prefix is used.
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
    return self::$input;
  }
  
  /**
   * Sets the input string for rclone commands.
   *
   * @param string $input The input string.
   */
  public static function setInput(string $input) : void
  {
    self::$input = $input;
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
      
      $final_env_var_name;
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
    // These are prefixed like 'RCLONE_CONFIG_MYREMOTENAME_OPTION'.
    // Provider::flags() internally uses Rclone::prefix_flags(), so boolean conversion is handled.
    $env_vars = array_merge($env_vars, $this->left_side->flags());
    // $this->right_side is guaranteed to be set by the constructor ($right_side ?? $left_side)
    $env_vars = array_merge($env_vars, $this->right_side->flags());
    
    
    // 3. Global flags (set via Rclone::setFlags()).
    // These are general rclone flags, prefixed with 'RCLONE_'.
    // Rclone::prefix_flags() handles key transformation and boolean-to-string conversion.
    $env_vars = array_merge($env_vars, self::prefix_flags(self::getFlags(), 'RCLONE_'));
    
    // 4. Custom environment variables (set via Rclone::setEnvs()).
    // These are assumed to be rclone parameters that need the 'RCLONE_' prefix.
    // Rclone::prefix_flags() handles key transformation and boolean-to-string conversion.
    $env_vars = array_merge($env_vars, self::prefix_flags(self::getEnvs(), 'RCLONE_'));
    
    // 5. Operation-specific flags (passed as $additional_operation_flags) (highest precedence).
    // These are flags specific to the rclone command being run (e.g., 'copy', 'sync').
    // Prefixed with 'RCLONE_'.
    // Rclone::prefix_flags() handles key transformation and boolean-to-string conversion.
    $env_vars = array_merge($env_vars, self::prefix_flags($additional_operation_flags, 'RCLONE_'));
    
    return $env_vars;
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
   * Executes an rclone command and handles its output and potential errors.
   *
   * @param string        $command         The rclone command to execute (e.g., 'lsjson', 'copy').
   * @param array         $args            Arguments for the rclone command (e.g., source and destination paths).
   * @param array         $operation_flags Additional flags specific to this operation.
   * @param callable|null $onProgress      Optional callback for real-time progress updates.
   *                                       Receives ($type, $buffer) from Symfony Process.
   *
   * @return string The trimmed standard output from rclone.
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
  private function simpleRun(string $command, array $args = [], array $operation_flags = [], ?callable $onProgress = NULL) : string
  {
    $env_options = $operation_flags; // Start with operation-specific flags.
    if ($onProgress) {
      // Enable rclone progress output if a callback is provided.
      // RCLONE_STATS forces updates at the specified interval.
      $env_options += [
        'RCLONE_STATS_ONE_LINE' => 'true',
        'RCLONE_PROGRESS' => 'true',
        'RCLONE_STATS' => '250ms', // Request progress updates every 250 milliseconds.
      ];
    }
    
    // Consolidate all environment variables (provider, global, custom, operation-specific).
    $final_envs = $this->allEnvs($env_options);
    
    // Build the full rclone command line arguments.
    $process_args = array_merge([self::getBIN(), $command], $args);
    
    $process = new Process($process_args, sys_get_temp_dir(), $final_envs);
    
    $process->setTimeout(self::getTimeout());
    $process->setIdleTimeout(self::getIdleTimeout());
    if (!empty(self::getInput())) {
      $process->setInput(self::getInput()); // Set input for commands like 'rcat'.
    }
    
    try {
      if ($onProgress) {
        // Execute with progress callback.
        $process->mustRun(function ($type, $buffer) use ($onProgress) {
          $this->parseProgress($type, $buffer); // Parse progress internally.
          $onProgress($type, $buffer); // Call the user-supplied callback.
        });
      } else {
        // Execute without progress callback.
        $process->mustRun();
      }
      $this->reset(); // Reset static settings after successful execution.
      
      $output = $process->getOutput();
      
      return trim($output);
    }
    catch (ProcessFailedException $exception) {
      // Attempt to parse rclone's exit code and error message.
      // This regex tries to find "Exit Code: X" and the error output block.
      // It is less robust than directly getting these from the Process object if possible,
      // but works with the ProcessFailedException's message format.
      $regex = '/Exit\sCode:\s(\d+?).*Error\sOutput:.*?={10,20}\s(.*)/mis';
      preg_match_all($regex, $exception->getMessage(), $matches, PREG_SET_ORDER, 0);
      
      $processExitCode = $exception->getProcess()->getExitCode();
      $processErrorOutput = trim($exception->getProcess()->getErrorOutput());
      
      // Prefer direct process info if available, otherwise fallback to regex on message.
      $code = $processExitCode ?? null;
      $msg = !empty($processErrorOutput) ? $processErrorOutput : null;
      
      if ($code === null && isset($matches[0]) && count($matches[0]) === 3) {
        [, $parsedCode, $parsedMsg] = $matches[0];
        $code = (int) $parsedCode;
        $msg = trim($parsedMsg);
      } elseif ($code === null) {
        // If neither direct info nor regex provides a code, default to an unknown error scenario.
        throw new UnknownErrorException($exception, 'Rclone process failed with unknown exit code: ' . $exception->getMessage());
      }
      
      // If msg is still null/empty after attempts, use a generic part of the exception message.
      if (empty($msg)) {
        $msg = 'Rclone process failed. Output: ' . $exception->getProcess()->getOutput();
      }
      
      // Map rclone exit codes to specific exceptions.
      switch ((int) $code) {
        case 1:
          throw new SyntaxErrorException($exception, $msg, (int) $code);
        // case 2 is caught by the default UnknownErrorException
        case 3:
          throw new DirectoryNotFoundException($exception, $msg, (int) $code);
        case 4:
          throw new FileNotFoundException($exception, $msg, (int) $code);
        case 5:
          throw new TemporaryErrorException($exception, $msg, (int) $code);
        case 6:
          throw new LessSeriousErrorException($exception, $msg, (int) $code);
        case 7:
          throw new FatalErrorException($exception, $msg, (int) $code);
        case 8:
          throw new MaxTransferReachedException($exception, $msg, (int) $code);
        case 9:
          throw new NoFilesTransferredException($exception, $msg, (int) $code);
        default:
          throw new UnknownErrorException($exception, "Rclone error (Code: $code): $msg", (int) $code);
      }
    }
    catch (SymfonyProcessTimedOutExceptionAlias $exception) {
      // Handle Symfony's process timeout exception.
      throw new ProcessTimedOutException($exception);
    }
    catch (\Exception $exception) {
      // Catch any other unexpected exceptions.
      throw new UnknownErrorException($exception, 'An unexpected error occurred: ' . $exception->getMessage());
    }
  }
  
  /**
   * Executes an rclone command targeting a single provider path.
   *
   * @param string        $command    The rclone command.
   * @param string|null   $path       The path on the left-side provider.
   * @param array         $flags      Additional flags for the operation.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  protected function directRun(string $command, $path = NULL, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->simpleRun($command, [
      $this->left_side->backend($path), // Builds the path like 'myremote:path/to/file'
    ],               $flags, $onProgress);
    
    return TRUE;
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
   * @return bool True on success.
   */
  protected function directTwinRun(string $command, ?string $left_path = NULL, ?string $right_path = NULL, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->simpleRun($command,
                     [$this->left_side->backend($left_path), $this->right_side->backend($right_path)],
                     $flags,
                     $onProgress);
    
    return TRUE;
  }
  
  /**
   * Executes an rclone command that takes input via STDIN (e.g., rcat).
   *
   * @param string        $command    The rclone command.
   * @param string        $input      The string to pass to STDIN.
   * @param array         $args       Arguments for the rclone command (typically the destination path).
   * @param array         $flags      Additional flags for the operation.
   * @param callable|null $onProgress Optional progress callback.
   *
   * @return bool True on success.
   */
  private function inputRun(string $command, string $input, array $args = [], array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->setInput($input); // Set the input string.
    
    // Command arguments usually include the destination path for rcat.
    return (bool) $this->simpleRun($command, $args, $flags, $onProgress);
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
    $cmd_output = $this->simpleRun('version'); // Executes 'rclone version'.
    
    // Parses version string like "rclone v1.2.3"
    preg_match_all('/rclone\sv(.+)/m', $cmd_output, $version_matches, PREG_SET_ORDER, 0);
    
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
      if ($rclone_path === NULL) {
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
  private function parseProgress(string $type, string $buffer) : void
  {
    // Rclone progress output is expected on STDOUT (Process::OUT).
    // Example line: "Transferred: 1.234 GiB / 2.000 GiB, 61%, 12.345 MiB/s, ETA 1m2s"
    // Or with checks/errors: "Checks: 100 / 100, 100% | Transferred: 0 / 0, - | Errors: 1 (retrying may help)"
    // These regexes focus on the transfer part.
    if ($type === Process::OUT) {
      // Regex for transfer stats. Made more robust for units (KiB, MiB, GiB, TiB, B) and ETA (can be '-', 'Ns', etc.).
      // iu modifiers: case-insensitive, unicode.
      $regex_base = '([\d.]+\s[KMGT]?i?B)\s*\/\s*([\d.]+\s[KMGT]?i?B),\s*(\d+)\%,\s*([\d.]+\s[KMGT]?i?B\/s),\s*ETA\s*(\S+)';
      $regex = '/' . $regex_base . '/iu';
      $regex_xfr = '/' . $regex_base . '\s*\(xfr#(\d+\/\d+)\)/iu';
      
      $matches_xfr = [];
      $matches = [];
      
      preg_match($regex_xfr, $buffer, $matches_xfr); // Using preg_match as we expect at most one progress line per buffer chunk.
      
      if (isset($matches_xfr[0]) && count($matches_xfr) >= 7) {
        // raw, dataSent, dataTotal, sent (percentage), speed, eta, xfr_count
        $this->setProgressData($matches_xfr[0], $matches_xfr[1], $matches_xfr[2], (int) $matches_xfr[3], $matches_xfr[4], $matches_xfr[5], $matches_xfr[6]);
      } else {
        preg_match($regex, $buffer, $matches);
        if (isset($matches[0]) && count($matches) >= 6) {
          // raw, dataSent, dataTotal, sent (percentage), speed, eta
          $this->setProgressData($matches[0], $matches[1], $matches[2], (int) $matches[3], $matches[4], $matches[5]);
        }
      }
    }
  }
  
  /**
   * Sets the internal progress object with parsed data.
   *
   * @param string      $raw            The raw progress string.
   * @param string      $dataSent       Amount of data sent (e.g., "1.2 GiB").
   * @param string      $dataTotal      Total amount of data (e.g., "2.0 GiB").
   * @param int         $sentPercentage Percentage completed.
   * @param string      $speed          Current transfer speed (e.g., "10 MiB/s").
   * @param string      $eta            Estimated time remaining (e.g., "1m2s", "-", "0s").
   * @param string|null $xfr            Current transferring files count (e.g., "1/10"). Defaults to '1/1'.
   */
  private function setProgressData(string $raw, string $dataSent, string $dataTotal, int $sentPercentage, string $speed, string $eta, ?string $xfr = '1/1') : void
  {
    $this->progress = (object) [
      'raw' => trim($raw), // Trim the raw string
      'dataSent' => trim($dataSent),
      'dataTotal' => trim($dataTotal),
      'sent' => $sentPercentage, // Storing as integer percentage
      'speed' => trim($speed),
      'eta' => trim($eta),
      'xfr' => $xfr ?? '1/1', // Default if not provided (e.g., single file transfer)
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
  private function resetProgress() : void
  {
    // Initialize with default/empty values from self::$reset['progress']
    $this->progress = (object) self::$reset['progress'];
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
    
    $items_array = json_decode($result_json, FALSE, 512, JSON_THROW_ON_ERROR);
    
    // Process ModTime for each item
    foreach ($items_array as $item) {
      if (isset($item->ModTime) && is_string($item->ModTime)) {
        // Rclone's ModTime format is like "2023-08-15T10:20:30.123456789Z"
        // PHP strtotime handles this format well, especially RFC3339_EXTENDED.
        // Removing excessive nanoseconds for broader compatibility if necessary.
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
    return $this->directRun('touch', $path, $flags, $onProgress);
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
    return $this->directRun('mkdir', $path, $flags, $onProgress);
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
  public function rmdir(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('rmdir', $path, $flags, $onProgress);
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
    return $this->directRun('rmdirs', $path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function purge(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('purge', $path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function delete(?string $path = NULL, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('delete', $path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function deletefile(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('deletefile', $path, $flags, $onProgress);
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
    // Ensure --json flag is added for parsable output.
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
   * @return bool True on success.
   */
  public function rcat(string $path, string $input, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    // Arguments for rcat typically include only the destination path.
    return $this->inputRun('rcat', $input, [$this->left_side->backend($path)], $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function upload_file(string $local_path, string $remote_path, array $flags = [], ?callable $onProgress = NULL) : bool
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
   * @return string|false The absolute local path where the file was saved, or false if download fails.
   */
  public function download_to_local(string $remote_path, ?string $local_destination_path = NULL, array $flags = [], ?callable $onProgress = NULL) : string|false
  {
    $remote_filename = basename($remote_path);
    
    if ($local_destination_path === NULL) {
      // Create a temporary directory and append the remote filename.
      $temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_download_' . uniqid();
      if (!mkdir($temp_dir, 0777, TRUE) && !is_dir($temp_dir)) {
        // @codeCoverageIgnoreStart
        // This case is hard to test reliably without manipulating system permissions.
        return FALSE; // Failed to create temp directory
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
        if (!mkdir($parent_dir, 0777, TRUE) && !is_dir($parent_dir)) {
          // @codeCoverageIgnoreStart
          return FALSE; // Failed to create parent directory
          // @codeCoverageIgnoreEnd
        }
      }
      $final_local_path = $local_destination_path;
    }
    
    // Temporary Rclone setup: current Rclone's left_side as source, local provider as destination.
    $downloader = new self(left_side: $this->left_side, right_side: new LocalProvider('local_temp_download'));
    
    // Use copyto for direct file-to-file transfer.
    $success = $downloader->copyto($remote_path, $final_local_path, $flags, $onProgress);
    
    return $success ? $final_local_path : FALSE;
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
   * @return bool True on success.
   */
  public function copy(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('copy', $source_path, $dest_DIR_path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function copyto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('copyto', $source_path, $dest_path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function move(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('move', $source_path, $dest_DIR_path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function moveto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('moveto', $source_path, $dest_path, $flags, $onProgress);
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
   * @return bool True on success.
   */
  public function sync(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('sync', $source_path, $dest_path, $flags, $onProgress);
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
  public function check(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    // Rclone 'check' can exit non-zero if differences are found.
    // The 'simpleRun' method will throw an exception for non-zero exit codes.
    // For 'check', a successful run (exit code 0) means no differences were found (or ignored by flags).
    $this->directTwinRun('check', $source_path, $dest_path, $flags, $onProgress);
    return TRUE; // If directTwinRun doesn't throw, it means rclone exited with 0.
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