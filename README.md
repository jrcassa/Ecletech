# Ecletech API

API RESTful moderna desenvolvida em PHP puro com arquitetura limpa, focada em segurança e performance.

## Características

- **Arquitetura Limpa**: Separação de responsabilidades com Core, Models, Controllers, Middleware e Helpers
- **Segurança Avançada**:
  - Autenticação JWT
  - Proteção CSRF
  - Rate Limiting
  - Sanitização XSS
  - Cabeçalhos de segurança
  - Validação de dados
- **Sistema de Permissões**: Níveis, Roles e Permissions com controle granular de acesso
- **Auditoria**: Registro completo de ações, logins e requisições
- **RESTful**: API seguindo padrões REST
- **PHP Puro**: Sem dependências externas

## Estrutura do Projeto

```
Ecletech/
├── app/
│   ├── controllers/          # Controladores da aplicação
│   ├── core/                 # Componentes principais
│   │   ├── Autenticacao.php
│   │   ├── BancoDados.php
│   │   ├── CarregadorEnv.php
│   │   ├── Configuracao.php
│   │   ├── GerenciadorUsuario.php
│   │   ├── JWT.php
│   │   ├── LimitadorRequisicao.php
│   │   ├── RegistroAuditoria.php
│   │   ├── Roteador.php
│   │   └── TokenCsrf.php
│   ├── helpers/              # Funções auxiliares
│   │   ├── AuxiliarData.php
│   │   ├── AuxiliarResposta.php
│   │   ├── AuxiliarSanitizacao.php
│   │   └── AuxiliarValidacao.php
│   ├── middleware/           # Middlewares
│   │   ├── MiddlewareAdmin.php
│   │   ├── MiddlewareAutenticacao.php
│   │   ├── MiddlewareCabecalhosSeguranca.php
│   │   ├── MiddlewareCors.php
│   │   ├── MiddlewareCsrf.php
│   │   ├── MiddlewareLimiteRequisicao.php
│   │   └── MiddlewareSanitizadorXss.php
│   ├── models/               # Models de dados
│   │   ├── ModelAdministrador.php
│   │   ├── ModelAdministradorNivel.php
│   │   ├── ModelAdministradorPermission.php
│   │   ├── ModelAdministradorRole.php
│   │   └── ModelAdministradorRolePermission.php
│   ├── routes/               # Definição de rotas
│   │   └── api.php
│   └── services/             # Serviços da aplicação
├── database/
│   └── migrations/           # Scripts de migração do banco
├── public_html/
│   └── api/
│       └── index.php         # Ponto de entrada
└── .env                      # Configurações de ambiente
```

## Requisitos

- PHP 8.1 ou superior
- MySQL 5.7 ou superior / MariaDB 10.2 ou superior
- Extensões PHP:
  - PDO
  - pdo_mysql
  - mbstring
  - json

## Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/jrcassa/Ecletech.git
cd Ecletech
```

### 2. Configure o arquivo .env

Copie o arquivo `.env` e ajuste as configurações conforme seu ambiente:

```bash
# Configurações do Banco de Dados
DB_HOST=localhost
DB_PORTA=3306
DB_NOME=ecletech
DB_USUARIO=root
DB_SENHA=suasenha

# JWT - IMPORTANTE: Altere a chave secreta em produção
JWT_CHAVE_SECRETA=sua_chave_secreta_super_segura_aqui
```

### 3. Crie o banco de dados

```sql
CREATE DATABASE ecletech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Execute as migrations

Execute os scripts SQL na ordem:

```bash
mysql -u root -p ecletech < database/migrations/001_criar_tabela_administrador_niveis.sql
mysql -u root -p ecletech < database/migrations/002_criar_tabela_administradores.sql
mysql -u root -p ecletech < database/migrations/003_criar_tabela_administrador_roles.sql
mysql -u root -p ecletech < database/migrations/004_criar_tabela_administrador_permissions.sql
mysql -u root -p ecletech < database/migrations/005_criar_tabela_administrador_role_permissions.sql
```

### 5. Configure o servidor web

#### Apache

Crie um arquivo `.htaccess` em `public_html/api/`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

Configure o VirtualHost:

