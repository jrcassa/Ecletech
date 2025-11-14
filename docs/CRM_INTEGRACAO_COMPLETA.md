# 📘 DOCUMENTAÇÃO COMPLETA - INTEGRAÇÃO CRM EXTERNA

**Projeto:** Ecletech CRM
**Módulo:** Integração com CRMs Externos
**Versão:** 1.0.0
**Data:** Janeiro 2025
**Autor:** Equipe Ecletech

---

## 📑 ÍNDICE

1. [VISÃO GERAL](#1-visão-geral)
2. [ARQUITETURA](#2-arquitetura)
3. [ESTRUTURA DE DIRETÓRIOS](#3-estrutura-de-diretórios)
4. [BANCO DE DADOS](#4-banco-de-dados)
5. [PROVIDERS (CRM)](#5-providers-crm)
6. [ENTIDADES](#6-entidades)
7. [SISTEMA DE MAPEAMENTO](#7-sistema-de-mapeamento)
8. [SISTEMA DE TRANSFORMAÇÃO](#8-sistema-de-transformação)
9. [FLUXOS COMPLETOS](#9-fluxos-completos)
10. [CONFIGURAÇÃO](#10-configuração)
11. [SINCRONIZAÇÃO](#11-sincronização)
12. [AUDITORIA](#12-auditoria)
13. [PAINEL ADMINISTRATIVO](#13-painel-administrativo)
14. [PERMISSÕES (ACL)](#14-permissões-acl)
15. [API COMPLETA](#15-api-completa)
16. [GUIAS PRÁTICOS](#16-guias-práticos)
17. [TROUBLESHOOTING](#17-troubleshooting)
18. [REFERÊNCIAS](#18-referências)

---

## 1. VISÃO GERAL

### 1.1 Conceito

O módulo de Integração CRM permite que o sistema Ecletech se conecte com CRMs externos (como GestaoClick, Pipedrive, Bling, HubSpot, etc.) para sincronizar dados de forma bidirecional.

**Principais Características:**
- ✅ 100% Modular e Plugável
- ✅ Adicionar novos CRMs sem alterar código core
- ✅ Sincronização automática e manual
- ✅ Auditoria completa de todas as operações
- ✅ Mapeamento customizável de campos
- ✅ Suporte a entidades read-only
- ✅ Sistema de permissões granular (ACL)
- ✅ Painel administrativo completo

### 1.2 Objetivos

1. **Integração Transparente**: O sistema funciona normalmente com ou sem CRM externo ativo
2. **Flexibilidade Total**: Cada CRM tem suas próprias regras e configurações
3. **Rastreabilidade**: Logs completos de todas as operações
4. **Facilidade de Expansão**: Adicionar novo CRM = criar nova pasta
5. **Segurança**: Credenciais criptografadas, ACL por funcionalidade

### 1.3 Fluxo Básico

```
┌─────────────────────────────────────────────────────┐
│ Cliente Configura CRM no Painel                     │
│ ↓                                                   │
│ Sistema Valida Credenciais                          │
│ ↓                                                   │
│ Ativa Integração                                    │
│ ↓                                                   │
│ A partir de agora:                                  │
│ - Criar/Atualizar Cliente → Envia para CRM Externo │
│ - Sincronização Automática (CRON)                  │
│ - Logs e Auditoria Completos                       │
└─────────────────────────────────────────────────────┘
```

### 1.4 Decisões de Design

**1. Provider Pattern**
- Cada CRM é um provider independente
- Interface comum para todos
- Facilita adicionar novos CRMs

**2. Entity Handler**
- Cada entidade tem seu próprio handler
- Isola lógica de acesso aos Models/Services do Ecletech
- Zero acoplamento no código core

**3. Auto-Discovery**
- Providers descobertos automaticamente
- Entidades descobertas automaticamente
- Não precisa registrar manualmente

**4. External ID**
- Campo `external_id` em todas as entidades sincronizáveis
- Liga registro local com registro do CRM externo
- Permite sincronização bidirecional

**5. Auditoria Completa**
- Snapshot ANTES e DEPOIS de cada operação
- Request/Response do CRM externo
- Diff calculado automaticamente
- Rastreabilidade total (quem, quando, de onde)

---

## 2. ARQUITETURA

### 2.1 Visão Macro

```
┌──────────────────────────────────────────────────────────────┐
│                    ECLETECH CRM                               │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              CAMADA DE APLICAÇÃO                        │ │
│  │  (Controllers, Services, Models)                        │ │
│  └────────────────┬───────────────────────────────────────┘ │
│                   ↓                                          │
│  ┌────────────────────────────────────────────────────────┐ │
│  │           MÓDULO CRM (App/CRM/)                         │ │
│  │                                                         │ │
│  │  ┌──────────────────────────────────────────────────┐  │ │
│  │  │  CORE (Infraestrutura)                           │  │ │
│  │  │  - CrmManager                                     │  │ │
│  │  │  - CrmRegistry (Auto-discovery)                   │  │ │
│  │  │  - EntityRegistry (Auto-discovery)                │  │ │
│  │  │  - FieldMapper                                    │  │ │
│  │  │  - RequestBuilder / ResponseParser                │  │ │
│  │  └──────────────────────────────────────────────────┘  │ │
│  │                                                         │ │
│  │  ┌──────────────────────────────────────────────────┐  │ │
│  │  │  PROVIDERS (Plugável)                             │  │ │
│  │  │  - GestaoClick/                                   │  │ │
│  │  │  - Pipedrive/                                     │  │ │
│  │  │  - Bling/                                         │  │ │
│  │  │  - ... (adicionar mais)                           │  │ │
│  │  └──────────────────────────────────────────────────┘  │ │
│  │                                                         │ │
│  │  ┌──────────────────────────────────────────────────┐  │ │
│  │  │  SINCRONIZAÇÃO                                    │  │ │
│  │  │  - SyncEngine (paginado)                          │  │ │
│  │  │  - SyncIndividual                                 │  │ │
│  │  │  - CronManager                                    │  │ │
│  │  └──────────────────────────────────────────────────┘  │ │
│  │                                                         │ │
│  │  ┌──────────────────────────────────────────────────┐  │ │
│  │  │  AUDITORIA                                        │  │ │
│  │  │  - AuditLogger                                    │  │ │
│  │  │  - AuditDiff                                      │  │ │
│  │  └──────────────────────────────────────────────────┘  │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                              │
└──────────────────────────────────────────────────────────────┘
                              ↕
                      HTTP/HTTPS/REST
                              ↕
┌──────────────────────────────────────────────────────────────┐
│                    CRMs EXTERNOS                              │
│  - GestaoClick                                               │
│  - Pipedrive                                                 │
│  - Bling                                                     │
│  - RD Station                                                │
│  - HubSpot                                                   │
│  - Salesforce                                                │
│  - ... outros                                                │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 Camadas

**Camada 1: Core (Infraestrutura)**
- Componentes reutilizáveis
- Interfaces e contratos
- Lógica genérica de sincronização
- Sistema de mapeamento
- Sistema de transformação

**Camada 2: Providers (Plugável)**
- Cada CRM = Um provider
- Implementa interface comum
- Configuração em JSON
- Handlers específicos por entidade

**Camada 3: Sincronização**
- Motor genérico (não conhece entidades)
- Usa handlers para acessar dados
- Paginação automática
- Retry com backoff

**Camada 4: Auditoria**
- Registra todas as operações
- Snapshot antes/depois
- Request/Response do CRM
- Diff automático

### 2.3 Fluxo de Dados

```
REQUEST (Frontend)
    ↓
Controller (valida JWT, ACL)
    ↓
Service (lógica de negócio)
    ↓
┌───────────────────────────────┐
│ Verifica Integração CRM Ativa │
└───────────┬───────────────────┘
            ↓
       [TEM CRM?]
            ├─ NÃO → Fluxo Normal (Model → DB)
            │
            └─ SIM → Fluxo com CRM
                     ↓
                1. RequestBuilder (transforma dados)
                     ↓
                2. HTTP Client (envia para CRM)
                     ↓
                3. ResponseParser (parseia resposta)
                     ↓
                4. Salva no DB com external_id
                     ↓
                5. AuditLogger (registra tudo)
```

---

## 3. ESTRUTURA DE DIRETÓRIOS

### 3.1 Estrutura Completa

```
App/
└── CRM/
    │
    ├── Core/                                    # Infraestrutura
    │   ├── Interfaces/
    │   │   ├── CrmProviderInterface.php
    │   │   ├── EntityHandlerInterface.php
    │   │   └── TransformerInterface.php
    │   │
    │   ├── Abstract/
    │   │   ├── AbstractProvider.php
    │   │   ├── AbstractEntityHandler.php
    │   │   └── AbstractTransformer.php
    │   │
    │   ├── CrmManager.php
    │   ├── CrmRegistry.php
    │   ├── EntityRegistry.php
    │   ├── FieldMapper.php
    │   │
    │   ├── Request/
    │   │   ├── RequestBuilder.php
    │   │   ├── RequestTransformer.php
    │   │   └── RequestValidator.php
    │   │
    │   ├── Response/
    │   │   ├── ResponseParser.php
    │   │   ├── ResponseTransformer.php
    │   │   └── ResponseExtractor.php
    │   │
    │   └── Exceptions/
    │       ├── CrmException.php
    │       ├── UnsupportedOperationException.php
    │       ├── TransformationException.php
    │       └── AuditException.php
    │
    │
    ├── Providers/                               # Providers (Plugável)
    │   │
    │   ├── None/                                # Sem integração
    │   │   ├── NoneProvider.php
    │   │   └── config.json
    │   │
    │   ├── GestaoClick/                         # Provider GestaoClick
    │   │   │
    │   │   ├── GestaoClickProvider.php
    │   │   │
    │   │   ├── config.json
    │   │   │
    │   │   ├── entities/
    │   │   │   │
    │   │   │   ├── cliente/
    │   │   │   │   ├── config.json
    │   │   │   │   ├── endpoints.json
    │   │   │   │   ├── mapping.json
    │   │   │   │   └── ClienteHandler.php
    │   │   │   │
    │   │   │   ├── venda/
    │   │   │   │   ├── config.json
    │   │   │   │   ├── endpoints.json
    │   │   │   │   ├── mapping.json
    │   │   │   │   └── VendaHandler.php
    │   │   │   │
    │   │   │   ├── produto/
    │   │   │   │   ├── config.json
    │   │   │   │   ├── endpoints.json
    │   │   │   │   ├── mapping.json
    │   │   │   │   └── ProdutoHandler.php
    │   │   │   │
    │   │   │   └── atividade/
    │   │   │       ├── config.json
    │   │   │       ├── endpoints.json
    │   │   │       ├── mapping.json
    │   │   │       └── AtividadeHandler.php
    │   │   │
    │   │   ├── Services/
    │   │   │   ├── HttpClient.php
    │   │   │   └── AuthService.php
    │   │   │
    │   │   ├── Transformers/
    │   │   │   ├── DateTransformer.php
    │   │   │   ├── MoneyTransformer.php
    │   │   │   ├── PhoneTransformer.php
    │   │   │   └── DocumentTransformer.php
    │   │   │
    │   │   └── README.md
    │   │
    │   ├── Pipedrive/                           # Provider Pipedrive
    │   │   └── (mesma estrutura)
    │   │
    │   └── Bling/                               # Provider Bling
    │       └── (mesma estrutura)
    │
    │
    ├── Sync/                                    # Sincronização
    │   ├── SyncEngine.php
    │   ├── SyncOrchestrator.php
    │   ├── SyncIndividual.php
    │   ├── SyncPagination.php
    │   └── SyncLogger.php
    │
    │
    ├── Cron/                                    # Agendamentos
    │   ├── CronManager.php
    │   ├── CronExecutor.php
    │   ├── CronExpression.php
    │   └── Jobs/
    │       ├── SyncClientesJob.php
    │       ├── SyncVendasJob.php
    │       ├── SyncProdutosJob.php
    │       └── SyncFullJob.php
    │
    │
    ├── Audit/                                   # Auditoria
    │   ├── AuditLogger.php
    │   ├── AuditDiff.php
    │   ├── AuditFormatter.php
    │   └── AuditQuery.php
    │
    │
    ├── Models/                                  # Models do CRM
    │   ├── ModelCrmIntegracao.php
    │   ├── ModelCrmLog.php
    │   ├── ModelCrmAuditoria.php
    │   ├── ModelCrmAgendamento.php
    │   ├── ModelCrmSyncHistorico.php
    │   └── ModelCrmFila.php
    │
    │
    ├── Services/                                # Services do CRM
    │   ├── ServiceCrmManager.php
    │   ├── ServiceCrmSync.php
    │   ├── ServiceCrmAgendamento.php
    │   └── ServiceCrmAuditoria.php
    │
    │
    ├── Controllers/                             # Controllers (API)
    │   ├── ControllerCrmDashboard.php
    │   ├── ControllerCrmConfig.php
    │   ├── ControllerCrmSync.php
    │   ├── ControllerCrmAgendamento.php
    │   ├── ControllerCrmAuditoria.php
    │   ├── ControllerCrmMapeamento.php
    │   └── ControllerCrmEstatisticas.php
    │
    │
    ├── Middleware/
    │   └── MiddlewareCrmAcl.php
    │
    │
    └── Routes/
        └── crm.php

App/Routes/
    └── crm.php                                  # Rotas do módulo

database/migrations/
    ├── 080_criar_tabela_crm_integracoes.sql
    ├── 081_criar_tabela_crm_logs.sql
    ├── 082_criar_tabela_crm_auditoria.sql
    ├── 083_criar_tabela_crm_agendamentos.sql
    ├── 084_criar_tabela_crm_sync_historico.sql
    └── 085_criar_tabela_crm_fila.sql

docs/
    └── CRM_INTEGRACAO_COMPLETA.md               # Esta documentação
```

### 3.2 Responsabilidades

#### Core/
**Responsabilidade:** Infraestrutura comum a todos os providers

- **Interfaces:** Contratos que todos os providers devem implementar
- **Abstract:** Classes base com lógica comum
- **CrmManager:** Orquestra operações de CRM
- **CrmRegistry:** Auto-discovery de providers
- **EntityRegistry:** Auto-discovery de entidades
- **FieldMapper:** Sistema de mapeamento de campos
- **Request/Response:** Construção e parsing de requisições HTTP

#### Providers/
**Responsabilidade:** Implementações específicas de cada CRM

- Cada pasta = um CRM diferente
- Totalmente isolado
- Adicionar novo = criar nova pasta
- Não afeta outros providers

#### Sync/
**Responsabilidade:** Lógica de sincronização

- **SyncEngine:** Motor genérico de sincronização
- **SyncIndividual:** Sincronizar um registro específico
- **SyncPagination:** Gerencia paginação de APIs externas

#### Cron/
**Responsabilidade:** Agendamentos automáticos

- **CronManager:** Gerencia jobs agendados
- **CronExecutor:** Executa jobs
- **Jobs:** Tarefas específicas

#### Audit/
**Responsabilidade:** Auditoria e rastreabilidade

- **AuditLogger:** Registra todas as operações
- **AuditDiff:** Calcula diferenças entre estados
- **AuditQuery:** Busca e filtra logs

#### Models/Services/Controllers/
**Responsabilidade:** Camada de aplicação do módulo CRM

- Segue padrão MVC+S do Ecletech
- Usa infraestrutura do Core
- Não conhece detalhes de providers

---

## 4. BANCO DE DADOS

### 4.1 Visão Geral

O módulo CRM utiliza **6 tabelas principais** para armazenar configurações, logs, auditoria e histórico de sincronizações:

1. **crm_integracoes** - Configuração do CRM ativo por loja
2. **crm_auditoria** - Auditoria completa de todas as operações
3. **crm_agendamentos** - Configuração de CRON para sincronizações
4. **crm_sync_historico** - Histórico de execução de sincronizações
5. **crm_logs** - Logs detalhados de operações
6. **crm_fila** - Fila de processamento assíncrono

### 4.2 Tabela: crm_integracoes

**Propósito:** Armazena qual CRM está ativo para cada loja

```sql
CREATE TABLE crm_integracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_loja INT NOT NULL,

    -- Identificação do Provider
    provider_slug VARCHAR(50) NOT NULL,              -- Ex: 'gestao_click', 'pipedrive', 'none'
    provider_nome VARCHAR(100) NOT NULL,             -- Ex: 'GestaoClick CRM'
    provider_versao VARCHAR(20) DEFAULT '1.0.0',

    -- Status
    ativo TINYINT(1) DEFAULT 1,

    -- Credenciais (criptografadas)
    credenciais TEXT NOT NULL,                        -- JSON criptografado

    -- Configurações
    configuracoes JSON,                               -- Configurações específicas do provider

    -- Entidades habilitadas
    entidades_habilitadas JSON,                       -- ['cliente', 'venda', 'produto', ...]

    -- Metadados
    ultima_sincronizacao DATETIME NULL,
    ultima_validacao DATETIME NULL,
    erro_ultima_validacao TEXT NULL,

    -- Auditoria
    criado_por INT,
    atualizado_por INT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_loja (id_loja),
    INDEX idx_provider (provider_slug),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Exemplo de Dados:**

```json
{
    "id": 1,
    "id_loja": 10,
    "provider_slug": "gestao_click",
    "provider_nome": "GestaoClick CRM",
    "provider_versao": "1.0.0",
    "ativo": 1,
    "credenciais": "ENCRYPTED_DATA_HERE",
    "configuracoes": {
        "timeout": 30,
        "retry_attempts": 3,
        "sync_batch_size": 100
    },
    "entidades_habilitadas": ["cliente", "venda", "produto", "atividade"],
    "ultima_sincronizacao": "2025-01-14 10:30:00",
    "criado_em": "2025-01-01 09:00:00"
}
```

---

### 4.3 Tabela: crm_auditoria

**Propósito:** Auditoria completa de todas as operações com snapshot antes/depois

```sql
CREATE TABLE crm_auditoria (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    id_integracao INT NOT NULL,
    id_loja INT NOT NULL,

    -- Tipo de Operação
    operacao ENUM('create', 'update', 'delete', 'sync_paginated', 'sync_individual') NOT NULL,
    entidade VARCHAR(50) NOT NULL,                    -- 'cliente', 'venda', 'produto', etc.

    -- IDs relacionados
    id_registro_local INT,                            -- ID do registro no Ecletech
    id_registro_externo VARCHAR(100),                 -- ID do registro no CRM externo

    -- Snapshots
    dados_antes JSON,                                 -- Estado ANTES da operação
    dados_depois JSON,                                -- Estado DEPOIS da operação
    diferencas JSON,                                  -- Diff calculado automaticamente

    -- Request/Response do CRM Externo
    request_enviado JSON,                             -- Request completo enviado ao CRM
    response_recebido JSON,                           -- Response completo do CRM
    http_status INT,                                  -- Status HTTP (200, 201, 400, etc.)

    -- Metadados
    sucesso TINYINT(1) DEFAULT 1,
    erro TEXT NULL,
    duracao_ms INT,                                   -- Tempo de execução em milissegundos

    -- Rastreabilidade
    usuario_id INT NULL,                              -- Quem executou (null = CRON)
    usuario_ip VARCHAR(45) NULL,
    usuario_user_agent TEXT NULL,
    origem ENUM('manual', 'automatico', 'api') DEFAULT 'manual',

    -- Timestamps
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    FOREIGN KEY (id_integracao) REFERENCES crm_integracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    INDEX idx_entidade (entidade),
    INDEX idx_operacao (operacao),
    INDEX idx_registro_local (id_registro_local),
    INDEX idx_registro_externo (id_registro_externo),
    INDEX idx_sucesso (sucesso),
    INDEX idx_criado_em (criado_em),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Exemplo de Dados:**

```json
{
    "id": 1523,
    "id_integracao": 1,
    "id_loja": 10,
    "operacao": "update",
    "entidade": "cliente",
    "id_registro_local": 450,
    "id_registro_externo": "gc_12345",
    "dados_antes": {
        "id": 450,
        "nome": "João Silva",
        "telefone": "11999998888",
        "email": "joao@email.com"
    },
    "dados_depois": {
        "id": 450,
        "nome": "João Silva Santos",
        "telefone": "11999998888",
        "email": "joao@email.com"
    },
    "diferencas": {
        "nome": {
            "de": "João Silva",
            "para": "João Silva Santos"
        }
    },
    "request_enviado": {
        "url": "https://api.gestaoclick.com/v1/customers/gc_12345",
        "method": "PUT",
        "body": {
            "name": "João Silva Santos",
            "phone": "(11) 99999-8888",
            "email": "joao@email.com"
        }
    },
    "response_recebido": {
        "id": "gc_12345",
        "name": "João Silva Santos",
        "updated_at": "2025-01-14T10:30:00Z"
    },
    "http_status": 200,
    "sucesso": 1,
    "duracao_ms": 234,
    "usuario_id": 5,
    "usuario_ip": "192.168.1.100",
    "origem": "manual",
    "criado_em": "2025-01-14 10:30:00"
}
```

---

### 4.4 Tabela: crm_agendamentos

**Propósito:** Configuração de CRON para sincronizações automáticas

```sql
CREATE TABLE crm_agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    id_integracao INT NOT NULL,
    id_loja INT NOT NULL,

    -- Configuração
    nome VARCHAR(100) NOT NULL,                       -- Ex: "Sincronizar Clientes Diário"
    descricao TEXT NULL,

    -- Entidade e Operação
    entidade VARCHAR(50) NOT NULL,                    -- 'cliente', 'venda', 'produto', etc.
    tipo_sync ENUM('paginated', 'individual', 'full') DEFAULT 'paginated',

    -- CRON Expression
    cron_expression VARCHAR(100) NOT NULL,            -- Ex: "*/5 * * * *" (a cada 5 min)

    -- Filtros (opcional)
    filtros JSON NULL,                                -- Filtros adicionais para a sincronização

    -- Status
    ativo TINYINT(1) DEFAULT 1,

    -- Metadados
    ultima_execucao DATETIME NULL,
    proxima_execucao DATETIME NULL,
    total_execucoes INT DEFAULT 0,
    total_sucessos INT DEFAULT 0,
    total_falhas INT DEFAULT 0,

    -- Auditoria
    criado_por INT,
    atualizado_por INT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    FOREIGN KEY (id_integracao) REFERENCES crm_integracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    INDEX idx_entidade (entidade),
    INDEX idx_ativo (ativo),
    INDEX idx_proxima_execucao (proxima_execucao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Exemplos de CRON Expressions:**

```
*/5 * * * *          → A cada 5 minutos
0 */2 * * *          → A cada 2 horas
0 12 * * *           → Todo dia às 12:00
0 0 * * 0            → Todo domingo à meia-noite
0 9,18 * * 1-5       → Segunda a sexta às 9h e 18h
```

**Exemplo de Dados:**

```json
{
    "id": 1,
    "id_integracao": 1,
    "id_loja": 10,
    "nome": "Sincronizar Clientes a cada 5 minutos",
    "descricao": "Importa novos clientes do GestaoClick",
    "entidade": "cliente",
    "tipo_sync": "paginated",
    "cron_expression": "*/5 * * * *",
    "filtros": {
        "status": "active",
        "created_after": "2025-01-01"
    },
    "ativo": 1,
    "ultima_execucao": "2025-01-14 10:25:00",
    "proxima_execucao": "2025-01-14 10:30:00",
    "total_execucoes": 2880,
    "total_sucessos": 2875,
    "total_falhas": 5
}
```

---

### 4.5 Tabela: crm_sync_historico

**Propósito:** Histórico de execução de sincronizações

```sql
CREATE TABLE crm_sync_historico (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    id_integracao INT NOT NULL,
    id_loja INT NOT NULL,
    id_agendamento INT NULL,                          -- NULL se manual

    -- Tipo
    entidade VARCHAR(50) NOT NULL,
    tipo_sync ENUM('paginated', 'individual', 'full') NOT NULL,
    origem ENUM('manual', 'automatico') DEFAULT 'automatico',

    -- Estatísticas
    total_registros INT DEFAULT 0,                    -- Total de registros processados
    total_criados INT DEFAULT 0,
    total_atualizados INT DEFAULT 0,
    total_erros INT DEFAULT 0,
    total_ignorados INT DEFAULT 0,

    -- Paginação (se aplicável)
    pagina_inicial INT DEFAULT 1,
    pagina_final INT DEFAULT 1,
    registros_por_pagina INT DEFAULT 100,

    -- Execução
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    duracao_segundos INT NULL,

    -- Status
    status ENUM('em_andamento', 'concluido', 'erro', 'cancelado') DEFAULT 'em_andamento',
    erro TEXT NULL,

    -- Metadados
    usuario_id INT NULL,
    detalhes JSON,                                    -- Informações adicionais

    -- Constraints
    FOREIGN KEY (id_integracao) REFERENCES crm_integracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    FOREIGN KEY (id_agendamento) REFERENCES crm_agendamentos(id) ON DELETE SET NULL,
    INDEX idx_entidade (entidade),
    INDEX idx_status (status),
    INDEX idx_inicio (inicio),
    INDEX idx_origem (origem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Exemplo de Dados:**

```json
{
    "id": 5421,
    "id_integracao": 1,
    "id_loja": 10,
    "id_agendamento": 1,
    "entidade": "cliente",
    "tipo_sync": "paginated",
    "origem": "automatico",
    "total_registros": 350,
    "total_criados": 12,
    "total_atualizados": 338,
    "total_erros": 0,
    "total_ignorados": 0,
    "pagina_inicial": 1,
    "pagina_final": 4,
    "registros_por_pagina": 100,
    "inicio": "2025-01-14 10:25:00",
    "fim": "2025-01-14 10:26:15",
    "duracao_segundos": 75,
    "status": "concluido",
    "detalhes": {
        "api_version": "v1",
        "total_requests": 4,
        "avg_response_time": 187
    }
}
```

---

### 4.6 Tabela: crm_logs

**Propósito:** Logs detalhados de operações (debug, info, warning, error)

```sql
CREATE TABLE crm_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    id_integracao INT NULL,
    id_loja INT NULL,

    -- Nível
    nivel ENUM('debug', 'info', 'warning', 'error') NOT NULL,

    -- Contexto
    contexto VARCHAR(100) NOT NULL,                   -- Ex: 'sync', 'config', 'api', 'cron'
    entidade VARCHAR(50) NULL,

    -- Mensagem
    mensagem TEXT NOT NULL,
    detalhes JSON NULL,

    -- Stack Trace (se erro)
    stack_trace TEXT NULL,

    -- Rastreabilidade
    usuario_id INT NULL,
    request_id VARCHAR(36) NULL,                      -- UUID da requisição

    -- Timestamp
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    INDEX idx_nivel (nivel),
    INDEX idx_contexto (contexto),
    INDEX idx_entidade (entidade),
    INDEX idx_criado_em (criado_em),
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Exemplo de Dados:**

```json
{
    "id": 98765,
    "id_integracao": 1,
    "id_loja": 10,
    "nivel": "error",
    "contexto": "sync",
    "entidade": "venda",
    "mensagem": "Falha ao sincronizar venda - Timeout na API",
    "detalhes": {
        "id_venda": 1234,
        "external_id": "gc_98765",
        "tentativa": 3,
        "timeout_seconds": 30
    },
    "stack_trace": "Exception in SyncEngine...",
    "usuario_id": null,
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "criado_em": "2025-01-14 10:25:30"
}
```

---

### 4.7 Tabela: crm_fila

**Propósito:** Fila de processamento assíncrono para operações pesadas

```sql
CREATE TABLE crm_fila (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação
    id_integracao INT NOT NULL,
    id_loja INT NOT NULL,

    -- Job
    tipo_job ENUM('sync_paginated', 'sync_individual', 'export', 'import') NOT NULL,
    entidade VARCHAR(50) NOT NULL,

    -- Payload
    payload JSON NOT NULL,                            -- Dados do job

    -- Prioridade
    prioridade TINYINT DEFAULT 5,                     -- 1=highest, 10=lowest

    -- Status
    status ENUM('pendente', 'processando', 'concluido', 'erro') DEFAULT 'pendente',

    -- Execução
    tentativas INT DEFAULT 0,
    max_tentativas INT DEFAULT 3,
    erro TEXT NULL,

    -- Timestamps
    agendado_para DATETIME DEFAULT CURRENT_TIMESTAMP,
    iniciado_em DATETIME NULL,
    finalizado_em DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    FOREIGN KEY (id_integracao) REFERENCES crm_integracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_prioridade (prioridade),
    INDEX idx_agendado_para (agendado_para),
    INDEX idx_tipo_job (tipo_job)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 4.8 Alterações em Tabelas Existentes

Para suportar sincronização com CRM externo, adicionar campo `external_id` em todas as entidades sincronizáveis:

```sql
-- Tabela: clientes
ALTER TABLE clientes
ADD COLUMN external_id VARCHAR(100) NULL,
ADD UNIQUE KEY unique_external_id (id_loja, external_id);

-- Tabela: vendas
ALTER TABLE vendas
ADD COLUMN external_id VARCHAR(100) NULL,
ADD UNIQUE KEY unique_external_id (id_loja, external_id);

-- Tabela: produtos
ALTER TABLE produtos
ADD COLUMN external_id VARCHAR(100) NULL,
ADD UNIQUE KEY unique_external_id (id_loja, external_id);

-- Tabela: atividades
ALTER TABLE atividades
ADD COLUMN external_id VARCHAR(100) NULL,
ADD UNIQUE KEY unique_external_id (id_loja, external_id);
```

**Importante:**
- `external_id` é **nullable** (pode ser NULL se não sincronizado)
- Unique constraint é composto: `(id_loja, external_id)` para permitir diferentes lojas com mesmo external_id
- Usado para vincular registro local com registro do CRM externo

---

### 4.9 Diagrama de Relacionamentos

```
┌─────────────────────┐
│      lojas          │
└──────────┬──────────┘
           │
           │ 1:1
           ↓
┌──────────────────────┐
│  crm_integracoes     │◄──────────┐
│  - provider_slug     │           │
│  - credenciais       │           │
│  - entidades_hab.    │           │
└──────────┬───────────┘           │
           │                       │
           │ 1:N                   │
           ↓                       │
┌──────────────────────┐           │
│  crm_agendamentos    │           │
│  - cron_expression   │           │
│  - entidade          │           │
└──────────┬───────────┘           │
           │                       │
           │ 1:N                   │
           ↓                       │
┌──────────────────────┐           │
│  crm_sync_historico  │───────────┘
│  - total_registros   │
│  - status            │
└──────────────────────┘

┌──────────────────────┐
│  crm_auditoria       │
│  - dados_antes       │
│  - dados_depois      │
│  - diferencas        │
│  - request/response  │
└──────────────────────┘

┌──────────────────────┐
│  crm_logs            │
│  - nivel             │
│  - mensagem          │
│  - stack_trace       │
└──────────────────────┘

┌──────────────────────┐
│  crm_fila            │
│  - tipo_job          │
│  - payload           │
│  - status            │
└──────────────────────┘

┌──────────────────────┐      ┌──────────────────────┐
│  clientes            │      │  vendas              │
│  + external_id       │      │  + external_id       │
└──────────────────────┘      └──────────────────────┘

┌──────────────────────┐      ┌──────────────────────┐
│  produtos            │      │  atividades          │
│  + external_id       │      │  + external_id       │
└──────────────────────┘      └──────────────────────┘
```

---

### 4.10 Índices Recomendados

Para garantir performance em consultas frequentes:

```sql
-- Índices Compostos para queries comuns
CREATE INDEX idx_auditoria_lookup ON crm_auditoria(id_loja, entidade, id_registro_local, criado_em);
CREATE INDEX idx_logs_debug ON crm_logs(id_loja, nivel, contexto, criado_em);
CREATE INDEX idx_sync_stats ON crm_sync_historico(id_integracao, entidade, status, inicio);
CREATE INDEX idx_fila_processamento ON crm_fila(status, prioridade, agendado_para);
```

---

## 5. PROVIDERS (CRM)

### 5.1 Conceito de Provider

Um **Provider** é uma implementação completa e isolada de integração com um CRM específico (GestaoClick, Pipedrive, Bling, etc.).

**Características:**
- ✅ Totalmente autocontido (pasta única)
- ✅ Implementa interface comum (`CrmProviderInterface`)
- ✅ Descoberto automaticamente pelo sistema
- ✅ Zero dependência com outros providers
- ✅ Configuração em arquivos JSON

### 5.2 Interface: CrmProviderInterface

Todos os providers **DEVEM** implementar esta interface:

```php
interface CrmProviderInterface
{
    /**
     * Retorna slug único do provider
     * Ex: 'gestao_click', 'pipedrive', 'bling'
     */
    public function getSlug(): string;

    /**
     * Retorna nome amigável do provider
     * Ex: 'GestaoClick CRM', 'Pipedrive', 'Bling ERP'
     */
    public function getName(): string;

    /**
     * Retorna versão do provider
     * Ex: '1.0.0', '2.1.3'
     */
    public function getVersion(): string;

    /**
     * Retorna configuração completa do provider
     * (lê de config.json)
     */
    public function getConfig(): array;

    /**
     * Retorna lista de entidades suportadas
     * Ex: ['cliente', 'venda', 'produto', 'atividade']
     */
    public function getSupportedEntities(): array;

    /**
     * Retorna handler para uma entidade específica
     * @param string $entity Ex: 'cliente', 'venda'
     * @return EntityHandlerInterface
     */
    public function getEntityHandler(string $entity): EntityHandlerInterface;

    /**
     * Valida credenciais fazendo teste de conexão
     * @param array $credentials
     * @return bool|array true se válido, array com erro se inválido
     */
    public function validateCredentials(array $credentials): bool|array;

    /**
     * Testa conexão com CRM externo
     * @return bool|array
     */
    public function testConnection(): bool|array;

    /**
     * Retorna informações sobre rate limits da API
     * @return array ['requests_per_minute' => 60, 'burst' => 10]
     */
    public function getRateLimits(): array;
}
```

---

### 5.3 Estrutura de um Provider

Cada provider segue esta estrutura:

```
Providers/GestaoClick/
│
├── GestaoClickProvider.php          # Implementação do CrmProviderInterface
│
├── config.json                       # Configuração geral do provider
│
├── entities/                         # Entidades suportadas
│   ├── cliente/
│   │   ├── config.json               # Configuração da entidade
│   │   ├── endpoints.json            # Endpoints do CRM
│   │   ├── mapping.json              # Mapeamento de campos
│   │   └── ClienteHandler.php        # Handler da entidade
│   │
│   ├── venda/
│   │   ├── config.json
│   │   ├── endpoints.json
│   │   ├── mapping.json
│   │   └── VendaHandler.php
│   │
│   └── produto/
│       ├── config.json
│       ├── endpoints.json
│       ├── mapping.json
│       └── ProdutoHandler.php
│
├── Services/                         # Serviços auxiliares
│   ├── HttpClient.php                # Cliente HTTP customizado
│   └── AuthService.php               # Autenticação
│
├── Transformers/                     # Transformadores de dados
│   ├── DateTransformer.php           # Datas
│   ├── MoneyTransformer.php          # Valores monetários
│   ├── PhoneTransformer.php          # Telefones
│   └── DocumentTransformer.php       # Documentos (CPF/CNPJ)
│
└── README.md                         # Documentação do provider
```

---

### 5.4 Arquivo: config.json (Provider)

Configuração geral do provider:

```json
{
  "provider": {
    "slug": "gestao_click",
    "nome": "GestaoClick CRM",
    "versao": "1.0.0",
    "descricao": "Integração com GestaoClick CRM - Sistema completo de gestão",
    "website": "https://gestaoclick.com"
  },

  "api": {
    "base_url": "https://api.gestaoclick.com/v1",
    "timeout": 30,
    "retry_attempts": 3,
    "retry_delay": 2
  },

  "auth": {
    "type": "api_key",
    "credentials": {
      "api_key": {
        "label": "Chave de API",
        "placeholder": "Digite sua API Key",
        "required": true,
        "type": "password"
      },
      "api_secret": {
        "label": "Secret",
        "placeholder": "Digite seu API Secret",
        "required": false,
        "type": "password"
      }
    }
  },

  "rate_limits": {
    "requests_per_minute": 60,
    "burst": 10
  },

  "features": {
    "webhooks": true,
    "batch_operations": true,
    "custom_fields": true,
    "file_upload": false
  },

  "entidades_suportadas": [
    "cliente",
    "venda",
    "produto",
    "atividade"
  ]
}
```

**Campos importantes:**

- **slug**: Identificador único (usado no banco de dados)
- **auth.type**: Tipo de autenticação (`api_key`, `oauth2`, `bearer_token`, `basic_auth`)
- **auth.credentials**: Define quais campos o usuário deve preencher
- **rate_limits**: Limites da API externa
- **features**: Funcionalidades suportadas pelo CRM

---

### 5.5 Arquivo: entities/cliente/config.json

Configuração de uma entidade específica:

```json
{
  "entidade": {
    "slug": "cliente",
    "nome": "Cliente",
    "nome_plural": "Clientes",
    "descricao": "Cadastro de clientes"
  },

  "operacoes_suportadas": {
    "listar": true,
    "buscar": true,
    "criar": true,
    "atualizar": true,
    "deletar": false
  },

  "sincronizacao": {
    "direcao": "bidirecional",
    "estrategia": "incremental",
    "conflito": "crm_externo_vence"
  },

  "paginacao": {
    "tipo": "page",
    "registros_por_pagina": 100,
    "max_por_pagina": 500
  },

  "validacoes": {
    "campos_obrigatorios": ["nome", "email"],
    "formato_email": true,
    "formato_telefone": "brasileiro"
  }
}
```

**Campos importantes:**

- **operacoes_suportadas**: Define quais operações o CRM suporta
- **sincronizacao.direcao**: `bidirecional`, `somente_importar`, `somente_exportar`
- **sincronizacao.estrategia**: `incremental` (apenas novos/alterados) ou `full` (tudo)
- **sincronizacao.conflito**: Como resolver conflitos (`crm_externo_vence`, `ecletech_vence`, `mais_recente_vence`)
- **paginacao.tipo**: `page` (página/offset), `cursor`, `token`

---

### 5.6 Arquivo: entities/cliente/endpoints.json

Define todos os endpoints da API externa para esta entidade:

```json
{
  "endpoints": {
    "listar": {
      "url": "/customers",
      "method": "GET",
      "descricao": "Lista todos os clientes",
      "parametros": {
        "page": {
          "type": "query",
          "required": false,
          "default": 1
        },
        "per_page": {
          "type": "query",
          "required": false,
          "default": 100
        },
        "status": {
          "type": "query",
          "required": false,
          "enum": ["active", "inactive", "all"]
        },
        "created_after": {
          "type": "query",
          "required": false,
          "format": "Y-m-d H:i:s"
        }
      },
      "response": {
        "data_path": "data.customers",
        "pagination": {
          "current_page": "data.current_page",
          "total_pages": "data.total_pages",
          "total": "data.total",
          "per_page": "data.per_page"
        }
      }
    },

    "buscar": {
      "url": "/customers/{id}",
      "method": "GET",
      "descricao": "Busca cliente específico",
      "parametros": {
        "id": {
          "type": "path",
          "required": true
        }
      },
      "response": {
        "data_path": "data.customer"
      }
    },

    "criar": {
      "url": "/customers",
      "method": "POST",
      "descricao": "Cria novo cliente",
      "body_type": "json",
      "response": {
        "data_path": "data.customer",
        "id_field": "id"
      }
    },

    "atualizar": {
      "url": "/customers/{id}",
      "method": "PUT",
      "descricao": "Atualiza cliente existente",
      "parametros": {
        "id": {
          "type": "path",
          "required": true
        }
      },
      "body_type": "json",
      "response": {
        "data_path": "data.customer"
      }
    },

    "deletar": {
      "url": "/customers/{id}",
      "method": "DELETE",
      "descricao": "Remove cliente",
      "parametros": {
        "id": {
          "type": "path",
          "required": true
        }
      }
    }
  }
}
```

**Campos importantes:**

- **url**: Endpoint da API (pode ter placeholders como `{id}`)
- **method**: HTTP method (GET, POST, PUT, DELETE, PATCH)
- **parametros**: Definição de parâmetros (path, query, body)
- **response.data_path**: Caminho JSON onde estão os dados (ex: `data.customers` para `{"data": {"customers": [...]}}`)
- **response.pagination**: Onde encontrar informações de paginação na resposta

---

### 5.7 Arquivo: entities/cliente/mapping.json

Mapeamento completo de campos entre Ecletech ↔ CRM Externo:

```json
{
  "mapeamento": {
    "id": {
      "externo": "id",
      "local": "id",
      "tipo": "integer",
      "somente_leitura": true,
      "descricao": "ID único do cliente"
    },

    "nome": {
      "externo": "name",
      "local": "nome",
      "tipo": "string",
      "obrigatorio": true,
      "max_length": 255,
      "transformacoes": []
    },

    "email": {
      "externo": "email",
      "local": "email",
      "tipo": "email",
      "obrigatorio": true,
      "validacao": "email",
      "transformacoes": ["lowercase", "trim"]
    },

    "telefone": {
      "externo": "phone",
      "local": "telefone",
      "tipo": "string",
      "transformacoes": ["phone_format_br"],
      "formato_externo": "(##) #####-####",
      "formato_local": "###########"
    },

    "documento": {
      "externo": "document",
      "local": "cpf_cnpj",
      "tipo": "string",
      "transformacoes": ["document_format"],
      "validacao": "cpf_ou_cnpj"
    },

    "data_nascimento": {
      "externo": "birth_date",
      "local": "data_nascimento",
      "tipo": "date",
      "transformacoes": ["date_format"],
      "formato_externo": "Y-m-d",
      "formato_local": "d/m/Y"
    },

    "endereco": {
      "externo": "address.street",
      "local": "endereco",
      "tipo": "string",
      "nested": true
    },

    "cidade": {
      "externo": "address.city",
      "local": "cidade",
      "tipo": "string",
      "nested": true
    },

    "estado": {
      "externo": "address.state",
      "local": "estado",
      "tipo": "string",
      "transformacoes": ["uppercase"],
      "max_length": 2
    },

    "status": {
      "externo": "status",
      "local": "ativo",
      "tipo": "boolean",
      "transformacoes": ["status_to_boolean"],
      "mapeamento_valores": {
        "active": true,
        "inactive": false
      }
    },

    "tags": {
      "externo": "tags",
      "local": "tags",
      "tipo": "array",
      "separador": ","
    },

    "valor_total_vendas": {
      "externo": "total_sales",
      "local": "total_vendas",
      "tipo": "money",
      "transformacoes": ["money_format"],
      "casas_decimais": 2,
      "somente_leitura": true
    }
  },

  "campos_calculados": {
    "nome_completo": {
      "formula": "CONCAT(nome, ' ', sobrenome)",
      "tipo": "string"
    }
  },

  "relacionamentos": {
    "vendedor": {
      "externo": "owner_id",
      "local": "id_vendedor",
      "tipo": "belongsTo",
      "entidade": "usuario"
    }
  }
}
```

**Campos importantes:**

- **externo**: Nome do campo no CRM externo
- **local**: Nome do campo no Ecletech
- **tipo**: Tipo de dado (`string`, `integer`, `boolean`, `date`, `datetime`, `money`, `email`, `phone`, `array`)
- **transformacoes**: Lista de transformações a aplicar
- **nested**: Se o campo está dentro de um objeto (ex: `address.street`)
- **mapeamento_valores**: Mapeamento de valores específicos (enum)
- **somente_leitura**: Campo que não pode ser alterado

**Transformações disponíveis:**

```
- lowercase
- uppercase
- trim
- phone_format_br
- document_format
- date_format
- money_format
- status_to_boolean
- array_to_string
- string_to_array
```

---

### 5.8 Classe: ClienteHandler.php

Handler que implementa a lógica de acesso aos dados do Ecletech:

```php
class ClienteHandler implements EntityHandlerInterface
{
    private ServiceCliente $serviceCliente;
    private ModelCliente $modelCliente;

    public function __construct()
    {
        $this->serviceCliente = new ServiceCliente();
        $this->modelCliente = new ModelCliente();
    }

    /**
     * Retorna slug da entidade
     */
    public function getEntitySlug(): string
    {
        return 'cliente';
    }

    /**
     * Busca registro local por ID
     */
    public function findById(int $id, int $idLoja): ?array
    {
        $cliente = $this->modelCliente->buscar($id, $idLoja);
        return $cliente ? $cliente : null;
    }

    /**
     * Busca registro local por external_id
     */
    public function findByExternalId(string $externalId, int $idLoja): ?array
    {
        $cliente = $this->modelCliente->buscarPorExternalId($externalId, $idLoja);
        return $cliente ? $cliente : null;
    }

    /**
     * Lista todos os registros (com paginação)
     */
    public function listAll(int $idLoja, array $filtros = []): array
    {
        return $this->modelCliente->listar($idLoja, $filtros);
    }

    /**
     * Cria novo registro local
     */
    public function create(array $data, int $idLoja): array
    {
        // Usa o Service para garantir validações e regras de negócio
        return $this->serviceCliente->criar($data, $idLoja);
    }

    /**
     * Atualiza registro local
     */
    public function update(int $id, array $data, int $idLoja): array
    {
        return $this->serviceCliente->atualizar($id, $data, $idLoja);
    }

    /**
     * Remove registro local
     */
    public function delete(int $id, int $idLoja): bool
    {
        return $this->serviceCliente->deletar($id, $idLoja);
    }

    /**
     * Retorna snapshot do registro (para auditoria)
     */
    public function getSnapshot(int $id, int $idLoja): array
    {
        return $this->modelCliente->buscar($id, $idLoja);
    }
}
```

**Por que usar Handler?**

- ✅ **Isolamento**: SyncEngine não acessa diretamente Models/Services do Ecletech
- ✅ **Flexibilidade**: Cada entidade pode ter lógica diferente
- ✅ **Testabilidade**: Fácil criar mocks para testes
- ✅ **Manutenção**: Mudanças na estrutura do Ecletech não afetam o Core CRM

---

### 5.9 Auto-Discovery de Providers

O sistema descobre providers automaticamente através do `CrmRegistry`:

```php
class CrmRegistry
{
    private array $providers = [];

    public function __construct()
    {
        $this->discoverProviders();
    }

    /**
     * Descobre todos os providers automaticamente
     */
    private function discoverProviders(): void
    {
        $providersPath = __DIR__ . '/../Providers/';
        $directories = scandir($providersPath);

        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $providerFile = $providersPath . $dir . '/' . $dir . 'Provider.php';

            if (file_exists($providerFile)) {
                require_once $providerFile;

                $className = "App\\CRM\\Providers\\{$dir}\\{$dir}Provider";

                if (class_exists($className)) {
                    $provider = new $className();
                    $this->providers[$provider->getSlug()] = $provider;
                }
            }
        }
    }

    /**
     * Retorna provider por slug
     */
    public function getProvider(string $slug): ?CrmProviderInterface
    {
        return $this->providers[$slug] ?? null;
    }

    /**
     * Retorna todos os providers disponíveis
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }
}
```

**Vantagens do Auto-Discovery:**

- ✅ Adicionar novo provider = criar nova pasta
- ✅ Não precisa registrar manualmente
- ✅ Sistema detecta automaticamente
- ✅ Zero modificação no código core

---

### 5.10 Provider "None" (Sem Integração)

Provider especial para quando não há integração:

```json
{
  "provider": {
    "slug": "none",
    "nome": "Sem Integração",
    "versao": "1.0.0",
    "descricao": "Usar o sistema sem integração com CRM externo"
  },

  "entidades_suportadas": []
}
```

```php
class NoneProvider implements CrmProviderInterface
{
    public function getSlug(): string
    {
        return 'none';
    }

    public function getName(): string
    {
        return 'Sem Integração';
    }

    public function getSupportedEntities(): array
    {
        return []; // Nenhuma entidade
    }

    // ... outros métodos retornam valores vazios
}
```

---

## 6. ENTIDADES

### 6.1 Conceito de Entidade

Uma **Entidade** representa um tipo de dado que pode ser sincronizado entre o Ecletech e o CRM externo (Cliente, Venda, Produto, Atividade, etc.).

**Características:**
- ✅ Cada entidade é independente
- ✅ Possui configuração própria (endpoints, mapeamento, validações)
- ✅ Tem um Handler que acessa dados do Ecletech
- ✅ Pode ter operações diferentes (algumas create/update, outras só read)

### 6.2 Interface: EntityHandlerInterface

Todos os handlers **DEVEM** implementar esta interface:

```php
interface EntityHandlerInterface
{
    /**
     * Retorna slug da entidade
     * Ex: 'cliente', 'venda', 'produto'
     */
    public function getEntitySlug(): string;

    /**
     * Busca registro local por ID
     */
    public function findById(int $id, int $idLoja): ?array;

    /**
     * Busca registro local por external_id
     */
    public function findByExternalId(string $externalId, int $idLoja): ?array;

    /**
     * Lista todos os registros
     */
    public function listAll(int $idLoja, array $filtros = []): array;

    /**
     * Cria novo registro local
     */
    public function create(array $data, int $idLoja): array;

    /**
     * Atualiza registro local
     */
    public function update(int $id, array $data, int $idLoja): array;

    /**
     * Remove registro local
     */
    public function delete(int $id, int $idLoja): bool;

    /**
     * Retorna snapshot do registro (para auditoria)
     */
    public function getSnapshot(int $id, int $idLoja): array;

    /**
     * Retorna configuração da entidade
     */
    public function getConfig(): array;

    /**
     * Retorna mapeamento de campos
     */
    public function getMapping(): array;

    /**
     * Retorna endpoints disponíveis
     */
    public function getEndpoints(): array;
}
```

---

### 6.3 Entidades Suportadas

Lista de entidades comuns que podem ser sincronizadas:

#### 6.3.1 Cliente

**Descrição:** Cadastro de clientes/leads
**Direção:** Bidirecional (importar + exportar)
**Operações:** listar, buscar, criar, atualizar

**Campos principais:**
- nome, email, telefone, cpf_cnpj
- endereco, cidade, estado, cep
- data_nascimento, sexo
- tags, observacoes
- id_vendedor (responsável)

#### 6.3.2 Venda

**Descrição:** Vendas/Negócios/Deals
**Direção:** Bidirecional
**Operações:** listar, buscar, criar, atualizar

**Campos principais:**
- id_cliente
- valor_total, desconto, valor_liquido
- status (aberto, ganho, perdido)
- data_venda, data_fechamento
- itens (array de produtos)
- forma_pagamento

#### 6.3.3 Produto

**Descrição:** Produtos/Serviços
**Direção:** Somente importar (read-only na maioria dos CRMs)
**Operações:** listar, buscar

**Campos principais:**
- nome, descricao
- sku, codigo_barras
- preco_venda, preco_custo
- estoque_atual
- categoria, marca
- ativo

#### 6.3.4 Atividade

**Descrição:** Atividades/Tarefas/Follow-ups
**Direção:** Bidirecional
**Operações:** listar, buscar, criar, atualizar, deletar

**Campos principais:**
- tipo (ligacao, email, reuniao, tarefa)
- assunto, descricao
- id_cliente, id_venda
- data_prevista, data_realizada
- status (pendente, concluido, cancelado)
- id_usuario (responsável)

#### 6.3.5 Outras Entidades Possíveis

- **Funil/Pipeline**: Etapas do funil de vendas
- **Forma de Pagamento**: Métodos de pagamento
- **Categoria**: Categorias de produtos
- **Usuario/Vendedor**: Equipe de vendas
- **Nota Fiscal**: Notas fiscais emitidas
- **Ticket/Suporte**: Tickets de suporte

---

### 6.4 Operações por Entidade

Cada entidade pode suportar operações diferentes dependendo do CRM:

| Entidade | Listar | Buscar | Criar | Atualizar | Deletar |
|----------|--------|--------|-------|-----------|---------|
| Cliente | ✅ | ✅ | ✅ | ✅ | ❌ |
| Venda | ✅ | ✅ | ✅ | ✅ | ❌ |
| Produto | ✅ | ✅ | ❌ | ❌ | ❌ |
| Atividade | ✅ | ✅ | ✅ | ✅ | ✅ |
| Funil | ✅ | ✅ | ❌ | ❌ | ❌ |

**Nota:** Estas operações são **configuradas por provider**, então o mesmo entidade pode ter operações diferentes em CRMs diferentes.

---

### 6.5 Exemplo: VendaHandler.php

```php
class VendaHandler implements EntityHandlerInterface
{
    private ServiceVenda $serviceVenda;
    private ModelVenda $modelVenda;

    public function __construct()
    {
        $this->serviceVenda = new ServiceVenda();
        $this->modelVenda = new ModelVenda();
    }

    public function getEntitySlug(): string
    {
        return 'venda';
    }

    public function findById(int $id, int $idLoja): ?array
    {
        $venda = $this->modelVenda->buscar($id, $idLoja);

        if (!$venda) {
            return null;
        }

        // Busca itens da venda
        $venda['itens'] = $this->modelVenda->buscarItens($id);

        return $venda;
    }

    public function findByExternalId(string $externalId, int $idLoja): ?array
    {
        return $this->modelVenda->buscarPorExternalId($externalId, $idLoja);
    }

    public function listAll(int $idLoja, array $filtros = []): array
    {
        return $this->modelVenda->listar($idLoja, $filtros);
    }

    public function create(array $data, int $idLoja): array
    {
        // Validações específicas de venda
        if (!isset($data['id_cliente'])) {
            throw new Exception('Cliente é obrigatório');
        }

        if (!isset($data['itens']) || empty($data['itens'])) {
            throw new Exception('Venda deve ter ao menos 1 item');
        }

        // Usa Service que tem toda a lógica de negócio
        return $this->serviceVenda->criar($data, $idLoja);
    }

    public function update(int $id, array $data, int $idLoja): array
    {
        return $this->serviceVenda->atualizar($id, $data, $idLoja);
    }

    public function delete(int $id, int $idLoja): bool
    {
        // Vendas geralmente não são deletadas, apenas canceladas
        return $this->serviceVenda->cancelar($id, $idLoja);
    }

    public function getSnapshot(int $id, int $idLoja): array
    {
        $venda = $this->findById($id, $idLoja);

        // Remove campos sensíveis do snapshot
        unset($venda['senha_nf']);

        return $venda;
    }

    public function getConfig(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/config.json'),
            true
        );
    }

    public function getMapping(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/mapping.json'),
            true
        );
    }

    public function getEndpoints(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/endpoints.json'),
            true
        );
    }
}
```

---

### 6.6 Mapeamento de Campos Especiais

#### 6.6.1 Campos Nested (Aninhados)

Quando o CRM externo retorna objetos aninhados:

**Resposta do CRM:**
```json
{
  "id": 123,
  "name": "João Silva",
  "address": {
    "street": "Rua ABC",
    "city": "São Paulo",
    "state": "SP"
  }
}
```

**Mapeamento:**
```json
{
  "endereco": {
    "externo": "address.street",
    "local": "endereco",
    "nested": true
  },
  "cidade": {
    "externo": "address.city",
    "local": "cidade",
    "nested": true
  }
}
```

#### 6.6.2 Arrays e Listas

Para campos que são arrays:

**Resposta do CRM:**
```json
{
  "id": 123,
  "tags": ["vip", "premium", "corporate"]
}
```

**Mapeamento:**
```json
{
  "tags": {
    "externo": "tags",
    "local": "tags",
    "tipo": "array",
    "separador": ","
  }
}
```

**Conversão:**
- Ecletech → CRM: `"vip,premium,corporate"` → `["vip", "premium", "corporate"]`
- CRM → Ecletech: `["vip", "premium", "corporate"]` → `"vip,premium,corporate"`

#### 6.6.3 Relacionamentos

Para campos que representam relacionamentos:

**Mapeamento:**
```json
{
  "relacionamentos": {
    "vendedor": {
      "externo": "owner_id",
      "local": "id_vendedor",
      "tipo": "belongsTo",
      "entidade": "usuario"
    },
    "itens": {
      "externo": "line_items",
      "local": "itens",
      "tipo": "hasMany",
      "entidade": "produto"
    }
  }
}
```

#### 6.6.4 Campos Calculados

Campos que não existem na base mas são calculados:

**Mapeamento:**
```json
{
  "campos_calculados": {
    "nome_completo": {
      "formula": "CONCAT(nome, ' ', sobrenome)",
      "tipo": "string"
    },
    "dias_desde_criacao": {
      "formula": "DATEDIFF(NOW(), criado_em)",
      "tipo": "integer"
    }
  }
}
```

#### 6.6.5 Enums e Mapeamento de Valores

Para mapear valores fixos (status, tipos, etc.):

**Mapeamento:**
```json
{
  "status": {
    "externo": "status",
    "local": "status",
    "tipo": "string",
    "mapeamento_valores": {
      "open": "aberto",
      "won": "ganho",
      "lost": "perdido",
      "canceled": "cancelado"
    }
  }
}
```

**Conversão:**
- Ecletech → CRM: `"ganho"` → `"won"`
- CRM → Ecletech: `"won"` → `"ganho"`

---

### 6.7 Entidades Read-Only

Algumas entidades são **somente leitura** (importar do CRM, não exportar):

**Exemplo: Produto**

```json
{
  "entidade": {
    "slug": "produto",
    "nome": "Produto"
  },

  "operacoes_suportadas": {
    "listar": true,
    "buscar": true,
    "criar": false,
    "atualizar": false,
    "deletar": false
  },

  "sincronizacao": {
    "direcao": "somente_importar",
    "estrategia": "full"
  }
}
```

**Comportamento:**
- ✅ Sincronizar produtos do CRM → Ecletech
- ❌ Criar produto no Ecletech não envia para CRM
- ❌ Atualizar produto no Ecletech não envia para CRM

---

### 6.8 Validações por Entidade

Cada entidade pode ter validações específicas:

```json
{
  "validacoes": {
    "campos_obrigatorios": ["nome", "email", "id_cliente"],

    "formato_email": true,
    "formato_telefone": "brasileiro",
    "formato_cpf_cnpj": true,

    "valores_permitidos": {
      "status": ["aberto", "ganho", "perdido", "cancelado"],
      "tipo_pessoa": ["F", "J"]
    },

    "ranges": {
      "valor_total": {
        "min": 0,
        "max": 999999999.99
      },
      "desconto_percentual": {
        "min": 0,
        "max": 100
      }
    },

    "custom": {
      "itens_minimo": {
        "campo": "itens",
        "validacao": "count",
        "min": 1,
        "mensagem": "Venda deve ter ao menos 1 item"
      }
    }
  }
}
```

---

### 6.9 Exemplo Completo: Entidade Venda

**Estrutura:**
```
entities/venda/
├── config.json
├── endpoints.json
├── mapping.json
└── VendaHandler.php
```

**config.json:**
```json
{
  "entidade": {
    "slug": "venda",
    "nome": "Venda",
    "nome_plural": "Vendas",
    "descricao": "Negócios e vendas fechadas"
  },

  "operacoes_suportadas": {
    "listar": true,
    "buscar": true,
    "criar": true,
    "atualizar": true,
    "deletar": false
  },

  "sincronizacao": {
    "direcao": "bidirecional",
    "estrategia": "incremental",
    "conflito": "mais_recente_vence",
    "campo_timestamp": "atualizado_em"
  },

  "paginacao": {
    "tipo": "page",
    "registros_por_pagina": 50,
    "max_por_pagina": 200
  },

  "validacoes": {
    "campos_obrigatorios": ["id_cliente", "valor_total", "itens"],
    "itens_minimo": 1
  }
}
```

**Principais Campos da Venda:**

| Campo Local | Campo Externo | Tipo | Transformação |
|-------------|---------------|------|---------------|
| id | id | integer | - |
| id_cliente | customer_id | integer | - |
| valor_total | total_amount | money | money_format |
| desconto | discount | money | money_format |
| status | status | enum | status_mapping |
| data_venda | sale_date | date | date_format |
| itens | line_items | array | - |
| observacoes | notes | text | - |

---

## 7. SISTEMA DE MAPEAMENTO

O sistema de mapeamento é responsável por converter dados entre o formato do Ecletech e o formato do CRM externo.

### 7.1 FieldMapper

Classe principal que realiza o mapeamento:

```php
class FieldMapper
{
    public function toExternal(array $data, array $mapping): array
    {
        // Converte dados do Ecletech → CRM Externo
        // Aplica transformações
        // Processa nested fields
        // Retorna array no formato do CRM
    }

    public function toLocal(array $data, array $mapping): array
    {
        // Converte dados do CRM Externo → Ecletech
        // Reverte transformações
        // Extrai nested fields
        // Retorna array no formato do Ecletech
    }
}
```

### 7.2 Fluxo de Mapeamento

```
Ecletech → CRM Externo:
1. Dados do Ecletech (formato local)
2. FieldMapper.toExternal()
3. Aplica transformações
4. Renomeia campos conforme mapping
5. Dados no formato do CRM Externo

CRM Externo → Ecletech:
1. Dados do CRM (formato externo)
2. FieldMapper.toLocal()
3. Extrai campos nested
4. Reverte transformações
5. Renomeia campos conforme mapping
6. Dados no formato do Ecletech
```

---

## 8. SISTEMA DE TRANSFORMAÇÃO

Transformadores convertem tipos de dados específicos (datas, moedas, telefones, etc.).

### 8.1 Transformadores Disponíveis

#### DateTransformer
```php
// Ecletech (d/m/Y) → CRM (Y-m-d)
"14/01/2025" → "2025-01-14"

// CRM (Y-m-d) → Ecletech (d/m/Y)
"2025-01-14" → "14/01/2025"
```

#### MoneyTransformer
```php
// Ecletech (float) → CRM (string formatado)
1599.90 → "1599.90"

// CRM (cents) → Ecletech (float)
159990 → 1599.90
```

#### PhoneTransformer
```php
// Ecletech (sem formatação) → CRM (com formatação)
"11999998888" → "(11) 99999-8888"

// CRM (formatado) → Ecletech (apenas números)
"(11) 99999-8888" → "11999998888"
```

#### DocumentTransformer
```php
// CPF/CNPJ
"12345678901" → "123.456.789-01"
"12345678000190" → "12.345.678/0001-90"
```

### 8.2 Transformações Customizadas

Cada provider pode ter seus próprios transformadores em `Providers/{Provider}/Transformers/`.

---

## 9. FLUXOS COMPLETOS

### 9.1 Fluxo: Criar Cliente no Ecletech (COM CRM)

```
1. Frontend envia POST /api/clientes
   {
     "nome": "João Silva",
     "email": "joao@email.com",
     "telefone": "11999998888"
   }

2. Controller valida JWT e ACL

3. ServiceCliente.criar()
   ├─ Validações de negócio
   └─ Verifica se tem CRM ativo

4. [TEM CRM ATIVO?]
   └─ SIM → Continua fluxo CRM

5. CrmManager.getProvider('gestao_click')

6. FieldMapper.toExternal()
   ├─ Aplica transformações
   └─ Retorna dados no formato do CRM:
   {
     "name": "João Silva",
     "email": "joao@email.com",
     "phone": "(11) 99999-8888"
   }

7. HttpClient.post('/customers', dados)
   └─ Envia para API do GestaoClick

8. Resposta do CRM:
   {
     "data": {
       "customer": {
         "id": "gc_12345",
         "name": "João Silva",
         "created_at": "2025-01-14T10:30:00Z"
       }
     }
   }

9. ResponseParser extrai dados

10. ModelCliente.criar()
    {
      "nome": "João Silva",
      "email": "joao@email.com",
      "telefone": "11999998888",
      "external_id": "gc_12345"  ← Vincula com CRM
    }

11. AuditLogger.registrar()
    ├─ dados_antes: null
    ├─ dados_depois: {...}
    ├─ request_enviado: {...}
    └─ response_recebido: {...}

12. Retorna sucesso para Frontend
```

### 9.2 Fluxo: Sincronização Paginada (CRON)

```
CRON executa a cada 5 minutos

1. CronExecutor identifica jobs ativos
   └─ Job: "Sincronizar Clientes"

2. SyncEngine.syncPaginated('cliente')

3. Busca configuração:
   ├─ Provider: gestao_click
   ├─ Endpoints: /customers?page=X&per_page=100
   └─ Mapping: mapping.json

4. Loop por páginas:

   PÁGINA 1:
   ├─ GET /customers?page=1&per_page=100
   ├─ Retorna 100 clientes
   └─ Para cada cliente:
       ├─ Verifica se existe (busca por external_id)
       ├─ SE NÃO EXISTE → criar
       ├─ SE EXISTE → verificar se mudou
       │   └─ SE MUDOU → atualizar
       └─ Registra em crm_auditoria

   PÁGINA 2:
   ├─ GET /customers?page=2&per_page=100
   └─ ... (repete processo)

   ...

   ÚLTIMA PÁGINA:
   ├─ GET /customers?page=4&per_page=100
   ├─ Retorna 50 clientes (última página)
   └─ Processa

5. Atualiza crm_sync_historico:
   {
     "total_registros": 350,
     "total_criados": 12,
     "total_atualizados": 338,
     "total_erros": 0,
     "status": "concluido"
   }

6. Atualiza crm_agendamentos:
   ├─ ultima_execucao: agora
   ├─ proxima_execucao: +5 minutos
   └─ total_sucessos++
```

### 9.3 Fluxo: Sincronização Individual

```
Usuário está vendo detalhes do Cliente #450
Clica no botão "Sincronizar com CRM"

1. Frontend POST /api/crm/sync/individual
   {
     "entidade": "cliente",
     "id": 450
   }

2. Controller valida permissão (crm.sync.executar_individual)

3. SyncIndividual.sync('cliente', 450)

4. Busca registro no Ecletech:
   {
     "id": 450,
     "nome": "João Silva Santos",  ← Foi alterado
     "email": "joao@email.com",
     "external_id": "gc_12345"
   }

5. Busca registro no CRM (GET /customers/gc_12345):
   {
     "id": "gc_12345",
     "name": "João Silva",  ← Está desatualizado
     "email": "joao@email.com"
   }

6. Compara timestamps:
   ├─ Ecletech: atualizado_em = 2025-01-14 10:25:00
   └─ CRM: updated_at = 2025-01-13 15:00:00

   → Ecletech é mais recente, vai EXPORTAR

7. FieldMapper.toExternal()

8. PUT /customers/gc_12345
   {
     "name": "João Silva Santos",
     "email": "joao@email.com"
   }

9. Registra auditoria com diff:
   {
     "diferencas": {
       "nome": {
         "de": "João Silva",
         "para": "João Silva Santos"
       }
     }
   }

10. Retorna sucesso
```

### 9.4 Fluxo: Tratamento de Erro

```
Tentativa de criar cliente no CRM

1. Envia request para CRM

2. CRM retorna erro 400:
   {
     "error": "Email already exists",
     "code": "DUPLICATE_EMAIL"
   }

3. HttpClient detecta erro

4. [RETRY?]
   ├─ Se erro 5xx (server error) → RETRY
   └─ Se erro 4xx (client error) → NÃO RETRY

5. Registra em crm_logs:
   {
     "nivel": "error",
     "mensagem": "Falha ao criar cliente no CRM",
     "detalhes": {
       "http_status": 400,
       "erro_crm": "Email already exists"
     }
   }

6. Registra em crm_auditoria:
   {
     "sucesso": 0,
     "erro": "Email already exists"
   }

7. [CRIAR LOCAL MESMO ASSIM?]
   ├─ Depende da configuração
   └─ Se "ignorar_erros_crm": true → cria local sem external_id

8. Retorna erro para Frontend com mensagem clara
```

---

## 10. CONFIGURAÇÃO

### 10.1 Escolher CRM

**Endpoint:** `GET /api/crm/providers`

Retorna lista de providers disponíveis:

```json
{
  "providers": [
    {
      "slug": "none",
      "nome": "Sem Integração",
      "descricao": "Usar o sistema sem integração com CRM externo"
    },
    {
      "slug": "gestao_click",
      "nome": "GestaoClick CRM",
      "descricao": "Integração com GestaoClick CRM",
      "entidades_suportadas": ["cliente", "venda", "produto", "atividade"],
      "campos_credenciais": [
        {
          "name": "api_key",
          "label": "Chave de API",
          "type": "password",
          "required": true
        }
      ]
    },
    {
      "slug": "pipedrive",
      "nome": "Pipedrive",
      "entidades_suportadas": ["cliente", "venda", "atividade"]
    }
  ]
}
```

### 10.2 Configurar CRM

**Endpoint:** `POST /api/crm/config`

```json
{
  "provider_slug": "gestao_click",
  "credenciais": {
    "api_key": "abc123xyz789"
  },
  "entidades_habilitadas": ["cliente", "venda", "produto"]
}
```

**Fluxo:**
1. Valida credenciais chamando `provider.validateCredentials()`
2. Testa conexão chamando `provider.testConnection()`
3. Se válido, criptografa credenciais
4. Salva em `crm_integracoes`
5. Retorna sucesso

### 10.3 Testar Conexão

**Endpoint:** `POST /api/crm/config/test`

Testa conexão com CRM sem salvar:

```json
{
  "provider_slug": "gestao_click",
  "credenciais": {
    "api_key": "abc123xyz789"
  }
}
```

**Resposta (sucesso):**
```json
{
  "sucesso": true,
  "mensagem": "Conexão estabelecida com sucesso",
  "detalhes": {
    "versao_api": "v1",
    "rate_limit": "60 req/min"
  }
}
```

**Resposta (erro):**
```json
{
  "sucesso": false,
  "erro": "Credenciais inválidas",
  "codigo": "INVALID_API_KEY"
}
```

### 10.4 Configurar Mapeamento de Campos

**Endpoint:** `PUT /api/crm/mapping/{entidade}`

Permite customizar mapeamento de campos:

```json
{
  "entidade": "cliente",
  "mapeamento_custom": {
    "telefone": {
      "externo": "mobile_phone",
      "transformacoes": ["phone_format_br"]
    },
    "cpf_cnpj": {
      "externo": "tax_id",
      "transformacoes": ["document_format"]
    }
  }
}
```

---

## 11. SINCRONIZAÇÃO

### 11.1 Sincronização Manual (Paginada)

**Endpoint:** `POST /api/crm/sync/manual`

Permissão: `crm.sync.executar_manual`

```json
{
  "entidade": "cliente",
  "filtros": {
    "status": "active",
    "created_after": "2025-01-01"
  }
}
```

**Resposta:**
```json
{
  "sync_id": 5421,
  "status": "em_andamento",
  "mensagem": "Sincronização iniciada"
}
```

**Acompanhar progresso:**
`GET /api/crm/sync/status/{sync_id}`

```json
{
  "sync_id": 5421,
  "status": "em_andamento",
  "progresso": {
    "pagina_atual": 2,
    "total_paginas": 4,
    "registros_processados": 200,
    "registros_criados": 5,
    "registros_atualizados": 195,
    "registros_erros": 0
  },
  "tempo_decorrido": "45s",
  "tempo_estimado": "90s"
}
```

### 11.2 Sincronização Individual

**Endpoint:** `POST /api/crm/sync/individual`

Permissão: `crm.sync.executar_individual`

```json
{
  "entidade": "cliente",
  "id": 450,
  "direcao": "auto"
}
```

**Direções possíveis:**
- `auto`: Compara timestamps e sincroniza o mais recente
- `importar`: Força importação do CRM → Ecletech
- `exportar`: Força exportação do Ecletech → CRM

**Resposta:**
```json
{
  "sucesso": true,
  "direcao_executada": "exportar",
  "mensagem": "Cliente sincronizado com sucesso",
  "alteracoes": {
    "nome": {
      "de": "João Silva",
      "para": "João Silva Santos"
    }
  }
}
```

### 11.3 Agendamento (CRON)

**Endpoint:** `POST /api/crm/agendamentos`

Permissão: `crm.agendamentos.criar`

```json
{
  "nome": "Sincronizar Clientes",
  "entidade": "cliente",
  "tipo_sync": "paginated",
  "cron_expression": "*/5 * * * *",
  "filtros": {
    "status": "active"
  },
  "ativo": true
}
```

**Listar agendamentos:**
`GET /api/crm/agendamentos`

```json
{
  "agendamentos": [
    {
      "id": 1,
      "nome": "Sincronizar Clientes",
      "entidade": "cliente",
      "cron_expression": "*/5 * * * *",
      "ativo": true,
      "ultima_execucao": "2025-01-14 10:25:00",
      "proxima_execucao": "2025-01-14 10:30:00",
      "total_execucoes": 2880,
      "total_sucessos": 2875,
      "total_falhas": 5
    }
  ]
}
```

### 11.4 Histórico de Sincronizações

**Endpoint:** `GET /api/crm/sync/historico`

```json
{
  "historico": [
    {
      "id": 5421,
      "entidade": "cliente",
      "tipo_sync": "paginated",
      "origem": "automatico",
      "inicio": "2025-01-14 10:25:00",
      "fim": "2025-01-14 10:26:15",
      "duracao_segundos": 75,
      "status": "concluido",
      "total_registros": 350,
      "total_criados": 12,
      "total_atualizados": 338,
      "total_erros": 0
    }
  ],
  "paginacao": {
    "pagina": 1,
    "por_pagina": 20,
    "total": 150
  }
}
```

**Filtros disponíveis:**
- `entidade`
- `status` (em_andamento, concluido, erro, cancelado)
- `origem` (manual, automatico)
- `data_inicio`, `data_fim`

### 11.5 Estratégias de Sincronização

#### Incremental (padrão)
- Sincroniza apenas registros novos ou alterados
- Usa campo `updated_at` ou `modified_at`
- Mais rápido e eficiente
- Recomendado para sincronizações frequentes

#### Full
- Sincroniza TODOS os registros
- Ignora timestamps
- Mais lento mas garante consistência total
- Recomendado para sincronização inicial ou reconciliação

#### Configuração:
```json
{
  "sincronizacao": {
    "estrategia": "incremental",
    "campo_timestamp": "atualizado_em"
  }
}
```

### 11.6 Resolução de Conflitos

Quando o mesmo registro foi alterado em ambos os lados:

**Estratégias disponíveis:**

1. **crm_externo_vence**
   - CRM externo sempre ganha
   - Sobrescreve dados do Ecletech

2. **ecletech_vence**
   - Ecletech sempre ganha
   - Sobrescreve dados do CRM

3. **mais_recente_vence** (padrão)
   - Compara `atualizado_em` vs `updated_at`
   - Usa o registro mais recente

4. **manual**
   - Não sincroniza automaticamente
   - Gera alerta para resolução manual

**Configuração:**
```json
{
  "sincronizacao": {
    "conflito": "mais_recente_vence"
  }
}
```

---

## 12. AUDITORIA

### 12.1 Consultar Auditoria

**Endpoint:** `GET /api/crm/auditoria`

Permissão: `crm.auditoria.visualizar`

```json
{
  "registros": [
    {
      "id": 1523,
      "operacao": "update",
      "entidade": "cliente",
      "id_registro_local": 450,
      "id_registro_externo": "gc_12345",
      "diferencas": {
        "nome": {
          "de": "João Silva",
          "para": "João Silva Santos"
        }
      },
      "sucesso": true,
      "duracao_ms": 234,
      "usuario": {
        "id": 5,
        "nome": "Admin"
      },
      "origem": "manual",
      "criado_em": "2025-01-14 10:30:00"
    }
  ]
}
```

**Filtros disponíveis:**
- `entidade`
- `operacao` (create, update, delete, sync_paginated, sync_individual)
- `id_registro_local`
- `usuario_id`
- `sucesso` (true/false)
- `data_inicio`, `data_fim`

### 12.2 Detalhes da Auditoria

**Endpoint:** `GET /api/crm/auditoria/{id}`

Permissão: `crm.auditoria.visualizar_detalhes`

```json
{
  "id": 1523,
  "operacao": "update",
  "entidade": "cliente",
  "id_registro_local": 450,
  "id_registro_externo": "gc_12345",

  "dados_antes": {
    "id": 450,
    "nome": "João Silva",
    "email": "joao@email.com",
    "telefone": "11999998888"
  },

  "dados_depois": {
    "id": 450,
    "nome": "João Silva Santos",
    "email": "joao@email.com",
    "telefone": "11999998888"
  },

  "diferencas": {
    "nome": {
      "de": "João Silva",
      "para": "João Silva Santos"
    }
  },

  "request_enviado": {
    "url": "https://api.gestaoclick.com/v1/customers/gc_12345",
    "method": "PUT",
    "headers": {
      "Authorization": "Bearer ***",
      "Content-Type": "application/json"
    },
    "body": {
      "name": "João Silva Santos",
      "email": "joao@email.com",
      "phone": "(11) 99999-8888"
    }
  },

  "response_recebido": {
    "status": 200,
    "headers": {
      "Content-Type": "application/json"
    },
    "body": {
      "id": "gc_12345",
      "name": "João Silva Santos",
      "updated_at": "2025-01-14T10:30:00Z"
    }
  },

  "sucesso": true,
  "duracao_ms": 234,
  "usuario": {
    "id": 5,
    "nome": "Admin",
    "email": "admin@empresa.com"
  },
  "usuario_ip": "192.168.1.100",
  "origem": "manual",
  "criado_em": "2025-01-14 10:30:00"
}
```

### 12.3 Diff Automático

O sistema calcula automaticamente as diferenças entre `dados_antes` e `dados_depois`:

```json
{
  "diferencas": {
    "nome": {
      "de": "João Silva",
      "para": "João Silva Santos"
    },
    "telefone": {
      "de": "11999998888",
      "para": "11888887777"
    },
    "endereco": {
      "de": null,
      "para": "Rua ABC, 123"
    }
  }
}
```

**Tipos de mudança:**
- Valor alterado: `{"de": "valor_antigo", "para": "valor_novo"}`
- Campo adicionado: `{"de": null, "para": "valor_novo"}`
- Campo removido: `{"de": "valor_antigo", "para": null}`

### 12.4 Rastreabilidade Completa

Cada registro de auditoria contém:

✅ **O QUE** foi alterado (entidade, id)
✅ **QUANDO** foi alterado (timestamp)
✅ **QUEM** alterou (usuário)
✅ **DE ONDE** alterou (IP)
✅ **COMO** estava antes (dados_antes)
✅ **COMO** ficou depois (dados_depois)
✅ **O QUE MUDOU** (diff calculado)
✅ **REQUEST** enviado ao CRM
✅ **RESPONSE** recebido do CRM
✅ **SUCESSO** ou **ERRO**
✅ **TEMPO** de execução

### 12.5 Exportar Auditoria

**Endpoint:** `GET /api/crm/auditoria/exportar`

Permissão: `crm.auditoria.exportar`

Formatos disponíveis:
- `csv`
- `xlsx`
- `json`

```
GET /api/crm/auditoria/exportar?formato=xlsx&data_inicio=2025-01-01&data_fim=2025-01-14
```

Retorna arquivo para download com todos os registros de auditoria filtrados.

---

## 13. PAINEL ADMINISTRATIVO

### 13.1 Dashboard

**Rota:** `/admin/crm/dashboard`

Visão geral do sistema CRM:

**Métricas principais:**
- Total de sincronizações hoje
- Total de registros sincronizados (este mês)
- Taxa de sucesso (%)
- Última sincronização
- Status da conexão com CRM
- Próxima sincronização agendada

**Gráficos:**
- Sincronizações por dia (últimos 30 dias)
- Taxa de sucesso/erro
- Registros criados vs atualizados
- Tempo médio de sincronização

**Alertas:**
- Sincronizações falhadas
- Credenciais expiradas/inválidas
- Rate limit atingido
- Conflitos pendentes de resolução

### 13.2 Configuração

**Rota:** `/admin/crm/configuracao`

- Escolher provider
- Configurar credenciais
- Testar conexão
- Habilitar/desabilitar entidades
- Configurar mapeamentos customizados
- Opções avançadas (timeout, retry, batch size)

### 13.3 Sincronização

**Rota:** `/admin/crm/sincronizacao`

- Executar sincronização manual
- Ver sincronizações em andamento
- Histórico de sincronizações
- Cancelar sincronização
- Re-executar sincronização falhada

### 13.4 Agendamentos

**Rota:** `/admin/crm/agendamentos`

- Listar agendamentos CRON
- Criar novo agendamento
- Editar agendamento
- Ativar/desativar
- Executar agora (teste)
- Ver histórico de execuções

### 13.5 Auditoria

**Rota:** `/admin/crm/auditoria`

- Buscar registros de auditoria
- Filtros avançados
- Ver detalhes completos (request/response)
- Exportar logs
- Timeline de alterações

### 13.6 Mapeamentos

**Rota:** `/admin/crm/mapeamentos`

- Ver mapeamentos atuais
- Customizar mapeamentos
- Testar transformações
- Validar mapeamentos
- Importar/exportar configurações

### 13.7 Estatísticas

**Rota:** `/admin/crm/estatisticas`

- Estatísticas por entidade
- Performance de sincronizações
- Uso de API (rate limiting)
- Tempo médio por operação
- Erros mais comuns
- Top registros sincronizados

---

## 14. PERMISSÕES (ACL)

### 14.1 Hierarquia de Permissões

```
crm.*                          → Acesso total ao módulo CRM

crm.configuracao.*             → Acesso total a configurações
├─ crm.configuracao.visualizar
├─ crm.configuracao.editar
└─ crm.configuracao.testar_conexao

crm.sync.*                     → Acesso total a sincronizações
├─ crm.sync.executar_manual
├─ crm.sync.executar_individual
├─ crm.sync.visualizar_historico
└─ crm.sync.cancelar

crm.agendamentos.*             → Acesso total a agendamentos
├─ crm.agendamentos.visualizar
├─ crm.agendamentos.criar
├─ crm.agendamentos.editar
├─ crm.agendamentos.deletar
└─ crm.agendamentos.executar

crm.auditoria.*                → Acesso total a auditoria
├─ crm.auditoria.visualizar
├─ crm.auditoria.visualizar_detalhes
└─ crm.auditoria.exportar

crm.mapeamentos.*              → Acesso total a mapeamentos
├─ crm.mapeamentos.visualizar
├─ crm.mapeamentos.editar
└─ crm.mapeamentos.testar

crm.dashboard.*                → Acesso ao dashboard
└─ crm.dashboard.visualizar
```

### 14.2 Perfis de Acesso

#### Superadmin
- Permissão: `crm.*`
- Acesso total ao módulo CRM

#### CRM Admin
```json
{
  "permissoes": [
    "crm.configuracao.*",
    "crm.sync.*",
    "crm.agendamentos.*",
    "crm.auditoria.*",
    "crm.mapeamentos.*",
    "crm.dashboard.*"
  ]
}
```

#### CRM Operator
```json
{
  "permissoes": [
    "crm.sync.executar_manual",
    "crm.sync.executar_individual",
    "crm.sync.visualizar_historico",
    "crm.auditoria.visualizar",
    "crm.dashboard.visualizar"
  ]
}
```

#### CRM Viewer
```json
{
  "permissoes": [
    "crm.sync.visualizar_historico",
    "crm.auditoria.visualizar",
    "crm.dashboard.visualizar"
  ]
}
```

### 14.3 Middleware ACL

Todas as rotas do CRM passam pelo middleware de ACL:

```php
Route::group(['prefix' => 'crm', 'middleware' => ['jwt', 'crm.acl']], function() {
    // Rotas protegidas
});
```

---

## 15. API COMPLETA

### 15.1 Providers

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/api/crm/providers` | - | Lista providers disponíveis |
| GET | `/api/crm/providers/{slug}` | - | Detalhes de um provider |

### 15.2 Configuração

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/api/crm/config` | `configuracao.visualizar` | Configuração atual |
| POST | `/api/crm/config` | `configuracao.editar` | Salva configuração |
| PUT | `/api/crm/config` | `configuracao.editar` | Atualiza configuração |
| POST | `/api/crm/config/test` | `configuracao.testar_conexao` | Testa conexão |
| DELETE | `/api/crm/config` | `configuracao.editar` | Remove integração |

### 15.3 Sincronização

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| POST | `/api/crm/sync/manual` | `sync.executar_manual` | Sync manual paginada |
| POST | `/api/crm/sync/individual` | `sync.executar_individual` | Sync individual |
| GET | `/api/crm/sync/status/{id}` | `sync.visualizar_historico` | Status de sync |
| POST | `/api/crm/sync/cancel/{id}` | `sync.cancelar` | Cancela sync |
| GET | `/api/crm/sync/historico` | `sync.visualizar_historico` | Histórico |

### 15.4 Agendamentos

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/api/crm/agendamentos` | `agendamentos.visualizar` | Lista agendamentos |
| POST | `/api/crm/agendamentos` | `agendamentos.criar` | Cria agendamento |
| PUT | `/api/crm/agendamentos/{id}` | `agendamentos.editar` | Atualiza |
| DELETE | `/api/crm/agendamentos/{id}` | `agendamentos.deletar` | Remove |
| POST | `/api/crm/agendamentos/{id}/executar` | `agendamentos.executar` | Executa agora |

### 15.5 Auditoria

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/api/crm/auditoria` | `auditoria.visualizar` | Lista registros |
| GET | `/api/crm/auditoria/{id}` | `auditoria.visualizar_detalhes` | Detalhes |
| GET | `/api/crm/auditoria/exportar` | `auditoria.exportar` | Exporta logs |

### 15.6 Mapeamentos

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/api/crm/mapeamentos/{entidade}` | `mapeamentos.visualizar` | Ver mapeamento |
| PUT | `/api/crm/mapeamentos/{entidade}` | `mapeamentos.editar` | Customizar |
| POST | `/api/crm/mapeamentos/{entidade}/testar` | `mapeamentos.testar` | Testar |

### 15.7 Dashboard

| Método | Endpoint | Permissão | Descrição |
|--------|----------|-----------|-----------|
| GET | `/api/crm/dashboard` | `dashboard.visualizar` | Métricas e estatísticas |

---

## 16. GUIAS PRÁTICOS

### 16.1 Como Adicionar um Novo CRM

**Passo 1:** Criar pasta do provider
```bash
mkdir App/CRM/Providers/NovoCRM
```

**Passo 2:** Criar arquivo principal
```php
// App/CRM/Providers/NovoCRM/NovoCRMProvider.php
class NovoCRMProvider implements CrmProviderInterface
{
    // Implementar interface
}
```

**Passo 3:** Criar config.json
```json
{
  "provider": {
    "slug": "novo_crm",
    "nome": "Novo CRM",
    "versao": "1.0.0"
  }
}
```

**Passo 4:** Criar entidades
```bash
mkdir App/CRM/Providers/NovoCRM/entities/cliente
```

**Passo 5:** Criar arquivos da entidade
- `config.json`
- `endpoints.json`
- `mapping.json`
- `ClienteHandler.php`

**Pronto!** O sistema irá descobrir automaticamente o novo provider.

### 16.2 Como Adicionar uma Nova Entidade

**Passo 1:** Criar pasta da entidade
```bash
mkdir App/CRM/Providers/GestaoClick/entities/tarefa
```

**Passo 2:** Criar arquivos de configuração
- `config.json` - Configurações da entidade
- `endpoints.json` - Endpoints da API
- `mapping.json` - Mapeamento de campos

**Passo 3:** Criar Handler
```php
// TarefaHandler.php
class TarefaHandler implements EntityHandlerInterface
{
    // Implementar métodos
}
```

**Passo 4:** Adicionar campo external_id na tabela
```sql
ALTER TABLE tarefas
ADD COLUMN external_id VARCHAR(100) NULL,
ADD UNIQUE KEY unique_external_id (id_loja, external_id);
```

**Pronto!** A entidade está disponível para sincronização.

### 16.3 Como Customizar Transformações

**Criar transformador customizado:**

```php
// App/CRM/Providers/GestaoClick/Transformers/CustomTransformer.php
class CustomTransformer implements TransformerInterface
{
    public function transform($value, string $direction)
    {
        if ($direction === 'to_external') {
            // Ecletech → CRM
            return $this->toExternal($value);
        }

        // CRM → Ecletech
        return $this->toLocal($value);
    }

    private function toExternal($value)
    {
        // Lógica de transformação
        return $transformedValue;
    }

    private function toLocal($value)
    {
        // Lógica reversa
        return $transformedValue;
    }
}
```

**Usar no mapping.json:**
```json
{
  "campo_custom": {
    "externo": "custom_field",
    "local": "campo_custom",
    "transformacoes": ["custom_transformer"]
  }
}
```

### 16.4 Como Resolver Conflitos Manualmente

**Cenário:** Registro alterado em ambos os lados

1. Ver conflitos pendentes: `GET /api/crm/conflitos`

2. Ver detalhes do conflito:
```json
{
  "id_conflito": 123,
  "entidade": "cliente",
  "id_local": 450,
  "id_externo": "gc_12345",
  "dados_local": {...},
  "dados_externo": {...},
  "diferencas": {...}
}
```

3. Escolher resolução:
```
POST /api/crm/conflitos/123/resolver
{
  "escolha": "local"  // ou "externo" ou "custom"
}
```

---

## 17. TROUBLESHOOTING

### 17.1 Erro: "Credenciais inválidas"

**Causa:** API Key incorreta ou expirada

**Solução:**
1. Verificar API Key no CRM externo
2. Gerar nova API Key se necessário
3. Atualizar configuração: `PUT /api/crm/config`
4. Testar conexão: `POST /api/crm/config/test`

### 17.2 Erro: "Rate limit exceeded"

**Causa:** Muitas requisições em pouco tempo

**Solução:**
1. Verificar rate limits do provider: `GET /api/crm/providers/{slug}`
2. Reduzir frequência de sincronizações
3. Aguardar reset do rate limit
4. Considerar aumentar intervalo do CRON

### 17.3 Sincronização muito lenta

**Possíveis causas:**
- Muitos registros sendo processados
- Timeout da API muito alto
- Rede lenta

**Soluções:**
1. Reduzir `registros_por_pagina` no config.json
2. Usar filtros para limitar registros
3. Usar sincronização incremental ao invés de full
4. Verificar logs: `GET /api/crm/logs?nivel=warning`

### 17.4 Registros duplicados

**Causa:** external_id não está sendo salvo corretamente

**Solução:**
1. Verificar logs de auditoria
2. Verificar se tabela tem campo `external_id`
3. Executar reconciliação:
```
POST /api/crm/reconciliar
{
  "entidade": "cliente"
}
```

### 17.5 Erro: "Mapping field not found"

**Causa:** Campo mapeado não existe na resposta do CRM

**Solução:**
1. Ver resposta real do CRM nos logs de auditoria
2. Ajustar mapping.json com campos corretos
3. Testar mapeamento: `POST /api/crm/mapeamentos/cliente/testar`

### 17.6 Logs e Debug

**Ver logs em tempo real:**
```bash
tail -f logs/crm_debug.log
```

**Ativar modo debug:**
```php
// config.json do provider
{
  "debug": true,
  "log_level": "debug"
}
```

**Ver requests/responses:**
```sql
SELECT request_enviado, response_recebido
FROM crm_auditoria
WHERE id_registro_local = 450
ORDER BY criado_em DESC
LIMIT 1;
```

---

## 18. REFERÊNCIAS

### 18.1 Documentação de CRMs Externos

**GestaoClick**
- API Docs: https://api.gestaoclick.com/docs
- Auth: API Key
- Rate Limit: 60 req/min

**Pipedrive**
- API Docs: https://developers.pipedrive.com/docs/api/v1
- Auth: API Token
- Rate Limit: 100 req/10s

**Bling**
- API Docs: https://developer.bling.com.br/
- Auth: API Key
- Rate Limit: 30 req/min

**RD Station**
- API Docs: https://developers.rdstation.com/
- Auth: OAuth2
- Rate Limit: 120 req/min

**HubSpot**
- API Docs: https://developers.hubspot.com/docs/api/overview
- Auth: OAuth2 / API Key
- Rate Limit: 100 req/10s

### 18.2 Padrões de Paginação

**Page-based (página/offset):**
```
?page=1&per_page=100
?page=2&per_page=100
```

**Cursor-based:**
```
?cursor=abc123&limit=100
?cursor=def456&limit=100
```

**Token-based:**
```
?page_token=xyz789&page_size=100
?page_token=uvw012&page_size=100
```

### 18.3 Padrões de Autenticação

**API Key:**
```
Headers: { "X-API-Key": "abc123" }
```

**Bearer Token:**
```
Headers: { "Authorization": "Bearer abc123" }
```

**Basic Auth:**
```
Headers: { "Authorization": "Basic base64(user:pass)" }
```

**OAuth2:**
```
1. Obter authorization code
2. Trocar por access token
3. Usar access token
4. Refresh quando expirar
```

### 18.4 Códigos HTTP

- **200 OK**: Sucesso
- **201 Created**: Recurso criado
- **204 No Content**: Sucesso sem retorno
- **400 Bad Request**: Dados inválidos
- **401 Unauthorized**: Não autenticado
- **403 Forbidden**: Sem permissão
- **404 Not Found**: Recurso não encontrado
- **422 Unprocessable Entity**: Validação falhou
- **429 Too Many Requests**: Rate limit
- **500 Internal Server Error**: Erro no servidor
- **503 Service Unavailable**: Serviço indisponível

### 18.5 CRON Expressions

```
* * * * *
│ │ │ │ │
│ │ │ │ └─ Dia da semana (0-7, 0=domingo)
│ │ │ └─── Mês (1-12)
│ │ └───── Dia do mês (1-31)
│ └─────── Hora (0-23)
└───────── Minuto (0-59)
```

**Exemplos:**
```
*/5 * * * *          → A cada 5 minutos
0 * * * *            → A cada hora (no minuto 0)
0 0 * * *            → Todo dia à meia-noite
0 12 * * *           → Todo dia ao meio-dia
0 0 * * 0            → Todo domingo à meia-noite
0 9 * * 1-5          → Segunda a sexta às 9h
0 0 1 * *            → Todo dia 1 do mês à meia-noite
0 0 1 1 *            → Todo 1º de janeiro à meia-noite
*/15 9-17 * * 1-5    → A cada 15min, das 9h às 17h, seg-sex
```

### 18.6 Boas Práticas

✅ **Sempre usar HTTPS** para comunicação com CRM externo
✅ **Criptografar credenciais** antes de salvar no banco
✅ **Implementar retry** com exponential backoff para erros 5xx
✅ **Respeitar rate limits** do CRM externo
✅ **Logar todas as operações** para auditoria
✅ **Validar dados** antes de enviar para CRM
✅ **Usar sincronização incremental** quando possível
✅ **Monitorar performance** das sincronizações
✅ **Ter plano B** se CRM externo estiver offline
✅ **Documentar mapeamentos** customizados

❌ **Não expor credenciais** em logs ou respostas de API
❌ **Não fazer sync full** muito frequente
❌ **Não ignorar erros** silenciosamente
❌ **Não hardcodar** configurações de CRM
❌ **Não fazer requisições** desnecessárias

### 18.7 Checklist de Implementação

- [ ] Banco de dados criado (6 tabelas)
- [ ] Campo `external_id` adicionado nas entidades
- [ ] Providers configurados
- [ ] Entidades mapeadas
- [ ] Handlers implementados
- [ ] Transformadores criados
- [ ] Testes de conexão passando
- [ ] Sincronização manual funcionando
- [ ] CRON configurado
- [ ] Auditoria registrando
- [ ] ACL configurado
- [ ] Painel administrativo acessível
- [ ] Documentação atualizada
- [ ] Testes unitários criados
- [ ] Performance otimizada
- [ ] Monitoramento ativo

---

## 🎉 DOCUMENTAÇÃO COMPLETA!

Progresso: **100% CONCLUÍDO** ✅

Este documento contém TUDO sobre o módulo de Integração CRM:
- ✅ Visão geral e arquitetura
- ✅ Estrutura de diretórios completa
- ✅ Banco de dados (6 tabelas)
- ✅ Providers e configuração
- ✅ Entidades e handlers
- ✅ Sistema de mapeamento e transformação
- ✅ Fluxos completos de operação
- ✅ Configuração e sincronização
- ✅ Auditoria e rastreabilidade
- ✅ Painel administrativo
- ✅ Permissões (ACL)
- ✅ API completa
- ✅ Guias práticos
- ✅ Troubleshooting
- ✅ Referências

**Data de criação:** Janeiro 2025
**Versão:** 1.0.0
**Projeto:** Ecletech CRM

---

**Próximos Passos:**
1. Revisar esta documentação
2. Criar migrations do banco de dados
3. Implementar classes Core
4. Implementar primeiro provider (exemplo: GestaoClick)
5. Criar testes unitários
6. Implementar painel administrativo
7. Deploy e testes em produção
