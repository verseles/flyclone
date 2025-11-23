# Flyclone - Pontos de Melhoria Identificados

Este documento apresenta uma análise técnica detalhada do projeto Flyclone, identificando oportunidades de melhoria em diversas áreas: arquitetura, código, testes, documentação e segurança.

---

## 1. Arquitetura e Design

### 1.1 Classe Rclone muito grande (God Class)
**Arquivo:** `src/Rclone.php` (1490 linhas)

**Problema:** A classe `Rclone` concentra muitas responsabilidades:
- Gerenciamento de processo
- Parsing de progresso
- Parsing de estatísticas
- Conversão de unidades
- Execução de comandos
- Gerenciamento de configuração estática

**Sugestão:** Extrair responsabilidades para classes dedicadas:
```
src/
├── Rclone.php (orquestração principal)
├── Process/
│   ├── ProcessRunner.php (execução de processos)
│   └── ProcessConfigBuilder.php (construção de env vars)
├── Parser/
│   ├── ProgressParser.php (parsing de progresso)
│   ├── StatsParser.php (parsing de estatísticas)
│   └── OutputParser.php (parsing de saída JSON)
├── Util/
│   ├── SizeConverter.php (conversão de bytes)
│   └── DurationConverter.php (conversão de tempo)
```

### 1.2 Uso excessivo de propriedades estáticas
**Arquivo:** `src/Rclone.php:30-51`

**Problema:** Variáveis de configuração como `$timeout`, `$idleTimeout`, `$flags`, `$envs` são estáticas, causando:
- Estado global compartilhado entre instâncias
- Dificuldade em testes paralelos
- Comportamento inesperado em aplicações multi-tenant

**Sugestão:** Migrar para configuração por instância com Builder pattern:
```php
$rclone = Rclone::create($provider)
    ->withTimeout(300)
    ->withFlags(['verbose' => true])
    ->build();
```

### 1.3 Falta de interfaces para providers
**Arquivo:** `src/Providers/AbstractProvider.php`

**Problema:** Não há uma interface definida para providers, dificultando:
- Testes com mocks
- Implementações alternativas
- Documentação de contratos

**Sugestão:** Criar interface `ProviderInterface`:
```php
interface ProviderInterface
{
    public function provider(): string;
    public function name(): string;
    public function flags(): array;
    public function backend(?string $path = null): string;
    public function isDirAgnostic(): bool;
    public function isBucketAsDir(): bool;
    public function isListsAsTree(): bool;
}
```

---

## 2. Qualidade de Código

### 2.1 Type hints incompletos em AbstractProvider
**Arquivo:** `src/Providers/AbstractProvider.php:19-51`

**Problema:** Métodos sem return types declarados:
```php
public function provider()  // Falta : string
public function name()      // Falta : string
public function backend($path = NULL)  // Falta : string e tipo do parâmetro
```

**Sugestão:** Adicionar tipos completos:
```php
public function provider(): string
public function name(): string
public function backend(?string $path = null): string
```

### 2.2 Inconsistência no uso de NULL vs null
**Arquivos:** Vários

**Problema:** Uso inconsistente de `NULL` (maiúsculo) e `null` (minúsculo) em todo o código.

**Sugestão:** Padronizar para `null` (minúsculo) conforme PSR-12.

### 2.3 PHPDoc incompleto ou ausente em alguns métodos
**Arquivo:** `src/Providers/AbstractProvider.php`

**Problema:** Métodos como `provider()`, `name()`, `backend()` não têm documentação PHPDoc.

**Sugestão:** Adicionar documentação completa com `@param`, `@return`, `@throws`.

### 2.4 Propriedades não declaradas em Provider
**Arquivo:** `src/Providers/Provider.php:11-13`

**Problema:** As propriedades `$provider`, `$name`, `$flags` são declaradas em `Provider` mas acessadas em `AbstractProvider` sem declaração formal.

**Sugestão:** Declarar propriedades na classe abstrata ou usar traits.

### 2.5 Método executeProcess não retorna em catch
**Arquivo:** `src/Rclone.php:371-395`

**Problema:** O método `executeProcess` não tem return statement após os throws no catch, o que é detectado por analisadores estáticos:
```php
catch (ProcessFailedException $e) {
    $this->handleProcessFailure($e);  // Sempre lança exceção, mas isso não é explícito
}
```

**Sugestão:** Tornar explícito que `handleProcessFailure` sempre lança:
```php
#[\NoReturn]
private function handleProcessFailure(ProcessFailedException $exception): never
```

### 2.6 Magic strings para exit codes
**Arquivo:** `src/Rclone.php:441-451`

**Problema:** Exit codes do rclone são números mágicos:
```php
match ((int) $code) {
    1 => throw new SyntaxErrorException(...),
    3 => throw new DirectoryNotFoundException(...),
    // ...
}
```

**Sugestão:** Criar constantes ou enum para exit codes:
```php
enum RcloneExitCode: int
{
    case SYNTAX_ERROR = 1;
    case DIRECTORY_NOT_FOUND = 3;
    case FILE_NOT_FOUND = 4;
    // ...
}
```

