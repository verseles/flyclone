# Flyclone - Mapa do Código

PHP wrapper fluente para rclone CLI com suporte a múltiplos provedores cloud.

---

## Estrutura do Projeto

```
flyclone/
├── src/
│   ├── Rclone.php              # Classe principal - orquestra tudo
│   ├── ProcessManager.php      # Execução de processos rclone
│   ├── CommandBuilder.php      # Construção de comandos e flags
│   ├── StatsParser.php         # Parsing de estatísticas
│   ├── ProgressParser.php      # Parsing de progresso em tempo real
│   ├── SecretsRedactor.php     # Redação de segredos em mensagens
│   ├── Logger.php              # Logging estruturado opcional
│   ├── RetryHandler.php        # Retry com backoff exponencial
│   ├── FilterBuilder.php       # API fluente para filtros
│   ├── Providers/              # Provedores de storage
│   └── Exception/              # Hierarquia de exceções
├── tests/Unit/                 # Testes PHPUnit
├── .github/workflows/          # CI/CD
├── docker-compose.yml          # Containers para testes
└── makefile                    # Comandos de desenvolvimento
```

---

## `/src/Rclone.php` - Classe Principal

Orquestra execução do rclone CLI. Aceita 1-2 providers (source/dest).
Delega para `ProcessManager`, `CommandBuilder`, `StatsParser` e `ProgressParser`.

### Configuração Estática
| Propriedade    | Default | Descrição                        |
| -------------- | ------- | -------------------------------- |
| `$BIN`         | auto    | Caminho do binário rclone        |
| `$timeout`     | 120s    | Timeout total do processo        |
| `$idleTimeout` | 100s    | Timeout de inatividade           |
| `$flags`       | []      | Flags globais para todos comandos|
| `$envs`        | []      | Variáveis de ambiente extras     |

### Operações Disponíveis
| Método       | Descrição                              |
| ------------ | -------------------------------------- |
| `ls()`       | Listar arquivos                        |
| `mkdir()`    | Criar diretório                        |
| `copy()`     | Copiar arquivo/diretório               |
| `copyto()`   | Copiar para destino específico         |
| `move()`     | Mover arquivo/diretório                |
| `moveto()`   | Mover para destino específico          |
| `sync()`     | Sincronizar diretórios                 |
| `delete()`   | Deletar arquivos                       |
| `deletefile()` | Deletar arquivo específico           |
| `purge()`    | Deletar diretório e conteúdo           |
| `cat()`      | Ler conteúdo de arquivo                |
| `rcat()`     | Escrever conteúdo em arquivo           |
| `size()`     | Obter tamanho                          |
| `touch()`    | Criar arquivo vazio / atualizar mtime  |
| `about()`    | Info do provider                       |
| `tree()`     | Listar em formato árvore               |
| `dedupe()`   | Remover duplicatas                     |
| `cleanup()`  | Limpar arquivos incompletos            |
| `backend()`  | Comandos específicos do backend        |
| `bisync()`   | Sincronização bidirecional             |
| `md5sum()`   | Checksums MD5 de arquivos              |
| `sha1sum()`  | Checksums SHA1 de arquivos             |
| `listRemotes()` | Lista remotes configurados (static) |
| `configFile()` | Caminho do arquivo de config (static) |
| `configDump()` | Config como JSON (static)            |

### Métodos de Controle (v4)
| Método              | Descrição                                   |
| ------------------- | ------------------------------------------- |
| `dryRun(bool)`      | Ativa modo simulação                        |
| `isDryRun()`        | Verifica se dry-run está ativo              |
| `retry(attempts, delay)` | Configura retry com backoff            |
| `withRetry(handler)`| Define RetryHandler customizado             |
| `withFilter(builder)` | Define filtros para operação              |
| `filter()`          | Retorna FilterBuilder atual                 |
| `clearFilter()`     | Remove filtros                              |
| `healthCheck(path)` | Verifica conectividade do provider          |
| `getLastCommand()`  | Retorna último comando executado            |
| `getLastEnvs()`     | Retorna últimas env vars (redatadas)        |

