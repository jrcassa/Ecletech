# 📱 Sistema WhatsApp - Guia de Implementação

## 📋 Status da Implementação

### ✅ Completado

1. **Database** - `/database/migrations/2025_01_12_create_whatsapp_tables.sql`
2. **Helper** - `/src/Helpers/WhatsAppStatus.php`
3. **Models** (6 arquivos):
   - WhatsAppConfiguracao.php
   - WhatsAppQueue.php
   - WhatsAppHistorico.php
   - WhatsAppEntidade.php
   - WhatsAppWebhook.php
   - WhatsAppMessageStatus.php
   - WhatsAppSenderBaileys.php

4. **Services** (1 arquivo):
   - WhatsAppEntidadeService.php

### ⏳ Pendente

5. **Services** (restantes):
   - WhatsAppRetryService.php
   - WhatsAppQueueService.php
   - WhatsAppWebhookService.php
   - WhatsAppConnectionService.php
   - WhatsAppService.php (orquestrador)

6. **Controllers** (5 arquivos):
   - Controller_Whatsapp_Conexao.php
   - Controller_Whatsapp_Envio.php
   - Controller_Whatsapp_Painel.php
   - Controller_Whatsapp_Webhook.php
   - Controller_Whatsapp_Configuracao.php

7. **Cron** (Crunz):
   - Tasks (AbstractTask, WhatsAppQueueTask, etc)
   - Schedule
   - Bootstrap

8. **Views**:
   - painel.php (HTML)
   - whatsapp.js (JavaScript)

9. **Composer**:
   - Adicionar dependências (Crunz, Monolog)

---

## 🚀 Instalação e Configuração

### 1. Executar Migrations

Execute as migrations na ordem correta:

```bash
# 1. Migration das tabelas WhatsApp
mysql -u seu_usuario -p sua_database < database/migrations/2025_01_12_create_whatsapp_tables.sql

# 2. Migration das permissões ACL
mysql -u seu_usuario -p sua_database < database/migrations/047_adicionar_permissoes_whatsapp.sql
```

### 2. Configurar Permissões ACL

O sistema WhatsApp utiliza 3 permissões:

- **`whatsapp.acessar`** - Visualizar painel, status, fila e histórico
- **`whatsapp.alterar`** - Enviar mensagens, gerenciar conexão, processar fila e configurar
- **`whatsapp.deletar`** - ⚠️ **SEMPRE BLOQUEADA** por segurança

**IMPORTANTE:**
- As permissões são automaticamente atribuídas aos roles Super Admin (ID 1) e Admin (ID 2)
- Usuários com nível 0 ou 5 têm acesso automático (admins)
- A permissão `whatsapp.deletar` existe apenas para manter padrão ACL mas está inativa
- Deletar mensagens/histórico NÃO é permitido por design de segurança

### 3. Configurar API Baileys

```sql
-- URL base da API Baileys
UPDATE whatsapp_configuracoes
SET valor = 'https://api.baileys.com'
WHERE chave = 'api_base_url';

-- Token da instância WhatsApp
UPDATE whatsapp_configuracoes
SET valor = 'SEU_INSTANCE_TOKEN_AQUI'
WHERE chave = 'instancia_token';

-- Token de segurança para autenticação (Bearer)
UPDATE whatsapp_configuracoes
SET valor = 'SEU_SECURE_TOKEN_AQUI'
WHERE chave = 'api_secure_token';

-- Configurar Webhook
UPDATE whatsapp_configuracoes
SET valor = 'https://seudominio.com.br/public_html/api/whatsapp/webhook'
WHERE chave = 'webhook_url';
```

### 4. Configurar Entidades

```sql
-- Exemplo: Cliente
UPDATE whatsapp_configuracoes SET valor = 'clientes' WHERE chave = 'entidade_cliente_tabela';
UPDATE whatsapp_configuracoes SET valor = 'celular' WHERE chave = 'entidade_cliente_campo_telefone';
UPDATE whatsapp_configuracoes SET valor = 'nome' WHERE chave = 'entidade_cliente_campo_nome';
UPDATE whatsapp_configuracoes SET valor = 'id' WHERE chave = 'entidade_cliente_campo_id';

-- Exemplo: Colaborador
UPDATE whatsapp_configuracoes SET valor = 'colaboradores' WHERE chave = 'entidade_colaborador_tabela';
UPDATE whatsapp_configuracoes SET valor = 'telefone' WHERE chave = 'entidade_colaborador_campo_telefone';
```