---

## 3. Tratamento de Erros e Exceções

### 3.1 Exceção base muito simples
**Arquivo:** `src/Exception/RcloneException.php`

**Problema:** A exceção base não armazena contexto útil:
```php
class RcloneException extends \RuntimeException
{
}
```

**Sugestão:** Adicionar contexto:
```php
class RcloneException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $command = null,
        public readonly ?string $stderr = null,
        public readonly ?string $stdout = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

### 3.2 WriteOperationFailedException não utilizada
**Arquivo:** `src/Exception/WriteOperationFailedException.php`

**Problema:** Esta exceção existe mas não é utilizada em nenhum lugar do código.

**Sugestão:** Utilizar onde apropriado (ex: falhas em `rcat`, `touch`) ou remover se não necessária.

### 3.3 Erro silencioso em parseFinalStats
**Arquivo:** `src/Rclone.php:547-608`

**Problema:** Se o parsing de stats falhar, valores zerados são retornados silenciosamente.

**Sugestão:** Adicionar logging ou métricas para debugging quando parsing falha.

---

## 4. Performance

### 4.1 Regex compilado repetidamente
**Arquivo:** `src/Rclone.php:804-836`

**Problema:** Padrões regex são compilados a cada chamada de `parseProgress()`:
```php
$regex = '/' . $regex_base . '/iu';
$regex_xfr = '/' . $regex_base . '\s*\(xfr#(\d+\/\d+)\)/iu';
```

**Sugestão:** Definir como constantes de classe:
```php
private const PROGRESS_REGEX = '/...pattern.../iu';
private const PROGRESS_XFR_REGEX = '/...pattern.../iu';
```

### 4.2 Criação de objetos temporários em loops
**Arquivo:** `src/Rclone.php:892-910`

**Problema:** Em `ls()`, cada item do array é processado individualmente com `preg_replace`:
```php
foreach ($items_array as $item) {
    $time_string = preg_replace('/\.(\d{6})\d*Z$/', '.$1Z', $item->ModTime);
    // ...
}
```

**Sugestão:** Considerar processamento em batch ou lazy loading para listas grandes.

### 4.3 Falta de cache em guessBIN
**Arquivo:** `src/Rclone.php:774-795`

**Problema:** Embora use `spatie/once`, o padrão não é claro e pode ser melhorado.

**Sugestão:** Documentar claramente o comportamento de cache e considerar `WeakMap` para cache mais robusto.

---

## 5. Testes

### 5.1 Providers experimentais sem testes passando
**Arquivos:** `tests/Unit/CryptProviderTest.php`, `tests/Unit/UnionProviderTest.php`

**Problema:** Conforme documentado no README, os testes para `CryptProvider` e `UnionProvider` não passam.

**Sugestão:**
- Priorizar correção destes providers
- Ou marcar testes como `@group experimental` e skippar no CI principal

### 5.2 Falta de testes unitários isolados
**Arquivo:** `tests/Unit/`

**Problema:** A maioria dos testes são de integração, dependendo de processos rclone reais.

**Sugestão:** Adicionar testes unitários puros para:
- `prefix_flags()`
- `parseFinalStats()`
- `convertSizeToBytes()`
- `convertDurationToSeconds()`
- `formatBytes()`

### 5.3 Cobertura de código não reportada
**Arquivo:** `phpunit.xml:20`

**Problema:** A tag `<coverage/>` está vazia, não gerando relatórios.

**Sugestão:** Configurar cobertura:
```xml
<coverage includeUncoveredFiles="true">
    <report>
        <html outputDirectory="coverage"/>
        <clover outputFile="coverage.xml"/>
    </report>
</coverage>
```

### 5.4 Testes com dependências frágeis
**Arquivo:** `tests/Unit/AbstractProviderTest.php`

**Problema:** Testes usam `#[Depends]` extensivamente, criando cadeia longa de dependências. Se um teste falhar, todos os dependentes são pulados.

**Sugestão:** Reduzir acoplamento entre testes usando fixtures independentes.

---

## 6. Segurança

### 6.1 Potencial exposição de credenciais em logs
**Arquivo:** `src/Rclone.php`

**Problema:** Erros podem expor variáveis de ambiente com credenciais:
```php
$msg = 'Rclone process failed. Stdout: ' . trim($process->getOutput());
```

**Sugestão:** Sanitizar output antes de incluir em mensagens de erro:
```php
private function sanitizeOutput(string $output): string
{
    // Remover tokens, keys, passwords do output
    return preg_replace('/([A-Za-z0-9+\/=]{40,})/', '[REDACTED]', $output);
}
```

### 6.2 Validação de paths incompleta
**Arquivo:** `src/Rclone.php`

**Problema:** Não há validação contra path traversal ou caracteres especiais em caminhos.

**Sugestão:** Adicionar validação de paths:
```php
private function validatePath(string $path): void
{
    if (str_contains($path, '..')) {
        throw new \InvalidArgumentException('Path traversal not allowed');
    }
}
```

### 6.3 Temporary directories com permissões padrão
**Arquivo:** `src/Rclone.php:1191-1197`

