# ADR - Architectural Decision Records

Registro de decisões arquiteturais do projeto Flyclone.

---

## ADR-001: Wrapper sobre rclone CLI

**Status:** Aceito  
**Data:** Início do projeto

### Contexto
Precisávamos de uma forma de interagir com múltiplos cloud storages (S3, Dropbox, GDrive, SFTP, etc.) em PHP.

### Opções Consideradas
1. SDKs individuais para cada provider
2. Wrapper sobre rclone CLI via symfony/process

### Decisão
Usar rclone CLI como backend em vez de SDKs individuais.

### Consequências
- ✅ Suporte a 40+ providers sem manter SDKs separados
- ✅ Atualizações do rclone = novos recursos automaticamente
- ✅ Configuração unificada para todos providers
- ⚠️ Dependência de binário externo (rclone deve estar instalado)
- ⚠️ Performance: overhead de spawn de processo

---

## ADR-002: Arquitetura Source/Destination

**Status:** Aceito  
**Data:** Início do projeto

### Contexto
Operações de transferência precisam de origem e destino distintos.

### Decisão
`Rclone` aceita 1 provider (operações locais) ou 2 providers (transferências).

```php
// Operação single-provider
$rclone = new Rclone($local);
$rclone->ls('/path');

// Operação cross-provider
$rclone = new Rclone($source, $dest);
$rclone->copy('/source/file', '/dest/');
```

### Consequências
- ✅ API clara e intuitiva
- ✅ Suporta todos os cenários de uso do rclone
- ✅ Type-safe: providers são objetos tipados

---

## ADR-003: Configuração via Variáveis de Ambiente

**Status:** Aceito  
**Data:** Início do projeto

### Contexto
Credenciais não devem ser hardcoded ou expostas em arquivos de configuração.

### Decisão
Providers usam formato `RCLONE_CONFIG_{NAME}_{KEY}` para configuração, passado como environment variables para o processo.

```php
$s3 = new S3Provider('myS3', [
    'access_key_id' => 'xxx',
    'secret_access_key' => 'yyy',
]);
// Gera: RCLONE_CONFIG_MYS3_ACCESS_KEY_ID=xxx
```

### Consequências
- ✅ Seguro: credenciais não ficam em disco
- ✅ Compatível com containers e CI/CD
- ✅ Sobrescreve rclone.conf se existir
- ✅ Isolamento: cada instância tem suas credenciais

---

## ADR-004: Exit Codes Mapeados para Exceções

**Status:** Aceito  
**Data:** Início do projeto

### Contexto
Rclone retorna exit codes numéricos para diferentes tipos de erro. Precisamos de tratamento granular.

### Decisão
Cada exit code mapeia para uma exceção PHP específica:

| Exit | Exceção                       | Retry? |
| ---- | ----------------------------- | ------ |
| 1    | SyntaxErrorException          | Não    |
| 3    | DirectoryNotFoundException    | Não    |
| 4    | FileNotFoundException         | Não    |
| 5    | TemporaryErrorException       | Sim    |
| 6    | LessSeriousErrorException     | Talvez |
| 7    | FatalErrorException           | Não    |
| 8    | MaxTransferReachedException   | Não    |
| 9    | NoFilesTransferredException   | Talvez |

### Consequências
- ✅ Tratamento de erro granular no código cliente
- ✅ Catch específico por tipo de erro
- ✅ Decisão de retry baseada no tipo de exceção

---

## ADR-005: PHP 8.4 como Requisito Mínimo

**Status:** Aceito  
**Data:** 2024

### Contexto
O projeto usa features modernas do PHP para código mais limpo.

### Decisão
PHP 8.4+ é obrigatório. Features utilizadas:
- Property hooks (`get`/`set` syntax)
- Readonly properties
- Match expressions
- Named arguments

```php
// Property hooks em AbstractTwoProvidersTest
public string $leftProviderName {
    get => $this->instantiate_left_provider()->name();
}
```

### Consequências
- ✅ Código mais expressivo e type-safe
- ✅ Menos boilerplate
- ⚠️ Não compatível com PHP 8.3 ou inferior

