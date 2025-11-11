# Sistema de Proteção contra Brute Force - MySQL

## 📋 Visão Geral

Este sistema implementa proteção robusta contra ataques de brute force usando **banco de dados MySQL** em vez de sessões ou cookies. Todos os dados são persistidos no banco para garantir segurança e rastreabilidade.

## 🎯 Características

### ✅ Implementações
- ✅ Rastreamento de tentativas de login por **Email** e **IP**
- ✅ Bloqueio automático após exceder tentativas máximas
- ✅ Bloqueios temporários e permanentes
- ✅ Interface administrativa completa
- ✅ Estatísticas em tempo real
- ✅ API RESTful para gerenciamento
- ✅ Limpeza automática de dados antigos (30 dias)
- ✅ Suporte IPv4 e IPv6

### 🛡️ Proteções Implementadas
1. **Por Email**: Protege contas específicas de ataques
2. **Por IP**: Bloqueia IPs maliciosos
3. **Combinado**: Bloqueio de email+IP simultaneamente

## 📦 Estrutura Criada

### Banco de Dados
```
database/migrations/
└── 010_criar_tabela_login_attempts.sql
    ├── Tabela: login_attempts (registro de tentativas)
    ├── Tabela: login_bloqueios (bloqueios ativos)
    └── Event: limpar_login_attempts_antigos (limpeza automática)
```

### Backend (PHP)
```
App/
├── Models/Login/
│   └── ModelLoginAttempt.php          # Model completo com todas as operações
├── Controllers/Login/
│   └── ControllerLoginAttempt.php     # Controller para API REST
├── Routes/
│   └── login_attempts.php             # Rotas da API
└── Core/
    └── Autenticacao.php               # ATUALIZADO para usar MySQL
```

### Frontend
```
public_html/
├── brute_force.html                   # Interface administrativa
└── js/
    └── brute_force.js                 # JavaScript da interface
```

## 🚀 Instalação

### 1. Executar Migration

```bash
# Conecte ao MySQL
mysql -u root -p ecletech

# Execute a migration
source /home/user/Ecletech/database/migrations/010_criar_tabela_login_attempts.sql

# Verifique se as tabelas foram criadas
SHOW TABLES LIKE 'login_%';

# Verifique se o event foi criado
SHOW EVENTS;
```

### 2. Configurações (.env)

As seguintes configurações foram adicionadas ao arquivo `.env`:

```env
# Proteção contra Brute Force (MySQL)
BRUTE_FORCE_MAX_TENTATIVAS="5"           # Máximo de tentativas antes do bloqueio
BRUTE_FORCE_JANELA_TEMPO="15"            # Janela de tempo em minutos
BRUTE_FORCE_TEMPO_BLOQUEIO="30"          # Tempo de bloqueio em minutos
BRUTE_FORCE_RASTREAR_POR_IP="true"       # Habilitar rastreamento por IP
BRUTE_FORCE_RASTREAR_POR_EMAIL="true"    # Habilitar rastreamento por Email
```

**Configurações Explicadas:**
- `MAX_TENTATIVAS`: Após 5 tentativas falhadas, a conta/IP é bloqueada
- `JANELA_TEMPO`: As tentativas são contadas nos últimos 15 minutos
- `TEMPO_BLOQUEIO`: O bloqueio dura 30 minutos (pode ser permanente via admin)

### 3. Verificar Permissões

Certifique-se de que as permissões necessárias existem:

```sql
-- Verificar permissões de auditoria
SELECT * FROM colaborador_permissions
WHERE codigo LIKE 'auditoria.%' OR codigo LIKE 'config.%';
```

Se não existirem, adicione:

```sql
INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo) VALUES
('Visualizar Auditoria', 'auditoria.visualizar', 'Permite visualizar logs e tentativas de login', 'auditoria', 1),
('Editar Configurações', 'config.editar', 'Permite editar configurações do sistema', 'config', 1);
```

## 📊 Estrutura das Tabelas

### login_attempts
Registra **todas** as tentativas de login (sucesso e falha):

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | BIGINT | Identificador único |
| email | VARCHAR(150) | Email utilizado na tentativa |
| ip_address | VARCHAR(45) | IP do cliente (IPv4/IPv6) |
| user_agent | VARCHAR(500) | User agent do navegador |
| tentativa_sucesso | TINYINT | 0=Falha, 1=Sucesso |
| motivo_falha | ENUM | senha_invalida, usuario_nao_encontrado, conta_inativa, bloqueado, outro |
| criado_em | DATETIME | Data/hora da tentativa |

**Índices:** email, ip_address, criado_em (para queries rápidas)

