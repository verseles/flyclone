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
  
  private static string $BIN; // Caminho para o executável rclone.
  private Provider      $left_side; // O provedor 'origem' para as operações rclone.
  private Provider      $right_side; // O provedor 'destino'. Pode ser o mesmo que left_side.
  
  private static int    $timeout     = 120; // Timeout padrão para processos rclone em segundos.
  private static int    $idleTimeout = 100; // Timeout de inatividade padrão para processos rclone em segundos.
  private static array  $flags       = [];  // Flags globais do rclone a serem aplicadas a todos os comandos.
  private static array  $envs        = [];  // Variáveis de ambiente personalizadas (geralmente parâmetros rclone).
  private static string $input       = '';  // String de entrada a ser passada para comandos rclone (ex: para rcat).
  private object        $progress;          // Objeto para armazenar informações de progresso do rclone.
  private static array  $reset       = [    // Valores padrão para redefinir propriedades estáticas.
                                            'timeout' => 120,
                                            'idleTimeout' => 100,
                                            'flags' => [],
                                            'envs' => [],
                                            'input' => '',
                                            'progress' => [ // Estrutura padrão para o objeto de progresso.
                                                            'raw' => '',
                                                            'dataSent' => 0,
                                                            'dataTotal' => 0,
                                                            'sent' => 0,
                                                            'speed' => 0,
                                                            'eta' => 0,
                                                            'xfr' => '1/1',
                                            ],
  ];
  
  /**
   * Construtor para Rclone.
   *
   * @param Provider      $left_side  O provedor primário (origem).
   * @param Provider|null $right_side O provedor secundário (destino). Se null, assume $left_side.
   */
  public function __construct(Provider $left_side, ?Provider $right_side = NULL)
  {
    $this->reset(); // Inicializa/redefine as propriedades estáticas para os padrões.
    
    $this->setLeftSide($left_side);
    // Se nenhum provedor right_side for fornecido, as operações rclone terão como alvo o próprio provedor left_side (ex: mover arquivos dentro do mesmo remoto).
    $this->setRightSide($right_side ?? $left_side);
  }
  
  /**
   * Redefine as propriedades de configuração estáticas para seus valores padrão.
   * Também redefine o objeto de progresso.
   */
  private function reset() : void
  {
    self::setTimeout(self::$reset['timeout']);
    self::setIdleTimeout(self::$reset['idleTimeout']);
    self::setFlags(self::$reset['flags']);
    self::setEnvs(self::$reset['envs']);
    self::setInput(self::$reset['input']);
    
    $this->resetProgress(); // Redefine o objeto de rastreamento de progresso.
  }
  
  /**
   * Obtém o valor atual do timeout do processo.
   *
   * @return int Timeout em segundos.
   */
  public static function getTimeout() : int
  {
    return self::$timeout;
  }
  
  /**
   * Define o valor do timeout do processo.
   *
   * @param int $timeout Timeout em segundos.
   */
  public static function setTimeout(int $timeout) : void
  {
    self::$timeout = $timeout;
  }
  
  /**
   * Obtém o valor atual do timeout de inatividade do processo.
   *
   * @return int Timeout de inatividade em segundos.
   */
  public static function getIdleTimeout() : int
  {
    return self::$idleTimeout;
  }
  
  /**
   * Define o valor do timeout de inatividade do processo.
   *
   * @param int $idleTimeout Timeout de inatividade em segundos.
   */
  public static function setIdleTimeout(int $idleTimeout) : void
  {
    self::$idleTimeout = $idleTimeout;
  }
  
  /**
   * Obtém as flags rclone definidas globalmente.
   *
   * @return array Array de flags.
   */
  public static function getFlags() : array
  {
    return self::$flags;
  }
  
  /**
   * Define flags globais do rclone. Estas flags são aplicadas à maioria dos comandos rclone.
   * Exemplo: ['retries' => 3, 'verbose' => true]
   *
   * @param array $flags Array de flags. Booleano true será convertido para "true", false para "false".
   */
  public static function setFlags(array $flags) : void
  {
    self::$flags = $flags;
  }
  
  /**
   * Obtém as variáveis de ambiente personalizadas.
   *
   * @return array Array de variáveis de ambiente.
   */
  public static function getEnvs() : array
  {
    return self::$envs;
  }
  
  /**
   * Define variáveis de ambiente personalizadas, tipicamente usadas para parâmetros rclone.
   * Estas são usualmente prefixadas com 'RCLONE_' quando passadas para o processo.
   * Exemplo: ['BUFFER_SIZE' => '64M'] se tornaria 'RCLONE_BUFFER_SIZE=64M' se o prefixo padrão for usado.
   *
   * @param array $envs Array de variáveis de ambiente. Booleano true será convertido para "true", false para "false".
   */
  public static function setEnvs(array $envs) : void
  {
    self::$envs = $envs;
  }
  
  /**
   * Obtém a string de entrada para comandos rclone como 'rcat'.
   *
   * @return string A string de entrada.
   */
  public static function getInput() : string
  {
    return self::$input;
  }
  
  /**
   * Define a string de entrada para comandos rclone.
   *
   * @param string $input A string de entrada.
   */
  public static function setInput(string $input) : void
  {
    self::$input = $input;
  }
  
  /**
   * Verifica se o provedor do lado esquerdo é agnóstico a diretórios (não suporta diretórios vazios).
   *
   * @return bool True se for agnóstico a diretórios, false caso contrário.
   */
  public function isLeftSideDirAgnostic() : bool
  {
    return $this->getLeftSide()->isDirAgnostic();
  }
  
  /**
   * Verifica se o provedor do lado direito é agnóstico a diretórios.
   *
   * @return bool True se for agnóstico a diretórios, false caso contrário.
   */
  public function isRightSideDirAgnostic() : bool
  {
    return $this->getRightSide()->isDirAgnostic();
  }
  
  /**
   * Verifica se o provedor do lado esquerdo trata buckets como diretórios.
   *
   * @return bool True se buckets são tratados como diretórios, false caso contrário.
   */
  public function isLeftSideBucketAsDir() : bool
  {
    return $this->getLeftSide()->isBucketAsDir();
  }
  
  /**
   * Verifica se o provedor do lado direito trata buckets como diretórios.
   *
   * @return bool True se buckets são tratados como diretórios, false caso contrário.
   */
  public function isRightSideBucketAsDir() : bool
  {
    return $this->getRightSide()->isBucketAsDir();
  }
  
  /**
   * Verifica se o provedor do lado esquerdo lista conteúdos como uma árvore plana (todos os itens de uma vez).
   *
   * @return bool True se lista como uma árvore, false caso contrário.
   */
  public function isLeftSideListsAsTree() : bool
  {
    return $this->getLeftSide()->isListsAsTree();
  }
  
  /**
   * Verifica se o provedor do lado direito lista conteúdos como uma árvore plana.
   *
   * @return bool True se lista como uma árvore, false caso contrário.
   */
  public function isRightSideListsAsTree() : bool
  {
    return $this->getRightSide()->isListsAsTree();
  }
  
  
  /**
   * Adiciona um prefixo às chaves do array e transforma as chaves para compatibilidade com rclone.
   * Converte valores booleanos para strings "true" ou "false". Todos os valores são convertidos para string.
   * Exemplo: ['my-flag' => true] com prefixo 'RCLONE_' torna-se ['RCLONE_MY_FLAG' => 'true']
   *
   * @param array  $arr    O array de entrada de flags ou parâmetros.
   * @param string $prefix O prefixo a ser adicionado a cada chave (padrão: 'RCLONE_').
   *
   * @return array O array processado com chaves prefixadas e valores convertidos para string.
   */
  public static function prefix_flags(array $arr, string $prefix = 'RCLONE_') : array
  {
    $newArr = [];
    // Padrões para transformar chaves originais:
    // 1. Remover '--' iniciais (ex: '--retries' -> 'retries')
    // 2. Substituir hífens '-' por underscores '_' (ex: 'max-depth' -> 'max_depth')
    $replace_patterns = ['/^--/m' => '', '/-/m' => '_',];
    
    foreach ($arr as $key => $value) {
      // Aplicar transformações à chave
      $processed_key = preg_replace(array_keys($replace_patterns), array_values($replace_patterns), (string) $key);
      $processed_key = strtoupper($processed_key); // Converter chave para maiúsculas (ex: 'max_depth' -> 'MAX_DEPTH')
      
      // Converter valores booleanos para suas representações em string "true" ou "false"
      if (is_bool($value)) {
        $processed_value = $value ? 'true' : 'false';
      } else {
        // Garantir que todos os outros valores sejam convertidos para string para variáveis de ambiente
        $processed_value = (string) $value;
      }
      // Construir a nova chave com o prefixo e atribuir o valor processado
      $newArr[$prefix . $processed_key] = $processed_value;
    }
    
    return $newArr;
  }
  
  /**
   * Consolida todas as variáveis de ambiente para o processo rclone.
   * Isso inclui variáveis forçadas, flags específicas do provedor, flags globais,
   * variáveis de ambiente personalizadas e flags específicas da operação.
   * A ordem da mesclagem determina a precedência (mesclagens posteriores sobrescrevem as anteriores).
   * Precedência: Específicas da Operação > Envs Personalizadas > Flags Globais > Flags do Provedor > Vars Forçadas.
   *
   * @param array $additional_operation_flags Flags específicas para a operação rclone atual (ex: para copy, move).
   *
   * @return array Um array de variáveis de ambiente a ser passado para o Symfony Process.
   */
  private function allEnvs(array $additional_operation_flags = []) : array
  {
    // 1. Variáveis de ambiente forçadas (menor precedência).
    // O booleano 'true' é usado explicitamente, pois rclone espera strings "true"/"false".
    $env_vars = [
      'RCLONE_LOCAL_ONE_FILE_SYSTEM' => 'true', // Garante que rclone permaneça em um sistema de arquivos para operações locais.
      'RCLONE_CONFIG' => '/dev/null',   // Instrui rclone a não usar nenhum arquivo de configuração externo.
    ];
    
    // 2. Flags específicas do provedor.
    // São prefixadas como 'RCLONE_CONFIG_MYREMOTENAME_OPTION'.
    // Provider::flags() usa internamente Rclone::prefix_flags(), então a conversão de booleano é tratada.
    $env_vars = array_merge($env_vars, $this->left_side->flags());
    // $this->right_side é garantido estar definido pelo construtor ($right_side ?? $left_side)
    $env_vars = array_merge($env_vars, $this->right_side->flags());
    
    
    // 3. Flags globais (definidas via Rclone::setFlags()).
    // São flags gerais do rclone, prefixadas com 'RCLONE_'.
    // Rclone::prefix_flags() trata a transformação da chave e a conversão de booleano para string.
    $env_vars = array_merge($env_vars, self::prefix_flags(self::getFlags(), 'RCLONE_'));
    
    // 4. Variáveis de ambiente personalizadas (definidas via Rclone::setEnvs()).
    // Assume-se que são parâmetros rclone que precisam do prefixo 'RCLONE_'.
    // Rclone::prefix_flags() trata a transformação da chave e a conversão de booleano para string.
    $env_vars = array_merge($env_vars, self::prefix_flags(self::getEnvs(), 'RCLONE_'));
    
    // 5. Flags específicas da operação (passadas como $additional_operation_flags) (maior precedência).
    // São flags específicas para o comando rclone sendo executado (ex: 'copy', 'sync').
    // Prefixadas com 'RCLONE_'.
    // Rclone::prefix_flags() trata a transformação da chave e a conversão de booleano para string.
    $env_vars = array_merge($env_vars, self::prefix_flags($additional_operation_flags, 'RCLONE_'));
    
    return $env_vars;
  }
  
  
  /**
   * Ofusca uma senha ou segredo usando 'rclone obscure'.
   *
   * @param string $secret O segredo a ser ofuscado.
   *
   * @return string O segredo ofuscado.
   */
  public static function obscure(string $secret) : string
  {
    $process = new Process([self::getBIN(), 'obscure', $secret]);
    $process->setTimeout(3); // Timeout curto para uma operação rápida.
    
    $process->mustRun(); // Lança exceção em caso de falha.
    
    return trim($process->getOutput()); // Retorna a string ofuscada.
  }
  
  /**
   * Executa um comando rclone e trata sua saída e possíveis erros.
   *
   * @param string        $command         O comando rclone a ser executado (ex: 'lsjson', 'copy').
   * @param array         $args            Argumentos para o comando rclone (ex: caminhos de origem e destino).
   * @param array         $operation_flags Flags adicionais específicas para esta operação.
   * @param callable|null $onProgress      Callback opcional para atualizações de progresso em tempo real.
   *                                       Recebe ($type, $buffer) do Symfony Process.
   *
   * @return string A saída padrão trimada do rclone.
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
    $env_options = $operation_flags; // Começa com flags específicas da operação.
    if ($onProgress) {
      // Habilita a saída de progresso do rclone se um callback for fornecido.
      $env_options += ['RCLONE_STATS_ONE_LINE' => 'true', 'RCLONE_PROGRESS' => 'true'];
    }
    
    // Consolida todas as variáveis de ambiente (provedor, global, customizada, específica da operação).
    $final_envs = $this->allEnvs($env_options);
    
    // Constrói os argumentos completos da linha de comando rclone.
    $process_args = array_merge([self::getBIN(), $command], $args);
    
    $process = new Process($process_args, sys_get_temp_dir(), $final_envs);
    
    $process->setTimeout(self::getTimeout());
    $process->setIdleTimeout(self::getIdleTimeout());
    if (!empty(self::getInput())) {
      $process->setInput(self::getInput()); // Define a entrada para comandos como 'rcat'.
    }
    
    try {
      if ($onProgress) {
        // Executa com callback de progresso.
        $process->mustRun(function ($type, $buffer) use ($onProgress) {
          $this->parseProgress($type, $buffer); // Analisa o progresso internamente.
          $onProgress($type, $buffer); // Chama o callback fornecido pelo usuário.
        });
      } else {
        // Executa sem callback de progresso.
        $process->mustRun();
      }
      $this->reset(); // Redefine as configurações estáticas após execução bem-sucedida.
      
      $output = $process->getOutput();
      
      return trim($output);
    }
    catch (ProcessFailedException $exception) {
      // Tenta analisar o código de saída e a mensagem de erro do rclone.
      $regex = '/Exit\sCode:\s(\d+?).*Error\sOutput:.*?={10,20}\s(.*)/mis';
      preg_match_all($regex, $exception->getMessage(), $matches, PREG_SET_ORDER, 0);
      
      if (isset($matches[0]) && count($matches[0]) === 3) {
        [, $code, $msg] = $matches[0];
        $msg = trim($msg);
        // Mapeia códigos de saída rclone para exceções específicas.
        switch ((int) $code) {
          case 1:
            throw new SyntaxErrorException($exception, $msg, (int) $code);
          // case 2 é capturado pelo UnknownErrorException padrão
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
      } else {
        // Se a análise falhar, lança um UnknownErrorException genérico.
        throw new UnknownErrorException($exception, 'Rclone process failed: ' . $exception->getMessage());
      }
    }
    catch (SymfonyProcessTimedOutExceptionAlias $exception) {
      // Trata a exceção de timeout do processo do Symfony.
      throw new ProcessTimedOutException($exception);
    }
    catch (\Exception $exception) {
      // Captura quaisquer outras exceções inesperadas.
      throw new UnknownErrorException($exception, 'An unexpected error occurred: ' . $exception->getMessage());
    }
  }
  
  /**
   * Executa um comando rclone direcionado a um único caminho de provedor.
   *
   * @param string        $command    O comando rclone.
   * @param string|null   $path       O caminho no provedor do lado esquerdo.
   * @param array         $flags      Flags adicionais para a operação.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  protected function directRun(string $command, $path = NULL, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->simpleRun($command, [
      $this->left_side->backend($path), // Constrói o caminho como 'myremote:path/to/file'
    ],               $flags, $onProgress);
    
    return TRUE;
  }
  
  /**
   * Executa um comando rclone envolvendo dois caminhos de provedor (origem e destino).
   *
   * @param string        $command    O comando rclone.
   * @param string|null   $left_path  Caminho no provedor do lado esquerdo.
   * @param string|null   $right_path Caminho no provedor do lado direito.
   * @param array         $flags      Flags adicionais para a operação.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
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
   * Executa um comando rclone que recebe entrada via STDIN (ex: rcat).
   *
   * @param string        $command    O comando rclone.
   * @param string        $input      A string a ser passada para STDIN.
   * @param array         $args       Argumentos para o comando rclone (tipicamente o caminho de destino).
   * @param array         $flags      Flags adicionais para a operação.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  private function inputRun(string $command, string $input, array $args = [], array $flags = [], ?callable $onProgress = NULL) : bool
  {
    $this->setInput($input); // Define a string de entrada.
    
    // Os argumentos do comando geralmente incluem o caminho de destino para rcat.
    return (bool) $this->simpleRun($command, $args, $flags, $onProgress);
  }
  
  /**
   * Obtém a versão do rclone.
   *
   * @param bool $numeric Se true, tenta retornar uma versão numérica (float). Caso contrário, retorna string.
   *
   * @return string|float A versão do rclone.
   */
  public function version(bool $numeric = FALSE) : string|float
  {
    $cmd_output = $this->simpleRun('version'); // Executa 'rclone version'.
    
    // Analisa a string de versão como "rclone v1.2.3"
    preg_match_all('/rclone\sv(.+)/m', $cmd_output, $version_matches, PREG_SET_ORDER, 0);
    
    if (isset($version_matches[0][1])) {
      $version_string = $version_matches[0][1];
      return $numeric ? (float) $version_string : $version_string;
    }
    return $numeric ? 0.0 : ''; // Não deve acontecer com uma instalação válida do rclone.
  }
  
  /**
   * Obtém o caminho para o binário rclone.
   *
   * @return string Caminho para o rclone.
   */
  public static function getBIN() : string
  {
    return self::$BIN ?? self::guessBIN(); // Usa o caminho em cache ou o adivinha.
  }
  
  /**
   * Define o caminho para o binário rclone.
   *
   * @param string $BIN Caminho para o rclone.
   */
  public static function setBIN(string $BIN) : void
  {
    self::$BIN = $BIN;
  }
  
  /**
   * Tenta encontrar o binário rclone em caminhos comuns do sistema.
   * Usa spatie/once para garantir que execute apenas uma vez.
   *
   * @return string Caminho para o rclone.
   * @throws \RuntimeException Se o binário rclone não for encontrado.
   */
  public static function guessBIN() : string
  {
    // spatie/once garante que esta operação pesada de busca execute apenas uma vez.
    $BIN_path = once(static function () {
      $finder = new ExecutableFinder();
      $rclone_path = $finder->find('rclone', '/usr/bin/rclone', [
        '/usr/local/bin',
        '/usr/bin',
        '/bin',
        '/usr/local/sbin',
        '/var/lib/snapd/snap/bin', // Caminho comum para instalações via snap
      ]);
      if ($rclone_path === NULL) {
        throw new \RuntimeException('Binário rclone não encontrado. Por favor, garanta que o rclone está instalado e no seu PATH, ou defina o caminho manualmente usando Rclone::setBIN().');
      }
      return $rclone_path;
    });
    
    self::setBIN($BIN_path); // Armazena em cache o caminho encontrado.
    
    return self::getBIN();
  }
  
  /**
   * Analisa a saída de progresso do rclone.
   * Este método é chamado internamente quando um callback de progresso está ativo.
   *
   * @param string $type   O tipo de saída (Process::OUT ou Process::ERR).
   * @param string $buffer O conteúdo do buffer de saída.
   */
  private function parseProgress(string $type, string $buffer) : void
  {
    // A saída de progresso do Rclone é esperada em STDOUT (Process::OUT).
    // Linha de exemplo: "Transferred: 1.234 GiB / 2.000 GiB, 61%, 12.345 MiB/s, ETA 1m2s"
    // Ou com verificações/erros: "Checks: 100 / 100, 100% | Transferred: 0 / 0, - | Errors: 1 (retrying may help)"
    // Esta regex foca na parte da transferência.
    if ($type === Process::OUT) {
      // Regex para capturar estatísticas de transferência.
      $regex = '/([\d.]+\s[a-zA-Z]+)\s+?\/\s+([\d.]+\s[a-zA-Z]+),\s+?(\d+)\%,\s+?([\d.]+\s[a-zA-Z]+\/s),\s+?ETA\s+?([\w]+)/mixu';
      // Regex alternativa para quando 'xfr#' está presente (ex: durante transferências de múltiplos arquivos)
      $regex_xfr = '/([\d.]+\s[a-zA-Z]+)\s+?\/\s+([\d.]+\s[a-zA-Z]+),\s+?(\d+)\%,\s+?([\d.]+\s[a-zA-Z]+\/s),\s+?ETA\s+?([\w]+)\s\(xfr\#(\d+\/\d+)\)/mixu';
      
      preg_match_all($regex_xfr, $buffer, $matches_xfr, PREG_SET_ORDER, 0);
      
      if (isset($matches_xfr[0]) && count($matches_xfr[0]) >= 7) {
        // dataSent, dataTotal, sent (percentagem), speed, eta, xfr_count
        $this->setProgressData($matches_xfr[0][0], $matches_xfr[0][1], $matches_xfr[0][2], (int) $matches_xfr[0][3], $matches_xfr[0][4], $matches_xfr[0][5], $matches_xfr[0][6]);
      } else {
        preg_match_all($regex, $buffer, $matches, PREG_SET_ORDER, 0);
        if (isset($matches[0]) && count($matches[0]) >= 6) {
          // raw, dataSent, dataTotal, sent (percentagem), speed, eta
          $this->setProgressData($matches[0][0], $matches[0][1], $matches[0][2], (int) $matches[0][3], $matches[0][4], $matches[0][5]);
        }
      }
    }
  }
  
  /**
   * Define o objeto de progresso interno com dados analisados.
   *
   * @param string      $raw            A string de progresso bruta.
   * @param string      $dataSent       Quantidade de dados enviados (ex: "1.2 GiB").
   * @param string      $dataTotal      Quantidade total de dados (ex: "2.0 GiB").
   * @param int         $sentPercentage Percentagem concluída.
   * @param string      $speed          Velocidade de transferência atual (ex: "10 MiB/s").
   * @param string      $eta            Tempo estimado restante (ex: "1m2s").
   * @param string|null $xfr            Contagem atual de arquivos em transferência (ex: "1/10").
   */
  private function setProgressData(string $raw, string $dataSent, string $dataTotal, int $sentPercentage, string $speed, string $eta, ?string $xfr = '1/1') : void
  {
    $this->progress = (object) [
      'raw' => $raw,
      'dataSent' => $dataSent,
      'dataTotal' => $dataTotal,
      'sent' => $sentPercentage, // Armazenando como percentagem inteira
      'speed' => $speed,
      'eta' => $eta,
      'xfr' => $xfr ?? '1/1', // Padrão se não fornecido (ex: transferência de arquivo único)
    ];
  }
  
  /**
   * Obtém o objeto de progresso atual.
   *
   * @return object O objeto de progresso.
   */
  public function getProgress() : object
  {
    return $this->progress;
  }
  
  /**
   * Redefine o objeto de progresso para seu estado padrão.
   */
  private function resetProgress() : void
  {
    // Inicializa com valores padrão/vazios de self::$reset['progress']
    $this->progress = (object) self::$reset['progress'];
  }
  
  
  /**
   * Lista os objetos no caminho de origem. (rclone lsjson)
   *
   * @param string $path  Caminho a ser listado.
   * @param array  $flags Flags adicionais.
   *
   * @return array Array de objetos, cada um representando um arquivo ou diretório.
   *               ModTime é convertido para timestamp UNIX.
   * @throws \JsonException Se a decodificação JSON falhar.
   */
  public function ls(string $path, array $flags = []) : array
  {
    $result_json = $this->simpleRun('lsjson', [$this->left_side->backend($path)], $flags);
    
    $items_array = json_decode($result_json, FALSE, 512, JSON_THROW_ON_ERROR);
    
    // Processa ModTime para cada item
    foreach ($items_array as $item) {
      if (isset($item->ModTime) && is_string($item->ModTime)) {
        // O formato ModTime do rclone é como "2023-08-15T10:20:30.123456789Z"
        // PHP strtotime lida bem com este formato, especialmente RFC3339_EXTENDED.
        // Removendo nanossegundos excessivos para maior compatibilidade, se necessário.
        $time_string = preg_replace('/\.(\d{6})\d*Z$/', '.$1Z', $item->ModTime);
        $timestamp = strtotime($time_string);
        $item->ModTime = ($timestamp !== FALSE) ? $timestamp : NULL;
      }
    }
    return $items_array;
  }
  
  /**
   * Verifica se um caminho existe e é um arquivo.
   *
   * @param string $path Caminho a ser verificado.
   *
   * @return object Objeto com propriedades 'exists' (bool), 'details' (object|array), e 'error' (string|\Exception).
   */
  public function is_file(string $path) : object
  {
    return $this->exists($path, 'file');
  }
  
  /**
   * Verifica se um caminho existe e é um diretório.
   *
   * @param string $path Caminho a ser verificado.
   *
   * @return object Objeto com propriedades 'exists' (bool), 'details' (object|array), e 'error' (string|\Exception).
   */
  public function is_dir(string $path) : object
  {
    return $this->exists($path, 'dir');
  }
  
  /**
   * Verifica se um caminho existe e é do tipo especificado ('file' ou 'dir').
   * Este método lista o diretório pai e depois filtra pelo item específico.
   *
   * @param string $path O caminho a ser verificado.
   * @param string $type O tipo a ser verificado ('file' ou 'dir').
   *
   * @return object Um objeto com propriedades:
   *                - bool 'exists': True se o item existe e corresponde ao tipo.
   *                - mixed 'details': Os detalhes do item de 'lsjson' se existir, caso contrário, array vazio.
   *                - mixed 'error': O objeto de exceção se ocorreu um erro durante 'ls', caso contrário, string vazia.
   */
  public function exists(string $path, string $type) : object
  {
    $dirname = dirname($path);
    // Se dirname é '.', significa que o caminho está na raiz do remoto.
    // rclone lsjson remote: precisa apenas de 'remote:' para a raiz, não 'remote:.'
    if ($dirname === '.') {
      $dirname = ''; // Para listagem da raiz
    }
    $basename = basename($path);
    
    try {
      $listing = $this->ls($dirname); // Lista o conteúdo do diretório pai.
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
      // Se ls falhar (ex: diretório pai não encontrado), o item não existe ou está inacessível.
      return (object) ['exists' => FALSE, 'details' => [], 'error' => $e];
    }
  }
  
  
  /**
   * Cria novo arquivo ou altera o tempo de modificação do arquivo. (rclone touch)
   *
   * @see https://rclone.org/commands/rclone_touch/
   *
   * @param string        $path       Caminho para dar touch.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function touch(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('touch', $path, $flags, $onProgress);
  }
  
  /**
   * Cria o caminho se ele ainda não existir. (rclone mkdir)
   *
   * @see https://rclone.org/commands/rclone_mkdir/
   *
   * @param string        $path       Caminho a ser criado.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function mkdir(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('mkdir', $path, $flags, $onProgress);
  }
  
  /**
   * Remove um diretório vazio. (rclone rmdir)
   * Não removerá o caminho se ele tiver algum objeto nele.
   *
   * @see https://rclone.org/commands/rclone_rmdir/
   *
   * @param string        $path       Caminho a ser removido.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function rmdir(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('rmdir', $path, $flags, $onProgress);
  }
  
  /**
   * Remove diretórios vazios sob o caminho. (rclone rmdirs)
   *
   * @see https://rclone.org/commands/rclone_rmdirs/
   *
   * @param string        $path       Caminho raiz para procurar diretórios vazios.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function rmdirs(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('rmdirs', $path, $flags, $onProgress);
  }
  
  /**
   * Remove o caminho e todo o seu conteúdo. (rclone purge)
   * Não obedece a filtros de inclusão/exclusão.
   *
   * @see https://rclone.org/commands/rclone_purge/
   *
   * @param string        $path       Caminho a ser purgado.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function purge(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('purge', $path, $flags, $onProgress);
  }
  
  /**
   * Remove os arquivos no caminho. (rclone delete)
   * Obedece a filtros de inclusão/exclusão. Deixa a estrutura de diretórios.
   *
   * @see https://rclone.org/commands/rclone_delete/
   *
   * @param string|null   $path       Caminho contendo arquivos a serem deletados.
   * @param array         $flags      Flags adicionais (ex: --include, --exclude).
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function delete(?string $path = NULL, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('delete', $path, $flags, $onProgress);
  }
  
  /**
   * Remove um único arquivo do remoto. (rclone deletefile)
   * Não obedece a filtros. Não pode remover um diretório.
   *
   * @see https://rclone.org/commands/rclone_deletefile/
   *
   * @param string        $path       Caminho para o arquivo a ser deletado.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function deletefile(string $path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directRun('deletefile', $path, $flags, $onProgress);
  }
  
  /**
   * Imprime o tamanho total e o número de objetos em remote:path. (rclone size)
   *
   * @param string|null   $path       Caminho para obter o tamanho.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return object Objeto com propriedades 'count' e 'bytes'.
   * @throws \JsonException Se a decodificação JSON falhar.
   */
  public function size(?string $path = NULL, array $flags = [], ?callable $onProgress = NULL) : object
  {
    // Garante que a flag --json seja adicionada para saída parseável.
    $size_flags = array_merge($flags, ['json' => 'true']);
    $result_json = $this->simpleRun('size', [$this->left_side->backend($path)], $size_flags, $onProgress);
    
    return json_decode($result_json, FALSE, 512, JSON_THROW_ON_ERROR);
  }
  
  /**
   * Concatena quaisquer arquivos e os envia para stdout. (rclone cat)
   *
   * @see https://rclone.org/commands/rclone_cat/
   *
   * @param string        $path       Caminho para o arquivo.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return string O conteúdo do arquivo.
   */
  public function cat(string $path, array $flags = [], ?callable $onProgress = NULL) : string
  {
    return $this->simpleRun('cat', [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  
  /**
   * Copia a entrada padrão para remote:path. (rclone rcat)
   *
   * @see https://rclone.org/commands/rclone_rcat/
   *
   * @param string        $path       Caminho de destino no remoto.
   * @param string        $input      Conteúdo a ser enviado.
   * @param array         $flags      Flags adicionais.
   * @param callable|null $onProgress Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function rcat(string $path, string $input, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    // Argumentos para rcat tipicamente incluem apenas o caminho de destino.
    return $this->inputRun('rcat', $input, [$this->left_side->backend($path)], $flags, $onProgress);
  }
  
  
  /**
   * Faz upload de um único arquivo local para um caminho remoto usando o comando 'moveto' para eficiência.
   * Isso efetivamente move o arquivo local para o remoto (copia e depois deleta o original local).
   *
   * @param string        $local_path  Caminho para o arquivo local.
   * @param string        $remote_path Caminho de destino no remoto (left_side da instância Rclone atual).
   * @param array         $flags       Flags adicionais.
   * @param callable|null $onProgress  Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function upload_file(string $local_path, string $remote_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    // Cria uma configuração Rclone temporária: provedor local como origem, left_side da Rclone atual como destino.
    $uploader = new self(left_side: new LocalProvider('local_temp_upload'), right_side: $this->left_side);
    
    // Usa moveto para transferência direta. O rclone 'moveto' de 'local:' para um remoto deleta o arquivo local original.
    return $uploader->moveto($local_path, $remote_path, $flags, $onProgress);
  }
  
  /**
   * Faz download de um arquivo de um caminho remoto para o armazenamento local.
   *
   * @param string    $remote_path            O caminho do arquivo no servidor remoto (left_side da instância Rclone atual).
   * @param ?string   $local_destination_path O caminho local onde o arquivo deve ser salvo.
   *                                          Se for um diretório, o nome original do arquivo é usado.
   *                                          Se null, um diretório temporário com o nome original do arquivo é usado.
   * @param array     $flags                  Flags adicionais para a operação de download.
   * @param ?callable $onProgress             Uma função de callback para rastrear o progresso do download.
   *
   * @return string|false O caminho local absoluto onde o arquivo foi salvo, ou false se o download falhar.
   */
  public function download_to_local(string $remote_path, ?string $local_destination_path = NULL, array $flags = [], ?callable $onProgress = NULL) : string|false
  {
    $remote_filename = basename($remote_path);
    
    if ($local_destination_path === NULL) {
      // Cria um diretório temporário e anexa o nome do arquivo remoto.
      $temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_download_' . uniqid();
      if (!mkdir($temp_dir, 0777, TRUE) && !is_dir($temp_dir)) {
        // @codeCoverageIgnoreStart
        // Este caso é difícil de testar confiavelmente sem manipular permissões do sistema.
        return FALSE; // Falha ao criar diretório temporário
        // @codeCoverageIgnoreEnd
      }
      $final_local_path = $temp_dir . DIRECTORY_SEPARATOR . $remote_filename;
    } elseif (is_dir($local_destination_path)) {
      // Se um diretório for fornecido, anexa o nome do arquivo remoto.
      $final_local_path = rtrim($local_destination_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $remote_filename;
    } else {
      // Um caminho de arquivo específico é fornecido. Garante que o diretório pai exista.
      $parent_dir = dirname($local_destination_path);
      if (!is_dir($parent_dir)) {
        if (!mkdir($parent_dir, 0777, TRUE) && !is_dir($parent_dir)) {
          // @codeCoverageIgnoreStart
          return FALSE; // Falha ao criar diretório pai
          // @codeCoverageIgnoreEnd
        }
      }
      $final_local_path = $local_destination_path;
    }
    
    // Configuração Rclone temporária: left_side da Rclone atual como origem, provedor local como destino.
    $downloader = new self(left_side: $this->left_side, right_side: new LocalProvider('local_temp_download'));
    
    // Usa copyto para transferência direta de arquivo para arquivo.
    $success = $downloader->copyto($remote_path, $final_local_path, $flags, $onProgress);
    
    return $success ? $final_local_path : FALSE;
  }
  
  /**
   * Copia arquivos da origem para o destino, pulando os já copiados. (rclone copy)
   * Origem é um arquivo/diretório em left_side, dest_DIR_path é um diretório em right_side.
   *
   * @see https://rclone.org/commands/rclone_copy/
   *
   * @param string        $source_path   Caminho de origem (arquivo ou diretório).
   * @param string        $dest_DIR_path Caminho do diretório de destino.
   * @param array         $flags         Flags adicionais.
   * @param callable|null $onProgress    Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function copy(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('copy', $source_path, $dest_DIR_path, $flags, $onProgress);
  }
  
  /**
   * Copia um único arquivo ou diretório da origem para um caminho de arquivo/diretório de destino específico. (rclone copyto)
   * Se a origem for um arquivo, dest_path é um arquivo. Se a origem for um dir, dest_path é um dir.
   *
   * @see https://rclone.org/commands/rclone_copyto/
   *
   * @param string        $source_path Caminho do arquivo ou diretório de origem.
   * @param string        $dest_path   Caminho do arquivo ou diretório de destino.
   * @param array         $flags       Flags adicionais.
   * @param callable|null $onProgress  Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function copyto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('copyto', $source_path, $dest_path, $flags, $onProgress);
  }
  
  /**
   * Move arquivos da origem para o destino. (rclone move)
   * Origem é um arquivo/diretório em left_side, dest_DIR_path é um diretório em right_side.
   * Deleta arquivos originais da origem após transferência bem-sucedida.
   *
   * @see https://rclone.org/commands/rclone_move/
   *
   * @param string        $source_path   Caminho de origem (arquivo ou diretório).
   * @param string        $dest_DIR_path Caminho do diretório de destino.
   * @param array         $flags         Flags adicionais.
   * @param callable|null $onProgress    Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function move(string $source_path, string $dest_DIR_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('move', $source_path, $dest_DIR_path, $flags, $onProgress);
  }
  
  /**
   * Move arquivo ou diretório da origem para um caminho de arquivo/diretório de destino específico. (rclone moveto)
   * Se source:path for um arquivo ou diretório, ele o move para um arquivo ou diretório chamado dest:path.
   * Isso pode ser usado para renomear arquivos ou enviar arquivos únicos com nomes diferentes dos existentes.
   * Deleta o original da origem após transferência bem-sucedida.
   *
   * @see https://rclone.org/commands/rclone_moveto/
   *
   * @param string        $source_path Caminho do arquivo ou diretório de origem.
   * @param string        $dest_path   Caminho do arquivo ou diretório de destino.
   * @param array         $flags       Flags adicionais.
   * @param callable|null $onProgress  Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function moveto(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('moveto', $source_path, $dest_path, $flags, $onProgress);
  }
  
  /**
   * Torna a origem e o destino idênticos, modificando apenas o destino. (rclone sync)
   *
   * @see https://rclone.org/commands/rclone_sync/
   *
   * @param string        $source_path Caminho do diretório de origem.
   * @param string        $dest_path   Caminho do diretório de destino.
   * @param array         $flags       Flags adicionais.
   * @param callable|null $onProgress  Callback de progresso opcional.
   *
   * @return bool True em caso de sucesso.
   */
  public function sync(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    return $this->directTwinRun('sync', $source_path, $dest_path, $flags, $onProgress);
  }
  
  /**
   * Verifica se os arquivos na origem e no destino correspondem. (rclone check)
   *
   * @see https://rclone.org/commands/rclone_check/
   *
   * @param string        $source_path Caminho do diretório de origem.
   * @param string        $dest_path   Caminho do diretório de destino.
   * @param array         $flags       Flags adicionais.
   * @param callable|null $onProgress  Callback de progresso opcional.
   *
   * @return bool True se a verificação for bem-sucedida (geralmente significa que não há diferenças ou erros com base nas flags).
   *              Nota: rclone 'check' tem códigos de saída específicos para diferenças encontradas. Este método
   *              lançará uma exceção se rclone sair com um código diferente de zero, a menos que flags específicas
   *              de tratamento de erro como --one-way e --differ sejam usadas e resultem em um código de saída 0.
   */
  public function check(string $source_path, string $dest_path, array $flags = [], ?callable $onProgress = NULL) : bool
  {
    // Rclone 'check' pode sair com códigos diferentes de zero se diferenças forem encontradas.
    // O método 'simpleRun' lançará uma exceção para códigos de saída diferentes de zero.
    // Para 'check', uma execução bem-sucedida (código de saída 0) significa que nenhuma diferença foi encontrada (ou ignorada pelas flags).
    $this->directTwinRun('check', $source_path, $dest_path, $flags, $onProgress);
    return TRUE; // Se directTwinRun não lançar exceção, significa que rclone saiu com 0.
  }
  
  /**
   * Obtém o provedor do lado esquerdo (origem).
   *
   * @return Provider A instância do provedor do lado esquerdo.
   */
  public function getLeftSide() : Provider
  {
    return $this->left_side;
  }
  
  /**
   * Define o provedor do lado esquerdo (origem).
   *
   * @param Provider $left_side A instância do provedor.
   */
  public function setLeftSide(Provider $left_side) : void
  {
    $this->left_side = $left_side;
  }
  
  /**
   * Obtém o provedor do lado direito (destino).
   *
   * @return Provider A instância do provedor do lado direito.
   */
  public function getRightSide() : Provider
  {
    return $this->right_side;
  }
  
  /**
   * Define o provedor do lado direito (destino).
   *
   * @param Provider $right_side A instância do provedor.
   */
  public function setRightSide(Provider $right_side) : void
  {
    $this->right_side = $right_side;
  }
}