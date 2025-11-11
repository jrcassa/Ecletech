# Tabela de Tokens CSRF

## Descrição

A tabela `csrf_tokens` armazena tokens CSRF (Cross-Site Request Forgery) de forma persistente no banco de dados, permitindo validação mais robusta e controle granular sobre os tokens.

**STATUS: ✅ INTEGRAÇÃO COMPLETA**

A classe `TokenCsrf` foi atualizada para usar automaticamente o `ModelCsrfToken` e o banco de dados quando disponível, com fallback para sessões PHP caso haja erro de conexão.

## Estrutura da Tabela

```sql
csrf_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token           VARCHAR(64) NOT NULL UNIQUE,
    session_id      VARCHAR(255) NULL,
    colaborador_id  INT UNSIGNED NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    usado           TINYINT(1) NOT NULL DEFAULT 0,
    criado_em       DATETIME NOT NULL,
    expira_em       DATETIME NOT NULL,
    usado_em        DATETIME NULL
)
```

## Como Executar a Migration

### ⚠️ IMPORTANTE: Execute a migration antes de usar o sistema em produção

### Opção 1: Via MySQL CLI

```bash
# Conecte-se ao MySQL
mysql -u seu_usuario -p seu_banco_de_dados

# Execute a migration
source database/migrations/006_criar_tabela_csrf_tokens.sql
```

### Opção 2: Via script PHP

```bash
# Na raiz do projeto
php executar_migration_csrf.php
```

### Opção 3: Via client MySQL

Copie e cole o conteúdo do arquivo `database/migrations/006_criar_tabela_csrf_tokens.sql` diretamente no seu client MySQL (phpMyAdmin, MySQL Workbench, etc.).

### Verificar se a migration foi executada

```sql
SHOW TABLES LIKE 'csrf_tokens';
```

Se retornar resultado, a tabela foi criada com sucesso.

## Como Usar

### Uso Normal (Automático)

A classe `TokenCsrf` já está integrada com o banco de dados. **Não é necessário fazer nada diferente:**

```php
use App\Core\TokenCsrf;

$csrf = new TokenCsrf();

// Gerar token (salva automaticamente no banco E na sessão)
$token = $csrf->gerar();

// Validar token (valida no banco primeiro, depois sessão como fallback)
$valido = $csrf->validar($token);

// Obter token atual
$token = $csrf->obter();
```

### Funcionalidades Adicionais

```php
// Limpar tokens expirados (útil para cron jobs)
$quantidadeRemovida = $csrf->limparTokensExpirados();

// Limpar tokens usados há mais de X dias
$quantidadeRemovida = $csrf->limparTokensUsados(7); // 7 dias

// Obter estatísticas
$stats = $csrf->obterEstatisticas();
// Retorna: ['usando_banco_dados' => true, 'tokens_ativos' => 42, 'tokens_sessao_atual' => 1]
```

### Uso Direto do Model (Avançado)

Se precisar de controle mais granular, você pode usar o `ModelCsrfToken` diretamente:

```php
use App\Models\Csrf\ModelCsrfToken;

$model = new ModelCsrfToken();

// Criar um novo token manualmente
$tokenId = $model->criar([
    'token' => bin2hex(random_bytes(32)),
    'session_id' => session_id(),
    'colaborador_id' => $_SESSION['colaborador_id'] ?? null,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'expira_em' => date('Y-m-d H:i:s', time() + 3600)
]);

// Buscar tokens de um colaborador
$tokens = $model->buscarPorColaborador($colaboradorId);
```

## Integração com TokenCsrf.php

✅ **INTEGRAÇÃO COMPLETA**

A classe `TokenCsrf` (`App/Core/TokenCsrf.php`) foi atualizada para:

1. **Usar banco de dados automaticamente** quando disponível
2. **Fallback para sessões** caso haja erro de conexão ao banco
3. **One-time tokens** - tokens são marcados como "usado" após validação
4. **Rastreamento completo** - armazena IP, user agent, session_id e colaborador_id
5. **Tokens de uso único** - cada token só pode ser validado uma vez
6. **Renovação automática** - novo token é gerado e enviado após cada validação bem-sucedida

## Renovação Automática de Tokens

✅ **IMPLEMENTADO**

O sistema implementa renovação automática de tokens CSRF para máxima segurança:

### Como Funciona

1. Cliente envia requisição POST/PUT/PATCH/DELETE com token CSRF no header `X-CSRF-Token`
2. Middleware valida o token e marca como usado (one-time)
3. Middleware gera automaticamente um novo token
4. Novo token é enviado no header `X-New-CSRF-Token` da resposta
5. Cliente JavaScript captura e armazena o novo token automaticamente

### Fluxo de Renovação

```
┌─────────────┐                      ┌─────────────┐
│   Cliente   │                      │   Servidor  │
└──────┬──────┘                      └──────┬──────┘
       │                                    │
       │ POST /api/colaboradores            │
       │ X-CSRF-Token: abc123               │
       ├───────────────────────────────────>│
       │                                    │
       │                                    │ 1. Valida token abc123
       │                                    │ 2. Marca como usado
       │                                    │ 3. Gera novo token xyz789
       │                                    │
       │ 200 OK                             │
       │ X-New-CSRF-Token: xyz789           │
       │<───────────────────────────────────┤
       │                                    │
       │ (JavaScript armazena xyz789)       │
       │                                    │
       │ próxima requisição usa xyz789      │
       │                                    │
```

### Integração com Frontend

O JavaScript (`public_html/js/API.js`) já está configurado para:

```javascript
// Captura automaticamente o novo token após cada requisição
const newCsrfToken = response.headers.get('X-New-CSRF-Token');
if (newCsrfToken) {
    this.setCsrfToken(newCsrfToken);
}
```

### Benefícios

- **Máxima Segurança**: Cada token só pode ser usado uma vez
- **Transparente**: Renovação é automática, sem intervenção do desenvolvedor
- **Sem Interrupções**: Cliente sempre tem um token válido
- **Proteção contra Replay Attacks**: Tokens antigos não podem ser reutilizados

## Limpeza Automática

### Via Evento MySQL (Automático)

A migration cria um evento MySQL que executa **diariamente** para limpar:
- Tokens expirados há mais de 7 dias
- Tokens usados há mais de 1 dia

Para verificar se o evento está ativo:

```sql
SHOW EVENTS LIKE 'limpar_csrf_tokens_expirados';
```

### Via Cron Job (Opcional)

Você também pode configurar um cron job para executar a limpeza via PHP:

```bash
# Adicione ao crontab (executa diariamente às 3h da manhã)
0 3 * * * cd /caminho/do/projeto && php -r "require 'App/Core/TokenCsrf.php'; \$csrf = new App\Core\TokenCsrf(); \$csrf->limparTokensExpirados(); \$csrf->limparTokensUsados();"
```

## Vantagens

1. **Persistência**: Tokens sobrevivem a reinicializações do servidor
2. **Auditoria**: Histórico completo de uso de tokens
3. **Segurança**: Validação por IP, user agent e sessão
4. **Controle**: Tokens de uso único (flag `usado`)
5. **Escalabilidade**: Funciona em ambientes com múltiplos servidores

## Segurança

- Tokens são únicos (constraint UNIQUE)
- Validação inclui sessão, IP e user agent
- Tokens expiram automaticamente
- Tokens podem ser usados apenas uma vez
- Limpeza automática de tokens antigos