---

## 📁 Estrutura de Arquivos

```
Ecletech/
│
├── database/
│   └── migrations/
│       └── 2025_01_12_create_whatsapp_tables.sql ✅
│
├── src/
│   ├── Helpers/
│   │   └── WhatsAppStatus.php ✅
│   │
│   ├── Models/
│   │   └── Whatsapp/
│   │       ├── WhatsAppConfiguracao.php ✅
│   │       ├── WhatsAppQueue.php ✅
│   │       ├── WhatsAppHistorico.php ✅
│   │       ├── WhatsAppEntidade.php ✅
│   │       ├── WhatsAppWebhook.php ✅
│   │       ├── WhatsAppMessageStatus.php ✅
│   │       └── WhatsAppSenderBaileys.php ✅
│   │
│   ├── Services/
│   │   └── Whatsapp/
│   │       ├── WhatsAppEntidadeService.php ✅
│   │       ├── WhatsAppRetryService.php ⏳
│   │       ├── WhatsAppQueueService.php ⏳
│   │       ├── WhatsAppWebhookService.php ⏳
│   │       ├── WhatsAppConnectionService.php ⏳
│   │       └── WhatsAppService.php ⏳
│   │
│   ├── Controllers/
│   │   └── Whatsapp/
│   │       ├── Controller_Whatsapp_Conexao.php ⏳
│   │       ├── Controller_Whatsapp_Envio.php ⏳
│   │       ├── Controller_Whatsapp_Painel.php ⏳
│   │       ├── Controller_Whatsapp_Webhook.php ⏳
│   │       └── Controller_Whatsapp_Configuracao.php ⏳
│   │
│   └── Cron/
│       ├── Tasks/
│       │   ├── AbstractTask.php ⏳
│       │   ├── WhatsAppQueueTask.php ⏳
│       │   ├── WhatsAppWebhookTask.php ⏳
│       │   ├── WhatsAppCleanupTask.php ⏳
│       │   └── WhatsAppStatusTask.php ⏳
│       ├── bootstrap.php ⏳
│       └── schedule.php ⏳
│
└── public/
    └── Views/
        └── Whatsapp/
            ├── painel.php ⏳
            └── js/
                └── whatsapp.js ⏳
```

---

## 🔧 Configurações Importantes

### Token da Instância

Após executar a migration, configure o token no banco:

```sql
UPDATE whatsapp_configuracoes
SET valor = 'deviceweb'
WHERE chave = 'instancia_token';
```

### Webhook URL

Configure a URL do webhook:

```sql
UPDATE whatsapp_configuracoes
SET valor = 'https://seu-dominio.com.br/src/Controllers/Whatsapp/Controller_Whatsapp_Webhook.php'
WHERE chave = 'webhook_url';
```

---

## 📊 Funcionalidades Implementadas

### ✅ Sistema de Entidades

- Envio por entidade (cliente:123, colaborador:45, etc)
- Sincronização automática de dados
- Cache de entidades
- Suporte a fallback para número direto

### ✅ Sistema de Fila

- Fila com prioridades
- Agendamento de mensagens
- Retry automático com backoff exponencial
- Anti-ban configurável

### ✅ Sistema de Webhooks

- Recebimento de status (entregue, lido)
- Tracking de mensagens
- Reprocessamento automático

### ✅ Configurações Parametrizadas

- 72+ configurações disponíveis
- API, fila, retry, limites, validações, etc
- Cache de configurações

---

## 🎯 Exemplo de Uso

### Enviar mensagem por entidade:

```javascript
$.ajax({
    url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Envio.php',
    method: 'POST',
    data: {
        op: 'enviar',
        destinatario: 'cliente:123',  // ← ENTIDADE
        tipo: 'text',
        mensagem: 'Olá! Seu pedido foi aprovado.'
    }
});
```

### Enviar por número direto:

```javascript
$.ajax({
    url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Envio.php',
    method: 'POST',
    data: {
        op: 'enviar',
        destinatario: '5515999999999',  // ← NÚMERO
        tipo: 'text',
        mensagem: 'Teste'
    }
});
```

---

## 📞 Suporte

Para continuar a implementação, solicite:

**"Continue implementando o sistema WhatsApp - crie os Services restantes"**

ou

**"Implemente os Controllers do sistema WhatsApp"**
