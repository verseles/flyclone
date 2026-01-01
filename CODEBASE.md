# Flyclone - Mapa do Código

PHP wrapper fluente para rclone CLI com suporte a múltiplos provedores cloud.

---

## Estrutura do Projeto

```
flyclone/
├── src/
│   ├── Rclone.php              # Classe principal - orquestra tudo
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
| `CryptProvider`  | Decorator | Wraps 1 provider, adiciona criptografia    | ⚠️ Experimental |
| `UnionProvider`  | Composite | Merge N providers em filesystem unificado  | ⚠️ Experimental |

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
| `CryptProviderTest`          | ⚠️ Experimental - pode falhar            |
| `UnionProviderTest`          | ⚠️ Experimental - pode falhar            |

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
