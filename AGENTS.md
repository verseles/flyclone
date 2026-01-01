# Flyclone - Agent Instructions

**IMPORTANTE:** Ao iniciar qualquer sessão, leia TODOS os arquivos:

| Arquivo        | Conteúdo                         |
| -------------- | -------------------------------- |
| @./CODEBASE.md | Mapa do código                   |
| @./ADR.md      | Decisões arquiteturais           |
| @./README.md   | Visão geral, instalação e uso    |

As regras deste arquivo são **obrigatórias**.

---

## Referência Rápida

| Campo           | Valor                          |
| --------------- | ------------------------------ |
| **Nome**        | Flyclone                       |
| **Package**     | `verseles/flyclone`            |
| **PHP**         | >= 8.4 (obrigatório)           |
| **Repositório** | github.com/verseles/flyclone   |
| **Licença**     | CC-BY-NC-SA-4.0                |

---

## Dependências

| Dependência       | Obrigatória | Notas                              |
| ----------------- | ----------- | ---------------------------------- |
| PHP 8.4+          | Sim         | Property hooks são utilizados      |
| rclone            | Sim         | Binário deve estar no PATH         |
| Composer          | Sim         | Gerenciamento de dependências      |
| podman-compose    | Para testes | Containers SFTP, MinIO, FTP        |

---

## Comandos

### Make
| Comando           | Descrição                                        |
| ----------------- | ------------------------------------------------ |
| `make test`       | Testes rápidos (extra_commands, upload_download) |
| `make test-offline` | Testes completos com containers                |
| `make init`       | `composer install`                               |
| `make tog`        | Gera documentação para AI                        |

### Composer
| Comando                      | Descrição                    |
| ---------------------------- | ---------------------------- |
| `composer test`              | Todos os testes              |
| `composer test-local`        | Apenas LocalProvider (CI)    |
| `composer run-script test-offline` | Testes offline completos |

---

## Setup para Desenvolvimento

```bash
# 1. Instalar dependências PHP
make init  # ou: composer install

# 2. Verificar rclone instalado
rclone version

# 3. Para testes com containers (SFTP, S3, FTP)
podman-compose up -d sftp s3 ftp

# 4. Para testes com providers cloud reais
cp .env.example .env
# Editar .env com credenciais (Dropbox, GDrive, Mega, etc.)
```

---

## Regras de Trabalho

### Desenvolvimento

1. **PHP 8.4+ obrigatório.** Property hooks são utilizados extensivamente.

2. **Testes primeiro.** Todo novo provider deve herdar de `AbstractProviderTest`.

3. **Exceções específicas.** Novos tipos de erro = nova exceção herdando `RcloneException`.

4. **Providers experimentais.** `CryptProvider` e `UnionProvider` têm testes falhando - corrigir antes de usar em produção.

### Build & Commit

5. **Testes antes de commit.** `make test` ou `composer test-local` deve passar.

6. **Commit descritivo.** Título resumido + lista de alterações.

7. **Push só se pedido.** Não fazer push automaticamente.

### Documentação

8. **Atualizar CODEBASE.md** ao criar novos arquivos/classes significativos.

9. **Atualizar ADR.md** para decisões técnicas importantes.

10. **Atualizar AGENTS.md** quando mudanças afetarem o fluxo de trabalho.

### Web Search

11. **Use web search para:**
    - Confirmar métodos eficientes/modernos
    - Resolver erros quando ficar preso
    - Aguardar 1 segundo entre pesquisas (rate limit)

### Notificações

12. **Chamar `play_notification` quando:**
    - Finalizar trabalho/tarefa grande
    - Finalizar planejamento
    - Ficar completamente preso sem solução

---

## Arquitetura

### Criando Novo Provider

```php
<?php

namespace Verseles\Flyclone\Providers;

class MyCloudProvider extends Provider
{
    protected string $provider = 'mycloud';
    
    // Se não suporta pastas vazias (como S3):
    protected bool $dirAgnostic = true;
}
```

### Uso Básico

```php
use Verseles\Flyclone\Rclone;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\S3Provider;

// Single provider
$local = new LocalProvider('local', ['root' => '/path']);
$rclone = new Rclone($local);
$rclone->ls('/');

// Cross-provider transfer
$s3 = new S3Provider('s3', [
    'access_key_id' => 'xxx',
    'secret_access_key' => 'yyy',
    'region' => 'us-east-1',
    'endpoint' => 'https://s3.amazonaws.com',
]);
$rclone = new Rclone($local, $s3);
$rclone->copy('/local/file.txt', '/bucket/');
```

### Tratamento de Erros

```php
use Verseles\Flyclone\Exception\FileNotFoundException;
use Verseles\Flyclone\Exception\TemporaryErrorException;

try {
    $rclone->copy($source, $dest);
} catch (FileNotFoundException $e) {
    // Arquivo não existe - não faz sentido retry
} catch (TemporaryErrorException $e) {
    // Erro temporário - pode fazer retry
}
```

---

## Docker Compose Services

| Service | Porta | Usuário/Senha           |
| ------- | ----- | ----------------------- |
| sftp    | 2222  | Ver docker-compose.yml  |
| s3      | 9000  | minioadmin/minioadmin   |
| ftp     | 2121  | Ver docker-compose.yml  |

---

## Não Fazer

- **NÃO** ignorar testes falhando
- **NÃO** usar `@phpstan-ignore` ou similar sem justificativa
- **NÃO** commitar credenciais (usar .env)
- **NÃO** fazer push sem o usuário ter pedido
- **NÃO** prosseguir com erros pendentes
- **NÃO** usar `docker`/`docker-compose` (usar `podman`/`podman-compose`)
