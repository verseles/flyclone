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