### login_bloqueios
Gerencia bloqueios ativos:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | BIGINT | Identificador único |
| tipo_bloqueio | ENUM | ip, email, ambos |
| email | VARCHAR(150) | Email bloqueado (se aplicável) |
| ip_address | VARCHAR(45) | IP bloqueado (se aplicável) |
| tentativas_falhadas | INT | Número de tentativas que causaram bloqueio |
| bloqueado_ate | DATETIME | Data/hora de expiração do bloqueio |
| bloqueado_permanente | TINYINT | 0=Temporário, 1=Permanente |
| motivo | VARCHAR(500) | Motivo do bloqueio |
| criado_em | DATETIME | Data/hora de criação |
| atualizado_em | DATETIME | Última atualização |

## 🔌 API Endpoints

### Tentativas de Login

#### GET /api/login-attempts
Lista todas as tentativas de login com filtros e paginação.

**Query Parameters:**
- `email` (string): Filtrar por email
- `ip_address` (string): Filtrar por IP
- `sucesso` (boolean): 0=Falha, 1=Sucesso
- `data_inicio` (datetime): Data inicial
- `data_fim` (datetime): Data final
- `pagina` (int): Página atual
- `por_pagina` (int): Itens por página

**Resposta:**
```json
{
  "sucesso": true,
  "dados": {
    "itens": [
      {
        "id": 1,
        "email": "usuario@example.com",
        "ip_address": "192.168.1.1",
        "user_agent": "Mozilla/5.0...",
        "tentativa_sucesso": 0,
        "motivo_falha": "senha_invalida",
        "criado_em": "2025-11-11 10:30:00"
      }
    ],
    "paginacao": {
      "total": 100,
      "pagina_atual": 1,
      "por_pagina": 20,
      "total_paginas": 5
    }
  }
}
```

#### GET /api/login-attempts/estatisticas
Retorna estatísticas gerais do sistema.

**Resposta:**
```json
{
  "sucesso": true,
  "dados": {
    "tentativas_24h": 150,
    "falhas_24h": 45,
    "sucesso_24h": 105,
    "bloqueios_ativos": 5,
    "ips_bloqueados": 3,
    "emails_bloqueados": 2,
    "taxa_sucesso": 70.0,
    "top_ips": [
      {"ip_address": "192.168.1.100", "total": 12}
    ],
    "top_emails": [
      {"email": "teste@example.com", "total": 8}
    ]
  }
}
```

### Bloqueios

#### GET /api/login-bloqueios
Lista bloqueios ativos.

**Query Parameters:**
- `tipo` (string): ip, email, ambos
- `email` (string): Filtrar por email
- `ip_address` (string): Filtrar por IP

#### POST /api/login-bloqueios
Cria bloqueio manual.

**Body:**
```json
{
  "tipo": "email",
  "email": "usuario@example.com",
  "ip_address": "192.168.1.1",
  "permanente": false,
  "motivo": "Suspeita de ataque"
}
```

#### DELETE /api/login-bloqueios/{id}
Remove bloqueio específico por ID.

#### DELETE /api/login-bloqueios/email
Desbloqueia um email.

**Body:**
```json
{
  "email": "usuario@example.com"
}
```

#### DELETE /api/login-bloqueios/ip
Desbloqueia um IP.

**Body:**
```json
{
  "ip_address": "192.168.1.1"
}
```

#### GET /api/login-bloqueios/verificar
Verifica status de bloqueio.

**Query Parameters:**
- `email` (string)
- `ip_address` (string)

## 🖥️ Interface Administrativa

Acesse: `http://localhost/brute_force.html`

### Funcionalidades:

#### 1. Dashboard de Estatísticas
- Total de tentativas nas últimas 24h
- Taxa de sucesso vs falha
- Bloqueios ativos
- Top 5 IPs com mais tentativas
- Top 5 Emails com mais tentativas

#### 2. Visualização de Tentativas
- Lista completa de tentativas
- Filtros por email, IP, status
- Paginação
- Visualização de motivo da falha

#### 3. Gerenciamento de Bloqueios
- Lista de bloqueios ativos
- Desbloquear manualmente
- Criar bloqueio manual
- Visualizar tempo restante de bloqueio

## 🔄 Fluxo de Proteção

### Login Normal (Sucesso)
```
1. Usuário tenta login
   ↓
2. Sistema verifica se IP está bloqueado → ❌ Não
   ↓
3. Sistema verifica se Email está bloqueado → ❌ Não
   ↓
4. Valida credenciais → ✅ Válidas
   ↓
5. Registra tentativa SUCESSO no banco
   ↓
6. Gera tokens e permite acesso
```

