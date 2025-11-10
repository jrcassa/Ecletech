# Sistema de ACL (Access Control List) - Documentação

## Visão Geral

O sistema de ACL implementado permite controlar o acesso às rotas da API com base em permissões específicas atribuídas aos usuários através de roles (funções).

## Componentes do Sistema

### 1. Autenticação JWT

O sistema utiliza tokens JWT (JSON Web Tokens) para autenticação:

- **Geração de Token**: `app/core/JWT.php`
- **Autenticação**: `app/core/Autenticacao.php`
- **Middleware de Autenticação**: `app/middleware/IntermediarioAutenticacao.php`

### 2. Sistema de Permissões

O sistema de permissões é baseado em três conceitos principais:

#### Níveis (administrador_niveis)
Define os níveis hierárquicos de acesso (ex: superadmin, admin, gerente, operador, visualizador)

#### Roles (administrador_roles)
Funções atribuídas a cada nível que agrupam permissões (ex: "Super Admin Full Access", "Gerente de Usuários")

#### Permissões (administrador_permissions)
Permissões específicas para ações no sistema (ex: "usuarios.visualizar", "admins.criar")

### 3. Middleware ACL

O middleware ACL (`app/middleware/IntermediarioAcl.php`) permite validar permissões específicas em cada rota.

## Como Usar

### 1. Login e Obtenção do Token

```bash
# Fazer login
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@ecletech.com",
  "senha": "Admin@123"
}

# Resposta
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "usuario": {
    "id": 1,
    "nome": "Administrador",
    "email": "admin@ecletech.com",
    "nivel_id": 1
  }
}
```

### 2. Usar o Token em Requisições

Inclua o token no header `Authorization`:

```bash
GET /api/administradores
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### 3. Verificar Permissões Programaticamente

#### No Controlador

```php
use App\Middleware\IntermediarioAcl;

class MeuControlador
{
    public function minhaAcao(): void
    {
        $acl = new IntermediarioAcl();

        // Verificar uma permissão
        if ($acl->verificarPermissao('usuarios.editar')) {
            // Usuário tem permissão
        }

        // Verificar múltiplas permissões (AND - todas necessárias)
        if ($acl->verificarPermissoes(['usuarios.editar', 'usuarios.visualizar'])) {
            // Usuário tem todas as permissões
        }

        // Verificar múltiplas permissões (OR - qualquer uma)
        if ($acl->verificarAlgumaPermissao(['usuarios.editar', 'admins.editar'])) {
            // Usuário tem pelo menos uma das permissões
        }
    }
}
```

#### No Model

```php
use App\Models\ModelAdministrador;

$model = new ModelAdministrador();
$adminId = 1;

// Obter todas as permissões do administrador
$permissoes = $model->obterPermissoes($adminId);

// Obter apenas os códigos das permissões
$codigos = $model->obterCodigosPermissoes($adminId);

// Verificar se tem uma permissão
if ($model->temPermissao($adminId, 'usuarios.editar')) {
    // Tem permissão
}

// Obter administrador com permissões e roles
$admin = $model->buscarComPermissoes($adminId);
```

### 4. Proteger Rotas com ACL

#### Usando o Middleware ACL Diretamente

O middleware ACL pode ser aplicado de duas formas:

**Forma 1: Encadeado com método ->middleware()** (Recomendado)

```php
use App\Middleware\IntermediarioAcl;

// Rota que requer uma permissão específica
$roteador->get('/usuarios', [ControladorUsuario::class, 'listar'])
    ->middleware(IntermediarioAcl::requer('usuarios.visualizar'));

// Rota que requer múltiplas permissões (AND - todas necessárias)
$roteador->post('/usuarios', [ControladorUsuario::class, 'criar'])
    ->middleware(IntermediarioAcl::requer(['usuarios.criar', 'usuarios.visualizar'], 'AND'));

// Rota que requer qualquer uma das permissões (OR)
$roteador->put('/usuarios/{id}', [ControladorUsuario::class, 'atualizar'])
    ->middleware(IntermediarioAcl::requer(['usuarios.editar', 'admins.editar'], 'OR'));
```

**Exemplo Real das Rotas de Administradores:**

```php
use App\Controllers\ControllerAdministrador;
use App\Middleware\IntermediarioAcl;

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'administradores',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        // Listar - requer permissão de visualização
        $roteador->get('/', [ControllerAdministrador::class, 'listar'])
            ->middleware(IntermediarioAcl::requer('admins.visualizar'));

        // Criar - requer permissão de criação
        $roteador->post('/', [ControllerAdministrador::class, 'criar'])
            ->middleware(IntermediarioAcl::requer('admins.criar'));

        // Atualizar - requer permissão de edição
        $roteador->put('/{id}', [ControllerAdministrador::class, 'atualizar'])
            ->middleware(IntermediarioAcl::requer('admins.editar'));

        // Deletar - requer permissão de exclusão
        $roteador->delete('/{id}', [ControllerAdministrador::class, 'deletar'])
            ->middleware(IntermediarioAcl::requer('admins.deletar'));
    });
};
```

#### Usando o Middleware Admin (Verifica apenas se é admin)

```php
$roteador->grupo([
    'prefixo' => 'admin',
    'middleware' => ['auth', 'admin']
], function($roteador) {
    $roteador->get('/dashboard', [ControladorDashboard::class, 'index']);
});
```

## Estrutura de Permissões Padrão

O sistema vem com as seguintes permissões pré-configuradas:

### Módulo: Usuários
- `usuarios.visualizar` - Visualizar usuários
- `usuarios.criar` - Criar novos usuários
- `usuarios.editar` - Editar usuários
- `usuarios.deletar` - Deletar usuários

### Módulo: Administradores
- `admins.visualizar` - Visualizar administradores
- `admins.criar` - Criar novos administradores
- `admins.editar` - Editar administradores
- `admins.deletar` - Deletar administradores

### Módulo: Níveis
- `niveis.visualizar` - Visualizar níveis
- `niveis.criar` - Criar novos níveis
- `niveis.editar` - Editar níveis
- `niveis.deletar` - Deletar níveis

### Módulo: Roles
- `roles.visualizar` - Visualizar roles
- `roles.criar` - Criar novos roles
- `roles.editar` - Editar roles
- `roles.deletar` - Deletar roles

### Módulo: Permissões
- `permissoes.visualizar` - Visualizar permissões
- `permissoes.criar` - Criar novas permissões
- `permissoes.editar` - Editar permissões
- `permissoes.deletar` - Deletar permissões

### Módulo: Configurações
- `config.visualizar` - Visualizar configurações
- `config.editar` - Editar configurações

### Módulo: Auditoria
- `auditoria.visualizar` - Visualizar logs de auditoria
- `auditoria.deletar` - Deletar logs de auditoria

### Módulo: Relatórios
- `relatorios.visualizar` - Visualizar relatórios
- `relatorios.exportar` - Exportar relatórios

## Endpoints da API

### Autenticação

```bash
# Login
POST /api/auth/login
Body: { "email": "...", "senha": "..." }