### Tracking de Progresso
```php
$rclone->copy($source, $dest);
$progress = $rclone->getProgress();

$progress->raw;       // string bruta do rclone
$progress->dataSent;  // "1.5 MB"
$progress->dataTotal; // "10 MB"
$progress->sent;      // int 0-100 (porcentagem)
$progress->speed;     // "5 MB/s"
$progress->eta;       // "00:05"
```

---

## `/src/ProcessManager.php` - Gerenciamento de Processos

Centraliza execução de processos rclone e mapeamento de erros.

| Método                    | Descrição                                    |
| ------------------------- | -------------------------------------------- |
| `guessBin()`              | Detecta binário rclone no sistema            |
| `run(command, envs, input)` | Cria e executa processo Symfony             |
| `execute(process)`        | Executa com mustRun e tratamento de erros    |
| `handleFailure(process)`  | Mapeia exit codes para exceções específicas  |
| `obscure(secret)`         | Ofusca senha via `rclone obscure`            |

### Mapeamento de Exit Codes
| Exit Code | Exceção                      |
| --------- | ---------------------------- |
| 1         | SyntaxErrorException         |
| 3         | DirectoryNotFoundException   |
| 4         | FileNotFoundException        |
| 5         | TemporaryErrorException      |
| 6         | LessSeriousErrorException    |
| 7         | FatalErrorException          |
| 8         | MaxTransferReachedException  |
| 9         | NoFilesTransferredException  |

---

## `/src/CommandBuilder.php` - Construção de Comandos

Constrói comandos e variáveis de ambiente para rclone.

| Método                          | Descrição                                         |
| ------------------------------- | ------------------------------------------------- |
| `prefixFlags(array, prefix)`    | Transforma keys: `key` → `RCLONE_PREFIX_KEY`      |
| `buildEnvironment(providers, flags, opFlags)` | Consolida env vars de providers + globais |

### Padrão de Variáveis de Ambiente
```
RCLONE_CONFIG_{PROVIDER_NAME}_{KEY} = value
```
Exemplo: `RCLONE_CONFIG_MYS3_ACCESS_KEY_ID = xxx`

---

## `/src/StatsParser.php` - Parsing de Estatísticas

Extrai estatísticas de transferência do stderr do rclone.

| Método                       | Descrição                                |
| ---------------------------- | ---------------------------------------- |
| `parse(stderr)`              | Extrai stats completas do output         |
| `convertSizeToBytes(string)` | "1.5 GiB" → 1610612736                   |
| `convertDurationToSeconds(string)` | "1m30s" → 90                       |
| `formatBytes(int)`           | 1610612736 → "1.50 GiB"                  |

### Objeto de Estatísticas Retornado
```php
(object) [
    'bytes' => 1073741824,
    'files' => 150,
    'speed_bytes_per_second' => 12946789.23,
    'speed_human' => '12.345 MiB/s',
    'elapsed_time' => 93.4,
    'errors' => 0,
    'checks' => 150,
]
```

---

## `/src/ProgressParser.php` - Parsing de Progresso

Extrai progresso em tempo real do stdout do rclone.

| Método                   | Descrição                             |
| ------------------------ | ------------------------------------- |
| `parse(type, buffer)`    | Processa linha de progresso           |
| `getProgress()`          | Retorna objeto de progresso atual     |
| `reset()`                | Limpa estado de progresso             |

### Objeto de Progresso
```php
(object) [
    'raw' => '1.234 GiB / 2.000 GiB, 61%, 12.345 MiB/s, ETA 1m2s',
    'dataSent' => '1.234 GiB',
    'dataTotal' => '2.000 GiB',
    'sent' => 61,           // porcentagem 0-100
    'speed' => '12.345 MiB/s',
    'eta' => '1m2s',
]
```

---

## `/src/SecretsRedactor.php` - Redação de Segredos

Remove informações sensíveis de mensagens de erro e logs.

| Método                     | Descrição                                      |
| -------------------------- | ---------------------------------------------- |
| `redact(message, secrets)` | Remove senhas, tokens, URLs com credenciais   |
| `setEnabled(bool)`         | Habilita/desabilita redação globalmente        |
| `isEnabled()`              | Verifica se redação está ativa                 |

