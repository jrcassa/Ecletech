# Tabela de Tokens CSRF

## Descrição

A tabela `csrf_tokens` foi criada para armazenar tokens CSRF (Cross-Site Request Forgery) de forma persistente no banco de dados, permitindo validação mais robusta e controle granular sobre os tokens.

## Estrutura da Tabela

```sql
csrf_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token           VARCHAR(64) NOT NULL UNIQUE,
    session_id      VARCHAR(255) NULL,
    usuario_id      INT UNSIGNED NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    usado           TINYINT(1) NOT NULL DEFAULT 0,
    criado_em       DATETIME NOT NULL,
    expira_em       DATETIME NOT NULL,
    usado_em        DATETIME NULL
)
```

## Como Executar a Migration

```bash
# Conecte-se ao MySQL
mysql -u seu_usuario -p seu_banco_de_dados

# Execute a migration
source database/migrations/006_criar_tabela_csrf_tokens.sql
```

Ou via PHP:

```php
$db = BancoDados::obterInstancia();
$sql = file_get_contents(__DIR__ . '/migrations/006_criar_tabela_csrf_tokens.sql');
$db->obterConexao()->exec($sql);
```

## Como Usar o Model

```php
use App\Models\ModelCsrfToken;

$model = new ModelCsrfToken();

// Criar um novo token
$tokenId = $model->criar([
    'token' => bin2hex(random_bytes(32)),
    'session_id' => session_id(),
    'usuario_id' => $_SESSION['usuario_id'] ?? null,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'expira_em' => date('Y-m-d H:i:s', time() + 3600) // 1 hora
]);

// Validar um token
$valido = $model->validar($token, session_id());

// Marcar token como usado
$model->marcarComoUsado($token);

// Limpar tokens expirados
$model->limparExpirados();
```

## Integração com TokenCsrf.php

A classe `TokenCsrf` pode ser atualizada para usar o banco de dados em vez de (ou além de) sessões PHP:

```php
// Em TokenCsrf::gerar()
$token = bin2hex(random_bytes(32));
$model = new ModelCsrfToken();
$model->criar([
    'token' => $token,
    'session_id' => session_id(),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'expira_em' => date('Y-m-d H:i:s', time() + $this->expiracao)
]);

// Em TokenCsrf::validar()
$model = new ModelCsrfToken();
if ($model->validar($token, session_id())) {
    $model->marcarComoUsado($token);
    return true;
}
return false;
```

## Limpeza Automática

A migration cria um evento MySQL que executa diariamente para limpar:
- Tokens expirados há mais de 7 dias
- Tokens usados há mais de 1 dia

Para verificar se o evento está ativo:

```sql
SHOW EVENTS LIKE 'limpar_csrf_tokens_expirados';
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