# Refresh Token
POST /api/auth/refresh
Body: { "refresh_token": "..." }

# Logout
POST /api/auth/logout
Headers: Authorization: Bearer {token}

# Obter usuário autenticado
GET /api/auth/me
Headers: Authorization: Bearer {token}

# Alterar senha
POST /api/auth/alterar-senha
Headers: Authorization: Bearer {token}
Body: { "senha_atual": "...", "senha_nova": "..." }
```

### Administradores

```bash
# Listar administradores (requer: admins.visualizar)
GET /api/administradores
Headers: Authorization: Bearer {token}

# Buscar administrador (requer: admins.visualizar)
GET /api/administradores/{id}
Headers: Authorization: Bearer {token}

# Criar administrador (requer: admins.criar)
POST /api/administradores
Headers: Authorization: Bearer {token}
Body: { "nome": "...", "email": "...", "senha": "...", "nivel_id": 1 }

# Atualizar administrador (requer: admins.editar)
PUT /api/administradores/{id}
Headers: Authorization: Bearer {token}
Body: { "nome": "...", "email": "..." }

# Deletar administrador (requer: admins.deletar)
DELETE /api/administradores/{id}
Headers: Authorization: Bearer {token}
```

### Roles

```bash
# Listar roles (requer: roles.visualizar)
GET /api/roles
Headers: Authorization: Bearer {token}

# Buscar role (requer: roles.visualizar)
GET /api/roles/{id}
Headers: Authorization: Bearer {token}

# Obter permissões de uma role (requer: roles.visualizar)
GET /api/roles/{id}/permissoes
Headers: Authorization: Bearer {token}

# Atribuir permissões a uma role (requer: roles.editar)
POST /api/roles/{id}/permissoes
Headers: Authorization: Bearer {token}
Body: { "permissoes": [1, 2, 3] }
```

### Permissões

```bash
# Listar permissões (requer: permissoes.visualizar)
GET /api/permissoes
Headers: Authorization: Bearer {token}

# Buscar permissão (requer: permissoes.visualizar)
GET /api/permissoes/{id}
Headers: Authorization: Bearer {token}

# Listar permissões agrupadas por módulo (requer: permissoes.visualizar)
GET /api/permissoes/modulos/listar
Headers: Authorization: Bearer {token}
```

## Exemplo Completo de Uso

### 1. Fazer Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ecletech.com","senha":"Admin@123"}'
```

### 2. Usar o Token para Acessar Recursos

```bash
curl -X GET http://localhost/api/administradores \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

### 3. Listar Permissões do Usuário

```bash
curl -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

## Segurança

### Boas Práticas

1. **Sempre use HTTPS em produção** para proteger os tokens JWT
2. **Configure uma chave secreta forte** no arquivo `.env`:
   ```
   JWT_SECRET=sua-chave-secreta-muito-forte-e-aleatoria
   ```
3. **Defina tempos de expiração apropriados**:
   ```
   JWT_EXPIRATION=3600          # 1 hora
   JWT_REFRESH_EXPIRATION=86400 # 24 horas
   ```
4. **Revogue tokens quando necessário** usando a tabela `administrador_tokens`
5. **Monitore tentativas de acesso não autorizado** através dos logs de auditoria

### Rate Limiting

O sistema inclui rate limiting para prevenir ataques de força bruta. Configure no `.env`:

```
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

## Troubleshooting

### Token Expirado

Se receber erro `401 Unauthorized` com mensagem de token expirado, use o refresh token:

```bash
POST /api/auth/refresh
Body: { "refresh_token": "..." }
```

### Permissão Negada

Se receber erro `403 Forbidden`, verifique:

1. O usuário está autenticado?
2. O usuário tem a permissão necessária?
3. A permissão está ativa no banco de dados?
4. A role está associada ao nível correto?

### Verificar Permissões de um Usuário

Use a rota `/api/auth/me` para ver as informações do usuário autenticado e suas permissões.

## Contribuindo

Para adicionar novas permissões ao sistema:

1. Adicione a permissão na tabela `administrador_permissions`
2. Associe a permissão a uma ou mais roles na tabela `administrador_role_permissions`
3. Use o middleware ACL nas rotas que requerem a nova permissão