**Problema:** Diretórios temporários são criados com permissões 0777:
```php
if (!mkdir($temp_dir, 0777, TRUE) && !is_dir($temp_dir)) {
```

**Sugestão:** Usar permissões mais restritivas (0700 ou 0750).

---

## 7. Documentação

### 7.1 Falta de CHANGELOG estruturado
**Problema:** O changelog está incorporado no README em um `<details>` tag.

**Sugestão:** Criar arquivo `CHANGELOG.md` seguindo o formato [Keep a Changelog](https://keepachangelog.com/).

### 7.2 Falta de CONTRIBUTING.md
**Problema:** Não há guia de contribuição detalhado.

**Sugestão:** Criar `CONTRIBUTING.md` com:
- Setup do ambiente de desenvolvimento
- Padrões de código
- Processo de PR
- Como rodar testes

### 7.3 Falta de documentação de API
**Problema:** Não há documentação gerada automaticamente (PHPDoc).

**Sugestão:** Configurar geração de docs com PHPDocumentor ou similar.

---

## 8. Configuração e Build

### 8.1 PHP 8.4 como requisito mínimo é muito restritivo
**Arquivo:** `composer.json:17`

**Problema:** `"php": ">=8.4"` limita muito a adoção, já que PHP 8.4 é muito recente.

**Sugestão:** Considerar suporte para PHP 8.2+ ou 8.3+ se possível.

### 8.2 Falta de análise estática no CI
**Arquivo:** `.github/workflows/phpunit.yml` (presumido)

**Problema:** Não há execução de PHPStan ou Psalm no CI.

**Sugestão:** Adicionar análise estática:
```yaml
- name: PHPStan
  run: vendor/bin/phpstan analyse src tests --level=6
```

### 8.3 Falta de verificação de estilo de código
**Problema:** Não há PHP-CS-Fixer ou PHP_CodeSniffer configurado.

**Sugestão:** Adicionar `.php-cs-fixer.php` e integrar no CI.

### 8.4 Script post-install-cmd com comportamento estranho
**Arquivo:** `composer.json:37-39`

**Problema:**
```json
"post-install-cmd": [
    "exit 0 || [ $COMPOSER_DEV_MODE -eq 0 ] || composer run security-check"
]
```
O `exit 0 ||` faz com que o security-check nunca execute.

**Sugestão:** Corrigir para:
```json
"post-install-cmd": [
    "[ $COMPOSER_DEV_MODE -eq 0 ] || composer run security-check || true"
]
```

---

## 9. Funcionalidades Faltantes

### 9.1 Não há suporte a streaming
**Problema:** Para arquivos grandes, todo o conteúdo é carregado em memória com `cat()`.

**Sugestão:** Adicionar método `catStream()` que retorne um stream resource.

### 9.2 Não há suporte a operações assíncronas
**Problema:** Todas as operações são síncronas e bloqueantes.

**Sugestão:** Considerar adicionar suporte a operações assíncronas com ReactPHP ou Amp.

### 9.3 Não há retry automático para erros temporários
**Problema:** `TemporaryErrorException` é lançada mas não há retry automático.

**Sugestão:** Adicionar retry policy configurável:
```php
$rclone = Rclone::create($provider)
    ->withRetryPolicy(maxRetries: 3, backoff: 'exponential')
    ->build();
```

### 9.4 Falta metadata de arquivo em operações
**Arquivo:** `README.md:446`

**Problema:** Como documentado no TODO, falta enviar meta details como file ID do Google Drive.

**Sugestão:** Implementar conforme planejado.

---

## 10. Providers

### 10.1 CryptProvider armazena provider em flags
**Arquivo:** `src/Providers/CryptProvider.php:25`

**Problema:**
```php
$this->flags['wrapped_provider'] = $wrappedProvider;
```
Isso mistura configuração com objetos.

**Sugestão:** Usar propriedade separada:
```php
private Provider $wrappedProvider;
```

### 10.2 Providers muito semelhantes com código repetido
**Arquivos:** `src/Providers/S3Provider.php`, `LocalProvider.php`, etc.

**Problema:** Muitos providers são quase idênticos, apenas definindo `$provider` e `$dirAgnostic`.

**Sugestão:** Considerar usar factory ou configuração:
```php
$s3 = Provider::create('s3', 'myS3', $flags);
```

---

## Priorização Recomendada

### Alta Prioridade
1. Corrigir script post-install-cmd no composer.json
2. Adicionar type hints completos
3. Corrigir providers experimentais (crypt, union)
4. Adicionar análise estática (PHPStan)

### Média Prioridade
5. Extrair classes do Rclone (refatoração)
6. Criar interfaces para providers
7. Melhorar tratamento de exceções
8. Adicionar testes unitários isolados

### Baixa Prioridade
9. Adicionar suporte a streaming
10. Considerar suporte a PHP 8.2/8.3
11. Melhorar documentação
12. Adicionar operações assíncronas

---

*Documento gerado em: 2025-11-23*
*Projeto analisado: verseles/flyclone*