---

## ADR-006: Providers Compostos (Decorator Pattern)

**Status:** Experimental  
**Data:** 2024

### Contexto
Alguns casos de uso requerem composição de providers (criptografia, merge de múltiplos backends).

### Decisão
Implementar `CryptProvider` e `UnionProvider` usando Decorator pattern:

```php
// CryptProvider wraps outro provider
$s3 = new S3Provider('s3', ['bucket' => 'data']);
$crypt = new CryptProvider('encrypted', ['remote' => $s3]);

// UnionProvider merge múltiplos
$union = new UnionProvider('merged', [
    'upstream_providers' => [$local, $s3]
]);
```

### Consequências
- ✅ Composição flexível de providers
- ✅ Reutilização de providers existentes
- ⚠️ **Status experimental**: testes ainda falhando
- ⚠️ Complexidade adicional de configuração

---

## ADR-007: Infraestrutura de Testes com Containers

**Status:** Aceito  
**Data:** Início do projeto

### Contexto
Testes precisam de backends reais (SFTP, S3, FTP) mas não podemos depender de serviços externos.

### Decisão
Usar docker-compose para provisionar serviços de teste:

| Service | Imagem          | Porta |
| ------- | --------------- | ----- |
| sftp    | atmoz/sftp      | 2222  |
| s3      | minio/minio     | 9000  |
| ftp     | fauria/vsftpd   | 2121  |

### Consequências
- ✅ Testes reproduzíveis e offline
- ✅ Sem necessidade de contas cloud reais
- ✅ CI/CD consistente
- ⚠️ Requer Docker/Podman instalado para testes completos

---

## ADR-008: Estratégia de Testes em Camadas

**Status:** Aceito  
**Data:** Início do projeto

### Contexto
Diferentes níveis de teste para diferentes cenários.

### Decisão
Três camadas de testes:

1. **Single-Provider** (`AbstractProviderTest`)
   - CRUD básico: touch, write, rename, delete
   - mkdir, copy, move, list, purge

2. **Cross-Provider** (`AbstractTwoProvidersTest`)
   - Transferências entre providers diferentes
   - Progress tracking

3. **Operações Especiais** (`ExtraCommandsTest`, `UploadDownloadOperationsTest`)
   - Comandos específicos: size, about, tree
   - Upload/download com progresso

### Consequências
- ✅ Cobertura abrangente
- ✅ Testes rápidos (local) vs completos (com containers)
- ✅ Fácil adicionar novo provider: herdar de AbstractProviderTest

---

## ADR-009: Branching Strategy para Major Releases

**Status:** Aceito  
**Data:** 2025-01

### Contexto
Major releases (v4, v5...) envolvem refatorações extensas que podem quebrar a estabilidade da main.

### Decisão
Desenvolver major versions em branch dedicada:

```
main (v3.x estável)
  └── v4 (desenvolvimento v4)
        ├── v4.0.0-alpha.1 (tag após milestone 1)
        ├── v4.0.0-beta.1  (tag após milestone 2)
        └── v4.0.0-rc.1    (tag após milestone 3)
```

Fluxo:
1. Criar branch `v4` a partir de main
2. Trabalhar diretamente na branch (sem feature branches por action)
3. Tags alpha/beta/rc conforme milestones completados
4. Merge para main apenas no release final
5. Tag `v4.0.0` na main após merge

### Consequências
- ✅ Main sempre estável para usuários v3.x
- ✅ Progresso incremental visível via tags
- ✅ Rollback fácil se necessário
- ⚠️ Pode divergir de main (resolver antes do merge)

---

## Template para Novos ADRs

```markdown
## ADR-XXX: Título

**Status:** Proposto | Aceito | Deprecated | Substituído  
**Data:** YYYY-MM-DD

### Contexto
[Situação que levou à decisão]

### Opções Consideradas
1. [Opção A]
2. [Opção B]

### Decisão
[O que foi decidido e por quê]

### Consequências
- ✅ [Benefício]
- ⚠️ [Trade-off ou risco]
```