### Login com Falha (Proteção Ativada)
```
1. Usuário tenta login com senha errada
   ↓
2. Sistema verifica se IP está bloqueado → ❌ Não
   ↓
3. Sistema verifica se Email está bloqueado → ❌ Não
   ↓
4. Valida credenciais → ❌ Senha inválida
   ↓
5. Registra tentativa FALHA no banco (motivo: senha_invalida)
   ↓
6. Conta tentativas por Email nos últimos 15 min → 5 tentativas
   ↓
7. Conta tentativas por IP nos últimos 15 min → 3 tentativas
   ↓
8. Email atingiu limite (5) → Cria bloqueio de Email por 30 min
   ↓
9. Retorna erro: "Credenciais inválidas"
```

### Tentativa com Bloqueio Ativo
```
1. Usuário tenta login
   ↓
2. Sistema verifica se IP está bloqueado → ✅ Bloqueado até 11:45
   ↓
3. Retorna erro: "Seu IP está bloqueado até 11/11/2025 11:45:00"
   ↓
4. Login não é processado
```

## 🧪 Testes

### Teste 1: Bloqueio por Tentativas Excessivas
```bash
# Faça 6 tentativas com senha errada
for i in {1..6}; do
  curl -X POST http://localhost/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"teste@example.com","senha":"senhaerrada"}'
done

# Resultado esperado: Bloqueio na 6ª tentativa
```

### Teste 2: Verificar Bloqueio
```bash
curl http://localhost/api/login-bloqueios/verificar?email=teste@example.com
```

### Teste 3: Desbloquear Manualmente
```bash
curl -X DELETE http://localhost/api/login-bloqueios/email \
  -H "Content-Type: application/json" \
  -d '{"email":"teste@example.com"}' \
  --cookie "access_token=SEU_TOKEN"
```

## 📝 Logs e Auditoria

### Consultas Úteis

**Tentativas recentes de um email:**
```sql
SELECT * FROM login_attempts
WHERE email = 'usuario@example.com'
ORDER BY criado_em DESC
LIMIT 10;
```

**IPs mais ativos:**
```sql
SELECT ip_address, COUNT(*) as total
FROM login_attempts
WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
ORDER BY total DESC
LIMIT 10;
```

**Bloqueios ativos:**
```sql
SELECT * FROM login_bloqueios
WHERE bloqueado_permanente = 1 OR bloqueado_ate > NOW();
```

**Tentativas por motivo:**
```sql
SELECT motivo_falha, COUNT(*) as total
FROM login_attempts
WHERE tentativa_sucesso = 0
AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY motivo_falha;
```

## 🔧 Manutenção

### Limpeza Manual
```sql
-- Remover tentativas antigas (mais de 30 dias)
DELETE FROM login_attempts
WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Remover bloqueios expirados
DELETE FROM login_bloqueios
WHERE bloqueado_permanente = 0 AND bloqueado_ate < NOW();
```

### Desbloquear Tudo (Emergência)
```sql
-- CUIDADO: Remove TODOS os bloqueios
DELETE FROM login_bloqueios;
```

## 🎨 Personalização

### Alterar Número de Tentativas
Edite `.env`:
```env
BRUTE_FORCE_MAX_TENTATIVAS="3"  # Bloqueia após 3 tentativas
```

### Alterar Tempo de Bloqueio
Edite `.env`:
```env
BRUTE_FORCE_TEMPO_BLOQUEIO="60"  # 60 minutos de bloqueio
```

### Desabilitar Rastreamento por IP
Edite `.env`:
```env
BRUTE_FORCE_RASTREAR_POR_IP="false"
```

## ❓ FAQ

**P: Os dados ficam salvos para sempre?**
R: Não. O event `limpar_login_attempts_antigos` executa diariamente e remove tentativas com mais de 30 dias.

**P: Posso bloquear permanentemente um IP?**
R: Sim! Use a interface administrativa ou a API com `permanente: true`.

**P: Como desbloquear um usuário legítimo?**
R: Acesse a interface administrativa em `brute_force.html` e clique em "Desbloquear".

**P: O sistema rastreia logins bem-sucedidos?**
R: Sim! Todas as tentativas (sucesso e falha) são registradas para auditoria.

**P: Funciona com IPv6?**
R: Sim! O campo `ip_address` suporta IPv4 e IPv6.

## 📄 Licença

Este sistema foi desenvolvido como parte do projeto Ecletech.

## 👥 Suporte

Para dúvidas ou problemas, consulte a documentação completa ou entre em contato com a equipe de desenvolvimento.