### Padrões Redatados
- URLs com credenciais: `https://user:pass@host` → `https://[REDACTED]@host`
- Variáveis de ambiente: `PASSWORD=secret` → `PASSWORD=[REDACTED]`
- Segredos conhecidos passados explicitamente

---

## `/src/Logger.php` - Logging Estruturado

Logger opcional para debugging e monitoramento.

| Método                        | Descrição                                  |
| ----------------------------- | ------------------------------------------ |
| `setDebugMode(bool)`          | Ativa modo debug (loga comandos)           |
| `isDebugMode()`               | Verifica se debug está ativo               |
| `setLogger(object)`           | Define logger PSR-3 externo                |
| `debug/info/warning/error()`  | Métodos de log por nível                   |
| `logCommand(cmd, envs)`       | Loga execução de comando (modo debug)      |
| `logResult(success, duration)`| Loga resultado (modo debug)                |
| `getLogs()`                   | Retorna logs internos                      |
| `clearLogs()`                 | Limpa logs internos                        |

---

## `/src/RetryHandler.php` - Retry com Backoff

Mecanismo de retry para falhas temporárias.

| Método                          | Descrição                               |
| ------------------------------- | --------------------------------------- |
| `create()`                      | Factory method                          |
| `maxAttempts(int)`              | Define máximo de tentativas (default 3) |
| `baseDelay(int)`                | Delay base em ms (default 1000)         |
| `multiplier(float)`             | Multiplicador exponencial (default 2.0) |
| `maxDelay(int)`                 | Delay máximo em ms (default 30000)      |
| `retryOn(callable)`             | Condição customizada para retry         |
| `onRetry(callable)`             | Callback executado a cada retry         |
| `execute(callable)`             | Executa operação com retry              |

```php
$result = RetryHandler::create()
    ->maxAttempts(5)
    ->baseDelay(500)
    ->execute(fn() => $rclone->copy($src, $dst));
```

---

## `/src/FilterBuilder.php` - API Fluente para Filtros

Construtor de padrões include/exclude para operações.

| Método                    | Descrição                              |
| ------------------------- | -------------------------------------- |
| `include(pattern)`        | Adiciona padrão de inclusão            |
| `exclude(pattern)`        | Adiciona padrão de exclusão            |
| `extensions(ext[])`       | Inclui por extensões                   |
| `minSize(size)`           | Tamanho mínimo (ex: "100K", "1M")      |
| `maxSize(size)`           | Tamanho máximo                         |
| `newerThan(age)`          | Arquivos mais novos que (ex: "1d")     |
| `olderThan(age)`          | Arquivos mais velhos que               |
| `toFlags()`               | Converte para array de flags rclone    |
| `reset()`                 | Limpa todos os filtros                 |

```php
$rclone->withFilter(
    FilterBuilder::create()
        ->extensions(['jpg', 'png'])
        ->minSize('100K')
        ->exclude('**/thumbs/**')
)->copy($src, $dst);
```

---

## `/src/Providers/` - Provedores de Storage

### Classe Base: `AbstractProvider`

| Propriedade    | Default | Significado                                   |
| -------------- | ------- | --------------------------------------------- |
| `$dirAgnostic` | false   | true = não suporta pastas vazias (S3, B2)     |
| `$bucketAsDir` | false   | true = bucket funciona como diretório         |
| `$listsAsTree` | false   | true = listagens retornam estrutura de árvore |

### Providers Simples
| Arquivo            | Provider | `$dirAgnostic` | Notas                    |
| ------------------ | -------- | -------------- | ------------------------ |
| `LocalProvider`    | local    | false          | Filesystem local         |
| `S3Provider`       | s3       | **true**       | AWS S3 / MinIO           |
| `SFtpProvider`     | sftp     | false          | SSH File Transfer        |
| `FtpProvider`      | ftp      | false          | FTP clássico             |
| `DropboxProvider`  | dropbox  | false          | API Dropbox              |
| `GDriveProvider`   | drive    | false          | Google Drive             |
| `MegaProvider`     | mega     | false          | Mega.nz                  |
| `B2Provider`       | b2       | **true**       | Backblaze B2             |