```apache
<VirtualHost *:80>
    ServerName ecletech.local
    DocumentRoot /caminho/para/Ecletech/public_html/api

    <Directory /caminho/para/Ecletech/public_html/api>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name ecletech.local;
    root /caminho/para/Ecletech/public_html/api;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Uso da API

### Credenciais Padrão

- **Email**: admin@ecletech.com
- **Senha**: Admin@123

### Endpoints

#### Autenticação

**Login**
```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@ecletech.com",
  "senha": "Admin@123"
}
```

**Resposta**
```json
{
  "sucesso": true,
  "mensagem": "Login realizado com sucesso",
  "dados": {
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
}
```

**Logout**
```http
POST /auth/logout
Authorization: Bearer {access_token}
```

**Renovar Token**
```http
POST /auth/refresh
Content-Type: application/json

{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Obter Usuário Autenticado**
```http
GET /auth/me
Authorization: Bearer {access_token}
```

**Alterar Senha**
```http
POST /auth/alterar-senha
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "senha_atual": "Admin@123",
  "nova_senha": "NovaSenha@456",
  "confirmar_senha": "NovaSenha@456"
}
```

#### Administradores

**Listar Administradores**
```http
GET /administradores?pagina=1&por_pagina=20
Authorization: Bearer {access_token}
```

**Buscar Administrador**
```http
GET /administradores/{id}
Authorization: Bearer {access_token}
```

**Criar Administrador**
```http
POST /administradores
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "senha": "Senha@123",
  "nivel_id": 2
}
```

**Atualizar Administrador**
```http
PUT /administradores/{id}
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "nome": "João Silva Santos",
  "email": "joao.silva@exemplo.com"
}
```

**Deletar Administrador**
```http
DELETE /administradores/{id}
Authorization: Bearer {access_token}
```

## Recursos de Segurança

### 1. Autenticação JWT

- Tokens de acesso com expiração configurável
- Refresh tokens para renovação
- Tokens armazenados no banco para controle

### 2. Proteção CSRF

- Tokens CSRF para requisições que modificam dados
- Validação automática via middleware

### 3. Rate Limiting

- Limite de requisições por IP
- Proteção contra brute force
- Bloqueio temporário após tentativas excessivas

### 4. Sanitização

- Proteção contra XSS
- Limpeza automática de inputs
- Validação rigorosa de dados

### 5. Cabeçalhos de Segurança

- X-Content-Type-Options
- X-Frame-Options
- X-XSS-Protection
- Content-Security-Policy
- E mais...

## Sistema de Permissões

A API implementa um sistema completo de permissões com três níveis:

1. **Níveis**: Grupos principais de acesso (Super Admin, Admin, Gerente, etc)
2. **Roles**: Funções específicas dentro de cada nível
3. **Permissions**: Permissões granulares por módulo e ação

### Exemplo de Verificação de Permissão

```php
$middleware = new MiddlewareAdmin();
if ($middleware->verificarPermissao('usuarios.criar')) {
    // Usuário tem permissão para criar usuários
}
```

## Auditoria

O sistema registra automaticamente:

- **Ações**: Criação, atualização e exclusão de registros
- **Logins**: Tentativas bem-sucedidas e falhas
- **Requisições**: Todas as chamadas à API (opcional)

### Visualizar Logs

```php
$auditoria = new RegistroAuditoria();
$historico = $auditoria->buscarHistorico([
    'usuario_id' => 1,
    'data_inicio' => '2025-11-01',
    'limite' => 50
]);
```

## Helpers Disponíveis

### AuxiliarValidacao

```php
// Validar email
AuxiliarValidacao::email('teste@exemplo.com');

// Validar CPF
AuxiliarValidacao::cpf('123.456.789-00');

// Validação completa
$erros = AuxiliarValidacao::validar($dados, [
    'nome' => 'obrigatorio|min:3',
    'email' => 'obrigatorio|email',
    'idade' => 'inteiro|entre:18,100'
]);
```

### AuxiliarSanitizacao

```php
// Remover XSS
$limpo = AuxiliarSanitizacao::antiXss($input);

// Sanitizar email
$email = AuxiliarSanitizacao::email($input);

// Criar slug
$slug = AuxiliarSanitizacao::slug('Título do Artigo');
```

### AuxiliarData

```php
// Formatar data
$data = AuxiliarData::formatar('2025-11-10', 'd/m/Y');

// Calcular idade
$idade = AuxiliarData::idade('1990-01-15');

// Data relativa
$relativa = AuxiliarData::relativa('2025-11-08'); // "há 2 dias"
```

### AuxiliarResposta

```php
// Resposta de sucesso
AuxiliarResposta::sucesso($dados, 'Operação realizada');

// Resposta de erro
AuxiliarResposta::erro('Mensagem de erro', 400);

// Resposta paginada
AuxiliarResposta::paginado($dados, $total, $pagina, $porPagina);
```

## Desenvolvimento

### Adicionar Nova Rota

1. Edite `app/routes/api.php`:

```php
$roteador->get('/minha-rota', [MeuControlador::class, 'meuMetodo']);
```

2. Crie o controlador em `app/controllers/MeuControlador.php`:

```php
<?php

namespace App\Controllers;

use App\Helpers\AuxiliarResposta;

class MeuControlador
{
    public function meuMetodo(): void
    {
        AuxiliarResposta::sucesso(['mensagem' => 'Olá Mundo!']);
    }
}
```

### Criar Novo Middleware

1. Crie o arquivo em `app/middleware/`:

```php
<?php

namespace App\Middleware;

class MeuMiddleware
{
    public function handle(): bool
    {
        // Sua lógica aqui
        return true; // ou false para bloquear
    }
}
```

2. Registre no roteador:

```php
$roteador->registrarMiddleware('meu', MeuMiddleware::class);
```

3. Use na rota:

```php
$roteador->get('/rota', [Controlador::class, 'metodo'])
    ->middleware('meu');
```

## Produção

Antes de colocar em produção:

1. **Altere a chave JWT** no `.env`
2. **Desabilite o debug**: `APP_DEBUG=false`
3. **Configure HTTPS** e habilite HSTS
4. **Revise as permissões de arquivo**
5. **Configure backups automáticos**
6. **Monitore os logs de auditoria**

## Licença

Este projeto está sob a licença MIT.

## Suporte

Para dúvidas ou problemas, abra uma issue no repositório.

---

**Desenvolvido com ❤️ usando PHP puro**
