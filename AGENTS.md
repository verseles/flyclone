# Flyclone - Agent Instructions

**IMPORTANTE:** Ao iniciar qualquer sessão, leia TODOS os arquivos:

| Arquivo        | Conteúdo                      |
| -------------- | ----------------------------- |
| @./CODEBASE.md | Mapa do código                |
| @./ADR.md      | Decisões arquiteturais        |
| @./README.md   | Visão geral, instalação e uso |

As regras deste arquivo são **obrigatórias**.

---

## Referência Rápida

| Campo           | Valor                        |
| --------------- | ---------------------------- |
| **Nome**        | Flyclone                     |
| **Package**     | `verseles/flyclone`          |
| **PHP**         | >= 8.4 (obrigatório)         |
| **Repositório** | github.com/verseles/flyclone |
| **Licença**     | CC-BY-NC-SA-4.0              |

---

## Dependências

| Dependência    | Obrigatória | Notas                         |
| -------------- | ----------- | ----------------------------- |
| PHP 8.4+       | Sim         | Property hooks são utilizados |
| rclone         | Sim         | Binário deve estar no PATH    |
| Composer       | Sim         | Gerenciamento de dependências |
| podman-compose | Para testes | Containers SFTP, MinIO, FTP   |

---

## Comandos

### Make

| Comando             | Descrição                                        |
| ------------------- | ------------------------------------------------ |
| `make test`         | Testes rápidos (extra_commands, upload_download) |
| `make test-offline` | Testes completos com containers                  |
| `make init`         | `composer install`                               |
| `make tog`          | Gera documentação para AI                        |

### Composer

| Comando                            | Descrição                 |
| ---------------------------------- | ------------------------- |
| `composer test`                    | Todos os testes           |
| `composer test-local`              | Apenas LocalProvider (CI) |
| `composer run-script test-offline` | Testes offline completos  |

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

11. **Use web search com FREQUÊNCIA:**
    - Antes de implementar: confirmar abordagem moderna/eficiente
    - Ao encontrar erro: pesquisar soluções antes de tentar fixes aleatórios
    - Ao ficar incerto: pesquisar ao invés de assumir
    - Documentação oficial: usar context7 ou busca direta
    - **Mínimo 2-3 pesquisas por action complexa**
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

| Service | Porta | Usuário/Senha          |
| ------- | ----- | ---------------------- |
| sftp    | 2222  | Ver docker-compose.yml |
| s3      | 9000  | minioadmin/minioadmin  |
| ftp     | 2121  | Ver docker-compose.yml |

---

## Execução de Roadmap

O projeto utiliza o arquivo `ROADMAP.md` para gerenciar tarefas de longo prazo. Siga estas diretrizes para manter a execução lúcida e focada.

### Estado Atual do Projeto

| Branch | Versão | Status                 | Ação do Agent                 |
| ------ | ------ | ---------------------- | ----------------------------- |
| `main` | v3.x   | Estável                | Apenas hotfixes críticos      |
| `v4`   | v4.0   | **EM DESENVOLVIMENTO** | Todo trabalho do roadmap aqui |

**⚠️ IMPORTANTE:** Ao iniciar sessão para trabalhar no roadmap:

1. `git checkout v4` - Garantir que está na branch correta
2. `cat roadmap/roadmap.md` - Ver estado atual e próximas actions
3. Escolher UMA action pendente e marcar `in_progress`

### Tags e Milestones

| Tag Pattern      | Quando Criar                                | Milestone |
| ---------------- | ------------------------------------------- | --------- |
| `v4.0.0-alpha.N` | Após completar Feature 1 (Core Refactoring) | 1         |
| `v4.0.0-beta.N`  | Após completar Feature 2 (Security & DX)    | 2         |
| `v4.0.0-rc.N`    | Após completar Feature 3 (Polish & Release) | 3         |
| `v4.0.0`         | Merge para main + release final             | -         |

### Antes de Iniciar uma Action

1. **Ler o roadmap.** para entender o contexto geral e identificar dependências.

2. **Ler apenas arquivos necessários.** Não carregar todo o codebase - apenas o que a action específica precisa.

3. **Planejar antes de codar.** Analisar arquivos relevantes e criar um plano mental ANTES de escrever código.

4. **Verificar actions in_progress.** Se outra action está em andamento, NÃO modificar arquivos relacionados a ela.

5. **Marcar in_progress.** Atualize o roadmap assim que iniciar trabalho

### Durante a Execução

6. **Foco estrito.** Trabalhar APENAS na action atual. Não refatorar código fora do escopo.

7. **Escopo mínimo.** Se encontrar problemas fora do escopo, documentar em nota mas NÃO corrigir.

8. **Testar incrementalmente.** Rodar `make test` após cada mudança significativa, não apenas no final.

9. **Commits atômicos.** Cada commit deve representar um passo lógico completo da action.

10. **Atualizar notas.** Atualize roadmap com progresso significativo.

### Ao Concluir uma Action

11. **Verificar completude.** Todos os critérios da action foram atendidos?

12. **Testes passando.** `make test` deve passar antes de marcar como completed.

13. **Atualizar documentação.** Se a action afeta CODEBASE.md ou ADR.md, atualizar agora.

14. **Commit descritivo.** Mensagem referenciando a action: `feat(1.03): Extract StatsParser class`

15. **Marcar completed.** Atualize roadmap alterando status e adicionando nota com resumo.

16. **Limpar contexto.** Antes da próxima action, descartar leituras de arquivos não mais necessários.

### Bugs Encontrados Durante Execução

17. **Sempre corrigir bugs.** Não acumular dívida técnica.

| Momento                 | Ação                                                |
| ----------------------- | --------------------------------------------------- |
| ANTES de iniciar action | Commit separado (`fix: ...`), depois iniciar action |
| DURANTE a action        | Incluir no commit da action, documentar na nota     |

18. **Documentar na nota do roadmap:** `"Completed. Also fixed: [descrição breve]"`

### Prevenindo Alucinação e Drift

19. **Checkpoint mental periódico.** Verifique o roadmap com frequência.

20. **Antes de cada commit:** Verificar se as mudanças correspondem à action descrita.

21. **Na dúvida, reler.** Nunca assumir - sempre consultar ROADMAP/ADR/CODEBASE.

22. **Web search frequente.** Pesquisar antes de implementar, ao encontrar erros, quando incerto.

### Prevenindo Drift de Contexto

- **Uma action por sessão** é o ideal. Se múltiplas, são do mesmo milestone.
- **Subagentes para exploração.** Usar `explore` para investigar código sem poluir contexto principal.
- **Notas como memória.** As notas do roadmap servem como memória entre sessões.
- **Reler roadmap** no início de cada nova sessão para reestabelecer contexto.

### Quando Parar e Pedir Ajuda

- Action está demorando mais que o esperado (> 3 tentativas de fix)
- Descobriu que a action precisa ser dividida em sub-actions
- Encontrou conflito com outra action in_progress
- Decisão arquitetural significativa não coberta no ADR.md

---

## Não Fazer

- **NÃO** ignorar testes falhando
- **NÃO** usar `@phpstan-ignore` ou similar sem justificativa
- **NÃO** commitar credenciais (usar .env)
- **NÃO** fazer push sem o usuário ter pedido
- **NÃO** prosseguir com erros pendentes
- **NÃO** usar `docker`/`docker-compose` (usar `podman`/`podman-compose`)