### Providers Compostos (Decorator Pattern)
| Arquivo          | Padrão    | Descrição                                  | Status       |
| ---------------- | --------- | ------------------------------------------ | ------------ |
| `CryptProvider`  | Decorator | Wraps 1 provider, adiciona criptografia    | ✅ Funcional |
| `UnionProvider`  | Composite | Merge N providers em filesystem unificado  | ✅ Funcional |

```php
// CryptProvider wraps outro provider
$s3 = new S3Provider('s3', ['bucket' => 'data']);
$crypt = new CryptProvider('encrypted', ['remote' => $s3]);

// UnionProvider merge múltiplos
$union = new UnionProvider('merged', [
    'upstream_providers' => [$local, $s3, $sftp]
]);
```

---

## `/src/Exception/` - Hierarquia de Exceções

Rclone exit codes mapeados para exceções PHP específicas:

| Exceção                         | Exit | Situação                       |
| ------------------------------- | ---- | ------------------------------ |
| `RcloneException`               | base | Classe pai de todas            |
| `SyntaxErrorException`          | 1    | Erro de sintaxe/uso            |
| `DirectoryNotFoundException`    | 3    | Diretório não existe           |
| `FileNotFoundException`         | 4    | Arquivo não existe             |
| `TemporaryErrorException`       | 5    | Erro temporário (pode retry)   |
| `LessSeriousErrorException`     | 6    | Alguns arquivos não transferidos |
| `FatalErrorException`           | 7    | Erro fatal irrecuperável       |
| `MaxTransferReachedException`   | 8    | Limite de transferência atingido |
| `NoFilesTransferredException`   | 9    | Nenhum arquivo transferido     |
| `ProcessTimedOutException`      | -    | Timeout do processo PHP        |
| `WriteOperationFailedException` | -    | Falha de escrita               |
| `UnknownErrorException`         | ?    | Erro não mapeado               |
| `CredentialWarning`             | -    | Aviso de credencial plaintext  |

### Contexto de Exceção (v4)
```php
try {
    $rclone->copy($src, $dst);
} catch (RcloneException $e) {
    $e->isRetryable();       // bool - pode fazer retry?
    $e->getContext();        // array - comando, provider, path
    $e->getDetailedMessage();// string - mensagem + contexto
}
```

---

## `/tests/Unit/` - Testes

### Classes Base
| Arquivo                      | Propósito                                |
| ---------------------------- | ---------------------------------------- |
| `AbstractProviderTest`       | Testes single-provider (CRUD básico)     |
| `AbstractTwoProvidersTest`   | Testes cross-provider (transferências)   |
| `Helpers` (trait)            | `random_string()` para nomes únicos      |
| `ProgressTrackingTrait`      | Helpers para testar progresso            |

### Testes por Provider
- `LocalProviderTest`, `S3ProviderTest`, `SFtpProviderTest`, etc.
- Herdam de `AbstractProviderTest`

### Testes Cross-Provider
- `FromLocalToS3ProviderTest`
- `FromS3ToLocalProviderTest`
- `FromS3ToSFtpProviderTest`
- `FromSFtpToS3ProviderTest`
- Herdam de `AbstractTwoProvidersTest`

### Testes Especiais
| Arquivo                      | Propósito                                |
| ---------------------------- | ---------------------------------------- |
| `ExtraCommandsTest`          | Comandos adicionais (size, about, etc.)  |
| `UploadDownloadOperationsTest` | Operações de upload/download           |
| `CryptProviderTest`          | Testes de criptografia (15 testes)       |
| `UnionProviderTest`          | Testes de union filesystem (16 testes)   |
| `ConfigurationTest`          | Testes de configuração (13 testes)       |
| `EdgeCasesTest`              | Casos especiais e edge cases (13 testes) |
| `Feature2Test`               | Testes Feature 2: Security & DX (28 testes) |

---

## Infraestrutura

### Docker Compose Services
| Service | Porta | Uso                        |
| ------- | ----- | -------------------------- |
| `sftp`  | 2222  | Testes SFTP                |
| `s3`    | 9000  | MinIO (S3-compatible)      |
| `ftp`   | 2121  | Testes FTP                 |

### GitHub Actions
- PHP 8.4
- Instala rclone via `AnimMouse/setup-rclone`
- Roda `composer run-script test-local`
