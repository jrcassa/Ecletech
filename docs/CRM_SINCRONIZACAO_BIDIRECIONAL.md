# ↔️ SINCRONIZAÇÃO BIDIRECIONAL - CRM

**Como funciona a sincronização em ambas as direções**

---

## 📋 ÍNDICE

1. [Visão Geral](#1-visão-geral)
2. [Direção 1: Ecletech → CRM](#2-direção-1-ecletech--crm)
3. [Direção 2: CRM → Ecletech](#3-direção-2-crm--ecletech)
4. [Conflitos e Resolução](#4-conflitos-e-resolução)
5. [Estratégias de Sincronização](#5-estratégias-de-sincronização)
6. [Implementação Completa](#6-implementação-completa)
7. [Casos Práticos](#7-casos-práticos)

---

## 1. VISÃO GERAL

### 1.1 O que é Sincronização Bidirecional?

Dados fluem **nos dois sentidos**:

```
ECLETECH  ⟷  CRM EXTERNO
    ↓              ↓
    ↓              ↓
    ↓←── SYNC ────↓
    ↓──── SYNC ──→↓
```

**Cenários:**

1. **Usuário cria cliente no Ecletech** → Envia para CRM
2. **Usuário cria cliente no CRM** → Importa para Ecletech
3. **Usuário edita no Ecletech** → Atualiza CRM
4. **Usuário edita no CRM** → Atualiza Ecletech
5. **Editam nos dois ao mesmo tempo** → CONFLITO! 💥

### 1.2 Desafios

| Desafio | Descrição | Solução |
|---------|-----------|---------|
| **Duplicação** | Mesmo cliente criado nos 2 sistemas | Vínculo via `crm_entity_links` |
| **Conflitos** | Editado nos 2 lugares simultaneamente | Estratégias de resolução |
| **Performance** | Sincronizar 1000s de registros | Paginação + async |
| **Dados perdidos** | Campo existe em um sistema, não no outro | Mapeamento + fallback |
| **Rate limiting** | API externa limita requisições | Throttling + retry |

---

## 2. DIREÇÃO 1: ECLETECH → CRM

### 2.1 Quando Acontece

**Triggers:**

1. **Automático (Eventos):**
   ```php
   // Usuario cria/edita cliente no Ecletech
   ControllerCliente::criar() → Event: ClienteCriado → ServiceCrm::sincronizar()
   ```

2. **Manual (Botão):**
   ```php
   // Botão "Sincronizar agora" no painel
   POST /api/crm/sync/cliente/450 → ServiceCrm::sincronizar()
   ```

3. **Bulk (Script):**
   ```bash
   # Sincronizar todos os clientes
   php cli/crm-sync-to-external.php
   ```

### 2.2 Fluxo Completo

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. USUÁRIO CRIA CLIENTE NO ECLETECH                             │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. SALVA NO BANCO LOCAL                                         │
│    INSERT INTO clientes (nome, cpf, email) VALUES (...)         │
│    → ID gerado: 450                                             │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. VERIFICA INTEGRAÇÃO ATIVA                                    │
│    SELECT * FROM crm_integracoes WHERE id_loja=10 AND ativo=1   │
│    → Provider: gestao_click                                     │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. VERIFICA SE JÁ FOI SINCRONIZADO                              │
│    SELECT * FROM crm_entity_links                               │
│    WHERE entity_type='cliente' AND entity_id=450                │
│    → Não encontrado (cliente novo)                              │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. TRANSFORMA DADOS                                             │
│    Handler::transformarParaExterno()                            │
│    { nome: "João" } → { name: "João" }                          │
│    { cpf: "12345678900" } → { document: "123.456.789-00" }      │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. ENVIA PARA CRM EXTERNO                                       │
│    POST https://api.gestaoclick.com/v1/customers                │
│    Authorization: Bearer xyz123                                 │
│    { name: "João", document: "123.456.789-00", ... }            │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. CRM EXTERNO RESPONDE                                         │
│    { id: "gc_99999", name: "João", ... }                        │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. SALVA VÍNCULO                                                │
│    INSERT INTO crm_entity_links (                               │
│       entity_id = 450,                                          │
│       external_id = 'gc_99999'                                  │
│    )                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 9. LOG DE AUDITORIA                                             │
│    INSERT INTO crm_sync_log (                                   │
│       operacao = 'create',                                      │
│       status = 'sucesso'                                        │
│    )                                                            │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Código: Ecletech → CRM

```php
// ServiceCrm::sincronizarParaExterno()

public function sincronizarParaExterno(
    string $entityType,  // 'cliente'
    int $entityId,       // 450
    int $idLoja          // 10
): array {
    $inicio = microtime(true);

    try {
        // 1. Buscar integração
        $integracao = $this->modelIntegracao->buscarPorLoja($idLoja);

        if (!$integracao || !$integracao['ativo']) {
            return ['success' => false, 'message' => 'Integração não ativa'];
        }

        // 2. Obter provider
        $provider = $this->manager->obterProvider($integracao['provider']);

        // 3. Buscar dados locais
        $dadosLocais = $this->buscarDadosLocais($entityType, $entityId);

        // 4. Verificar se já existe vínculo
        $link = $this->modelLink->buscarPorEntidade(
            $idLoja,
            $integracao['provider'],
            $entityType,
            $entityId
        );

        if ($link) {
            // === ATUALIZAR (já existe no CRM) ===

            $resultado = $provider->atualizar(
                $entityType,
                $link['external_id'],  // gc_99999
                $dadosLocais,
                $idLoja
            );

            // Atualizar timestamp do vínculo
            $this->modelLink->atualizarTimestamp($link['id']);

            $operacao = 'update';

        } else {
            // === CRIAR (novo no CRM) ===

            $resultado = $provider->criar(
                $entityType,
                $dadosLocais,
                $idLoja
            );

            // Salvar vínculo
            $this->modelLink->criar([
                'id_loja' => $idLoja,
                'provider' => $integracao['provider'],
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'external_id' => $resultado['external_id']
            ]);

            $operacao = 'create';
        }

        // 5. Log sucesso
        $this->modelLog->criar([
            'id_integracao' => $integracao['id'],
            'id_loja' => $idLoja,
            'tipo' => 'manual',
            'operacao' => $operacao,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'external_id' => $resultado['external_id'] ?? $link['external_id'],
            'status' => 'sucesso',
            'duracao_ms' => (int)((microtime(true) - $inicio) * 1000)
        ]);

        return [
            'success' => true,
            'operacao' => $operacao,
            'external_id' => $resultado['external_id'] ?? $link['external_id']
        ];

    } catch (\Exception $e) {
        // Log erro
        $this->modelLog->criar([
            'id_integracao' => $integracao['id'] ?? null,
            'id_loja' => $idLoja,
            'tipo' => 'manual',
            'operacao' => $operacao ?? 'unknown',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'erro',
            'mensagem' => $e->getMessage(),
            'duracao_ms' => (int)((microtime(true) - $inicio) * 1000)
        ]);

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
```

---

## 3. DIREÇÃO 2: CRM → ECLETECH

### 3.1 Quando Acontece

**Triggers:**

1. **CRON (Periódico):**
   ```bash
   # A cada 10 minutos
   */10 * * * * php cli/crm-sync-from-external.php --entity=cliente
   ```

2. **Webhook (Tempo Real):**
   ```
   GestaoClick → POST /api/crm/webhook/gestao_click
   ```

3. **Manual (Botão):**
   ```php
   POST /api/crm/sync/import/cliente
   ```

### 3.2 Fluxo Completo (CRON)

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. CRON EXECUTA                                                 │
│    */10 * * * * php cli/crm-sync-from-external.php              │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. BUSCA PÁGINA 1 DO CRM EXTERNO                                │
│    GET https://api.gestaoclick.com/v1/customers?page=1&limit=100│
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. CRM RETORNA 100 CLIENTES                                     │
│    {                                                            │
│      data: [                                                    │
│        { id: "gc_12345", name: "Maria", ... },                  │
│        { id: "gc_12346", name: "Pedro", ... },                  │
│        ... (100 itens)                                          │
│      ],                                                         │
│      pagination: { total_pages: 9 }                             │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. PARA CADA CLIENTE RETORNADO                                  │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
         ┌───────────────┴───────────────┐
         ↓                               ↓
┌──────────────────────┐      ┌──────────────────────┐
│ Cliente gc_12345     │      │ Cliente gc_12346     │
└──────┬───────────────┘      └──────┬───────────────┘
       ↓                              ↓
┌──────────────────────────────────────────────────────────────────┐
│ 5. VERIFICA SE JÁ EXISTE VÍNCULO                                │
│    SELECT * FROM crm_entity_links                               │
│    WHERE provider='gestao_click'                                │
│      AND entity_type='cliente'                                  │
│      AND external_id='gc_12345'                                 │
└────────────────────────┬─────────────────────────────────────────┘
                         ↓
         ┌───────────────┴───────────────┐
         ↓                               ↓
    ┌─────────┐                    ┌─────────┐
    │ EXISTE? │                    │ NÃO?    │
    └────┬────┘                    └────┬────┘
         ↓                              ↓
┌─────────────────────┐      ┌──────────────────────┐
│ 6A. ATUALIZAR       │      │ 6B. CRIAR            │
│                     │      │                      │
│ UPDATE clientes     │      │ INSERT INTO clientes │
│ SET nome='Maria',   │      │ (nome, email, ...)   │
│     email='...'     │      │ VALUES (...)         │
│ WHERE id=450        │      │ → ID: 500            │
│                     │      │                      │
│ UPDATE              │      │ INSERT INTO          │
│ crm_entity_links    │      │ crm_entity_links     │
│ SET                 │      │ (entity_id=500,      │
│   sincronizado_em   │      │  external_id='...')  │
│   = NOW()           │      │                      │
└─────────────────────┘      └──────────────────────┘
```

### 3.3 Código: CRM → Ecletech

```php
// ServiceCrmSync::sincronizarDoExterno()

public function sincronizarDoExterno(
    int $idLoja,
    string $entityType,     // 'cliente'
    int $limite = 100
): array {
    $integracao = $this->modelIntegracao->buscarPorLoja($idLoja);

    if (!$integracao || !$integracao['ativo']) {
        return ['success' => false, 'message' => 'Integração não ativa'];
    }

    $provider = $this->manager->obterProvider($integracao['provider']);

    $pagina = 1;
    $totalProcessados = 0;
    $totalCriados = 0;
    $totalAtualizados = 0;
    $erros = [];

    // Loop paginado
    do {
        // 1. Buscar página do CRM
        $resultado = $provider->buscar($entityType, $pagina, $limite, $idLoja);

        /* $resultado = [
            'dados' => [
                [ 'external_id' => 'gc_12345', 'nome' => 'Maria', ... ],
                [ 'external_id' => 'gc_12346', 'nome' => 'Pedro', ... ],
                ...
            ],
            'total' => 850,
            'pagina_atual' => 1,
            'total_paginas' => 9
        ] */

        // 2. Processar cada item
        foreach ($resultado['dados'] as $itemExterno) {
            try {
                $acao = $this->processarItem(
                    $idLoja,
                    $integracao,
                    $entityType,
                    $itemExterno
                );

                $totalProcessados++;

                if ($acao === 'criado') {
                    $totalCriados++;
                } elseif ($acao === 'atualizado') {
                    $totalAtualizados++;
                }

            } catch (\Exception $e) {
                $erros[] = [
                    'external_id' => $itemExterno['external_id'] ?? 'unknown',
                    'erro' => $e->getMessage()
                ];
            }
        }

        $pagina++;

    } while ($pagina <= $resultado['total_paginas']);

    return [
        'success' => true,
        'total_processados' => $totalProcessados,
        'total_criados' => $totalCriados,
        'total_atualizados' => $totalAtualizados,
        'erros' => $erros
    ];
}

// Processa um item individual
private function processarItem(
    int $idLoja,
    array $integracao,
    string $entityType,
    array $itemExterno
): string {
    // 1. Verifica se já existe vínculo
    $link = $this->modelLink->buscarPorExternalId(
        $idLoja,
        $integracao['provider'],
        $entityType,
        $itemExterno['external_id']
    );

    if ($link) {
        // === JÁ EXISTE - ATUALIZAR ===

        // Buscar dados atuais do Ecletech
        $dadosLocaisAtuais = $this->buscarDadosLocais($entityType, $link['entity_id']);

        // Verificar se precisa atualizar (comparar timestamps)
        $deveAtualizar = $this->deveAtualizar(
            $dadosLocaisAtuais,
            $itemExterno
        );

        if ($deveAtualizar) {
            // Atualizar
            $this->atualizarEntidadeLocal(
                $entityType,
                $link['entity_id'],
                $itemExterno
            );

            // Atualizar timestamp do vínculo
            $this->modelLink->atualizarTimestamp($link['id']);

            return 'atualizado';
        }

        return 'ignorado';

    } else {
        // === NÃO EXISTE - CRIAR ===

        // Verificar se já existe por email/cpf (evitar duplicação)
        $existePorChaveUnica = $this->buscarPorChaveUnica(
            $entityType,
            $itemExterno
        );

        if ($existePorChaveUnica) {
            // Existe no Ecletech mas sem vínculo - criar apenas vínculo
            $this->modelLink->criar([
                'id_loja' => $idLoja,
                'provider' => $integracao['provider'],
                'entity_type' => $entityType,
                'entity_id' => $existePorChaveUnica['id'],
                'external_id' => $itemExterno['external_id']
            ]);

            return 'vinculado';
        }

        // Criar novo registro
        $novoId = $this->criarEntidadeLocal($entityType, $itemExterno, $idLoja);

        // Criar vínculo
        $this->modelLink->criar([
            'id_loja' => $idLoja,
            'provider' => $integracao['provider'],
            'entity_type' => $entityType,
            'entity_id' => $novoId,
            'external_id' => $itemExterno['external_id']
        ]);

        return 'criado';
    }
}

// Verifica se deve atualizar (compara timestamps)
private function deveAtualizar(array $dadosLocais, array $dadosExternos): bool
{
    $timestampLocal = strtotime($dadosLocais['modificado_em'] ?? $dadosLocais['cadastrado_em']);
    $timestampExterno = strtotime($dadosExternos['updated_at'] ?? $dadosExternos['created_at']);

    // Atualiza se CRM é mais recente
    return $timestampExterno > $timestampLocal;
}

// Busca por chave única (evita duplicação)
private function buscarPorChaveUnica(string $entityType, array $dados): ?array
{
    if ($entityType === 'cliente') {
        $modelCliente = new ModelCliente();

        // Tenta por email
        if (!empty($dados['email'])) {
            $existe = $modelCliente->buscarPorEmail($dados['email']);
            if ($existe) {
                return $existe;
            }
        }

        // Tenta por CPF/CNPJ
        if (!empty($dados['cpf'])) {
            $existe = $modelCliente->buscarPorCpf($dados['cpf']);
            if ($existe) {
                return $existe;
            }
        }

        if (!empty($dados['cnpj'])) {
            $existe = $modelCliente->buscarPorCnpj($dados['cnpj']);
            if ($existe) {
                return $existe;
            }
        }
    }

    return null;
}

// Cria entidade local
private function criarEntidadeLocal(
    string $entityType,
    array $dadosExternos,
    int $idLoja
): int {
    $models = [
        'cliente' => ModelCliente::class,
        'venda' => ModelVenda::class,
        'produto' => ModelProduto::class
    ];

    $model = new $models[$entityType]();

    return $model->criar([
        'id_loja' => $idLoja,
        ...$dadosExternos
    ]);
}

// Atualiza entidade local
private function atualizarEntidadeLocal(
    string $entityType,
    int $entityId,
    array $dadosExternos
): void {
    $models = [
        'cliente' => ModelCliente::class,
        'venda' => ModelVenda::class,
        'produto' => ModelProduto::class
    ];

    $model = new $models[$entityType]();
    $model->atualizar($entityId, $dadosExternos);
}
```

---

## 4. CONFLITOS E RESOLUÇÃO

### 4.1 Tipos de Conflitos

#### Conflito 1: Edição Simultânea

**Cenário:**
```
10:30 - Usuário edita cliente no Ecletech
        Nome: João Silva → João Silva Santos

10:35 - Outro usuário edita no GestaoClick
        Nome: João Silva → J. Silva

10:40 - CRON sincroniza CRM → Ecletech
        Qual nome usar? 🤔
```

**Tabela de estados:**

| Timestamp | Ecletech | GestaoClick | Ação |
|-----------|----------|-------------|------|
| 10:00 | João Silva | João Silva | Sincronizado ✅ |
| 10:30 | João Silva Santos | João Silva | Ecletech mais recente |
| 10:35 | João Silva Santos | J. Silva | Conflito! 💥 |
| 10:40 | ??? | J. Silva | Resolver conflito |

#### Conflito 2: Criação Duplicada

**Cenário:**
```
Cliente criado no Ecletech: joao@email.com (ID: 450)
Cliente criado no GestaoClick: joao@email.com (ID: gc_99999)
São a mesma pessoa!
```

#### Conflito 3: Deleção

**Cenário:**
```
Cliente deletado no Ecletech (soft delete)
Cliente ainda existe no GestaoClick
O que fazer?
```

### 4.2 Estratégias de Resolução

#### Estratégia 1: Last Write Wins (Última Escrita Vence)

**Regra:** O sistema com timestamp mais recente vence.

```php
private function resolverConflito(array $dadosLocal, array $dadosExterno): array
{
    $timestampLocal = strtotime($dadosLocal['modificado_em']);
    $timestampExterno = strtotime($dadosExterno['updated_at']);

    if ($timestampExterno > $timestampLocal) {
        // CRM é mais recente - usar dados do CRM
        return [
            'origem' => 'externo',
            'dados' => $dadosExterno,
            'acao' => 'atualizar_local'
        ];
    } else {
        // Ecletech é mais recente - usar dados do Ecletech
        return [
            'origem' => 'local',
            'dados' => $dadosLocal,
            'acao' => 'atualizar_externo'
        ];
    }
}
```

**Vantagens:**
- ✅ Simples de implementar
- ✅ Automático
- ✅ Sem intervenção do usuário

**Desvantagens:**
- ❌ Pode perder dados
- ❌ Usuário pode não perceber que dados foram sobrescritos

---

#### Estratégia 2: CRM Always Wins (CRM Sempre Vence)

**Regra:** CRM externo é a fonte da verdade.

```php
private function resolverConflito(array $dadosLocal, array $dadosExterno): array
{
    // CRM sempre vence
    return [
        'origem' => 'externo',
        'dados' => $dadosExterno,
        'acao' => 'atualizar_local'
    ];
}
```

**Usado quando:**
- CRM externo é sistema principal
- Ecletech é apenas visualização
- Vendedores trabalham no CRM externo

**Configuração:**
```php
// config.php do provider
'entidades' => [
    'cliente' => [
        'read_only' => false,
        'sync_strategy' => 'crm_wins'
    ],
    'produto' => [
        'read_only' => true,  // Nunca envia, só recebe
        'sync_strategy' => 'crm_wins'
    ]
]
```

---

#### Estratégia 3: Ecletech Always Wins (Ecletech Sempre Vence)

**Regra:** Ecletech é a fonte da verdade.

```php
private function resolverConflito(array $dadosLocal, array $dadosExterno): array
{
    // Ecletech sempre vence
    return [
        'origem' => 'local',
        'dados' => $dadosLocal,
        'acao' => 'atualizar_externo'
    ];
}
```

**Usado quando:**
- Ecletech é sistema principal
- CRM externo é backup/integração
- Usuários trabalham no Ecletech

---

#### Estratégia 4: Merge Inteligente (Mesclar Campos)

**Regra:** Mescla campos individualmente.

```php
private function resolverConflito(array $dadosLocal, array $dadosExterno): array
{
    $merged = [];

    // Nome: usar o mais completo
    $merged['nome'] = strlen($dadosLocal['nome']) > strlen($dadosExterno['nome'])
        ? $dadosLocal['nome']
        : $dadosExterno['nome'];

    // Email: sempre manter do Ecletech
    $merged['email'] = $dadosLocal['email'];

    // Telefone: usar o que tiver
    $merged['telefone'] = $dadosExterno['telefone'] ?: $dadosLocal['telefone'];

    // Endereço: usar do CRM se Ecletech não tiver
    $merged['endereco'] = $dadosLocal['endereco'] ?: $dadosExterno['endereco'];

    // Status: manter do Ecletech
    $merged['ativo'] = $dadosLocal['ativo'];

    return [
        'origem' => 'merged',
        'dados' => $merged,
        'acao' => 'atualizar_ambos'
    ];
}
```

**Vantagens:**
- ✅ Não perde dados
- ✅ Melhor experiência do usuário

**Desvantagens:**
- ❌ Complexo de implementar
- ❌ Precisa de regras por campo

---

#### Estratégia 5: Manual (Notificar Usuário)

**Regra:** Avisar usuário e deixar ele decidir.

```php
private function resolverConflito(array $dadosLocal, array $dadosExterno): array
{
    // Salvar conflito para resolução manual
    $this->modelConflitos->criar([
        'entity_type' => 'cliente',
        'entity_id' => $dadosLocal['id'],
        'external_id' => $dadosExterno['id'],
        'dados_local' => json_encode($dadosLocal),
        'dados_externo' => json_encode($dadosExterno),
        'status' => 'pendente'
    ]);

    // Não atualizar nada ainda
    return [
        'origem' => 'manual',
        'acao' => 'aguardar_resolucao'
    ];
}
```

**Interface:**
```
┌──────────────────────────────────────────────────┐
│ CONFLITO DETECTADO                               │
├──────────────────────────────────────────────────┤
│                                                  │
│ Cliente: João Silva (ID: 450)                    │
│                                                  │
│ Campo em conflito: Nome                          │
│                                                  │
│ ┌──────────────────┐  ┌──────────────────┐      │
│ │ ECLETECH         │  │ GESTAOCLICK      │      │
│ │ João Silva Santos│  │ J. Silva         │      │
│ │ 14/01 10:30      │  │ 14/01 10:35      │      │
│ └──────────────────┘  └──────────────────┘      │
│                                                  │
│ [ Usar Ecletech ] [ Usar GestaoClick ] [ Mesclar ]│
└──────────────────────────────────────────────────┘
```

---

### 4.3 Evitar Duplicação

**Matching por Chaves Únicas:**

```php
private function encontrarClienteExistente(array $dadosExterno): ?array
{
    $modelCliente = new ModelCliente();

    // 1. Verificar vínculo existente
    $link = $this->modelLink->buscarPorExternalId(
        $this->idLoja,
        'gestao_click',
        'cliente',
        $dadosExterno['external_id']
    );

    if ($link) {
        return $modelCliente->buscarPorId($link['entity_id']);
    }

    // 2. Matching por email
    if (!empty($dadosExterno['email'])) {
        $cliente = $modelCliente->buscarPorEmail($dadosExterno['email']);
        if ($cliente) {
            // Criar vínculo retroativo
            $this->criarVinculoRetroativo($cliente['id'], $dadosExterno['external_id']);
            return $cliente;
        }
    }

    // 3. Matching por CPF/CNPJ
    if (!empty($dadosExterno['cpf'])) {
        $cliente = $modelCliente->buscarPorCpf($dadosExterno['cpf']);
        if ($cliente) {
            $this->criarVinculoRetroativo($cliente['id'], $dadosExterno['external_id']);
            return $cliente;
        }
    }

    // 4. Matching fuzzy por nome (opcional)
    if (!empty($dadosExterno['nome'])) {
        $similar = $this->buscarNomeSimilar($dadosExterno['nome'], 0.85);  // 85% similaridade
        if ($similar) {
            // Avisar usuário para confirmar
            $this->notificarPossiveDuplicacao($similar, $dadosExterno);
        }
    }

    return null;
}

private function buscarNomeSimilar(string $nome, float $threshold): ?array
{
    $modelCliente = new ModelCliente();
    $todos = $modelCliente->buscarAtivos();

    foreach ($todos as $cliente) {
        $similaridade = similar_text(
            strtolower($nome),
            strtolower($cliente['nome'])
        );

        $porcentagem = $similaridade / max(strlen($nome), strlen($cliente['nome']));

        if ($porcentagem >= $threshold) {
            return $cliente;
        }
    }

    return null;
}
```

---

## 5. ESTRATÉGIAS DE SINCRONIZAÇÃO

### 5.1 Comparação de Estratégias

| Estratégia | Quando Usar | Prós | Contras |
|------------|-------------|------|---------|
| **Last Write Wins** | Poucos conflitos esperados | Simples, automático | Pode perder dados |
| **CRM Wins** | CRM é sistema principal | Consistência | Ecletech fica read-only |
| **Ecletech Wins** | Ecletech é sistema principal | Controle total | CRM fica desatualizado |
| **Merge** | Ambos são importantes | Não perde dados | Complexo |
| **Manual** | Dados críticos | Precisão | Trabalho manual |

### 5.2 Estratégia Híbrida (Recomendada)

Combinar múltiplas estratégias por campo:

```php
// config.php
'sync_config' => [
    'estrategia_padrao' => 'last_write_wins',

    'campos_especiais' => [
        // Email: Ecletech sempre vence
        'email' => [
            'estrategia' => 'ecletech_wins',
            'justificativa' => 'Email é validado no Ecletech'
        ],

        // Telefone: Merge (usar o que estiver preenchido)
        'telefone' => [
            'estrategia' => 'merge',
            'regra' => 'usar_nao_vazio'
        ],

        // Nome: Manual se muito diferente
        'nome' => [
            'estrategia' => 'manual_se_diferente',
            'threshold' => 0.5  // Se <50% similar, avisar usuário
        ],

        // Status: Ecletech vence
        'ativo' => [
            'estrategia' => 'ecletech_wins'
        ],

        // Produtos: CRM vence (read-only)
        'produtos' => [
            'estrategia' => 'crm_wins',
            'read_only' => true
        ]
    ]
]
```

Implementação:

```php
private function resolverConflitoPorCampo(
    string $campo,
    mixed $valorLocal,
    mixed $valorExterno,
    array $metadados
): mixed {
    $config = $this->obterConfigCampo($campo);

    switch ($config['estrategia']) {
        case 'ecletech_wins':
            return $valorLocal;

        case 'crm_wins':
            return $valorExterno;

        case 'merge':
            if ($config['regra'] === 'usar_nao_vazio') {
                return $valorExterno ?: $valorLocal;
            }
            break;

        case 'manual_se_diferente':
            $similaridade = similar_text($valorLocal, $valorExterno) /
                           max(strlen($valorLocal), strlen($valorExterno));

            if ($similaridade < $config['threshold']) {
                // Marcar para resolução manual
                $this->marcarCampoParaRevisao($campo, $valorLocal, $valorExterno);
                return $valorLocal;  // Manter valor atual por enquanto
            }

            return $this->resolverLastWriteWins($metadados)
                ? $valorExterno
                : $valorLocal;

        case 'last_write_wins':
        default:
            return $this->resolverLastWriteWins($metadados)
                ? $valorExterno
                : $valorLocal;
    }
}
```

---

## 6. IMPLEMENTAÇÃO COMPLETA

### 6.1 Fluxo Bidirecional Completo

```php
// ServiceCrmBidirecional.php

class ServiceCrmBidirecional
{
    /**
     * Sincronização bidirecional inteligente
     */
    public function sincronizarBidirecional(
        int $idLoja,
        string $entityType,
        ?int $entityId = null  // null = todos
    ): array {
        $resultado = [
            'para_crm' => [],
            'do_crm' => [],
            'conflitos' => []
        ];

        // 1. Sincronizar Ecletech → CRM
        if ($entityId) {
            // Item específico
            $resultado['para_crm'] = $this->serviceCrm->sincronizarParaExterno(
                $entityType,
                $entityId,
                $idLoja
            );
        } else {
            // Todos os itens modificados recentemente
            $resultado['para_crm'] = $this->sincronizarTodosParaCrm(
                $idLoja,
                $entityType
            );
        }

        // 2. Sincronizar CRM → Ecletech
        $resultado['do_crm'] = $this->serviceCrmSync->sincronizarDoExterno(
            $idLoja,
            $entityType
        );

        // 3. Resolver conflitos
        $resultado['conflitos'] = $this->resolverConflitos($idLoja, $entityType);

        return $resultado;
    }

    /**
     * Sincroniza apenas itens modificados nas últimas X horas
     */
    private function sincronizarTodosParaCrm(
        int $idLoja,
        string $entityType,
        int $horasAtras = 24
    ): array {
        $model = $this->obterModel($entityType);

        // Buscar itens modificados recentemente
        $itens = $model->buscarModificadosApos(
            date('Y-m-d H:i:s', strtotime("-{$horasAtras} hours"))
        );

        $sucessos = 0;
        $erros = 0;

        foreach ($itens as $item) {
            try {
                $this->serviceCrm->sincronizarParaExterno(
                    $entityType,
                    $item['id'],
                    $idLoja
                );
                $sucessos++;
            } catch (\Exception $e) {
                $erros++;
            }
        }

        return [
            'total' => count($itens),
            'sucessos' => $sucessos,
            'erros' => $erros
        ];
    }

    /**
     * Resolve conflitos pendentes
     */
    private function resolverConflitos(int $idLoja, string $entityType): array
    {
        $conflitos = $this->modelConflitos->buscarPendentes($idLoja, $entityType);

        $resolvidos = 0;

        foreach ($conflitos as $conflito) {
            $resolucao = $this->resolverConflito(
                json_decode($conflito['dados_local'], true),
                json_decode($conflito['dados_externo'], true)
            );

            if ($resolucao['acao'] !== 'aguardar_resolucao') {
                // Aplicar resolução
                $this->aplicarResolucao($conflito, $resolucao);
                $resolvidos++;
            }
        }

        return [
            'total_conflitos' => count($conflitos),
            'resolvidos' => $resolvidos,
            'pendentes' => count($conflitos) - $resolvidos
        ];
    }
}
```

---

## 7. CASOS PRÁTICOS

### 7.1 Caso 1: Cliente Criado no Ecletech

**Timeline:**

```
10:00 - Usuário cria cliente no Ecletech
        POST /api/clientes
        { nome: "Maria Santos", email: "maria@email.com" }

10:00:01 - Salvo no banco
           INSERT INTO clientes → ID: 500

10:00:02 - Sincroniza para GestaoClick
           POST /v1/customers
           Response: { id: "gc_55555" }

10:00:03 - Vínculo criado
           INSERT INTO crm_entity_links
           (entity_id=500, external_id='gc_55555')

Resultado: Cliente existe nos 2 sistemas ✅
```

---

### 7.2 Caso 2: Cliente Criado no GestaoClick

**Timeline:**

```
10:10 - Usuário cria cliente no GestaoClick
        (via painel do GestaoClick)
        { id: "gc_66666", name: "Pedro Oliveira" }

10:15 - CRON executa (5 min depois)
        php cli/crm-sync-from-external.php

10:15:10 - Busca clientes do GestaoClick
           GET /v1/customers?page=1
           → Retorna gc_66666

10:15:11 - Verifica se existe vínculo
           SELECT * FROM crm_entity_links
           WHERE external_id='gc_66666'
           → Não encontrado

10:15:12 - Cria cliente no Ecletech
           INSERT INTO clientes → ID: 501

10:15:13 - Cria vínculo
           INSERT INTO crm_entity_links
           (entity_id=501, external_id='gc_66666')

Resultado: Cliente importado ✅
```

---

### 7.3 Caso 3: Edição Simultânea (Conflito)

**Timeline:**

```
Inicial: Cliente sincronizado
  Ecletech: { id: 450, nome: "João Silva" }
  GestaoClick: { id: "gc_99999", name: "João Silva" }

10:30 - Usuário A edita no Ecletech
        PUT /api/clientes/450
        { nome: "João Silva Santos" }

10:30:01 - Salvo no banco
           UPDATE clientes SET nome='João Silva Santos'
           WHERE id=450

10:30:02 - Sincroniza para GestaoClick
           PUT /v1/customers/gc_99999
           { name: "João Silva Santos" }

10:35 - Usuário B edita no GestaoClick (não sabe da edição anterior)
        (via painel GestaoClick)
        { id: "gc_99999", name: "J. Silva" }

10:40 - CRON executa
        php cli/crm-sync-from-external.php

10:40:10 - Busca clientes do GestaoClick
           → Retorna { id: "gc_99999", name: "J. Silva" }

10:40:11 - Compara timestamps
           Ecletech modificado_em: 2025-01-14 10:30:02
           GestaoClick updated_at: 2025-01-14 10:35:00

10:40:12 - Resolve conflito (Last Write Wins)
           GestaoClick mais recente → Atualiza Ecletech

10:40:13 - Atualiza Ecletech
           UPDATE clientes SET nome='J. Silva'
           WHERE id=450

Resultado: Nome final = "J. Silva" (GestaoClick venceu) ⚠️
```

**Perda de dados:** "João Silva Santos" foi perdido!

**Solução:** Usar estratégia Manual para campo nome:

```
10:40:12 - Detecta conflito no campo "nome"
           Diferença > 50% → Marcar para revisão manual

10:40:13 - Não atualiza, mantém "João Silva Santos"

10:40:14 - Notifica usuário:
           "Conflito detectado no cliente #450 - campo nome"

Usuário decide manualmente qual usar ✅
```

---

### 7.4 Caso 4: Evitar Duplicação

**Cenário:**

```
Ecletech: { id: 450, email: "joao@email.com", nome: "João Silva" }
GestaoClick: { id: "gc_99999", email: "joao@email.com", name: "João S." }

São a mesma pessoa! Mas sem vínculo.
```

**Solução:**

```php
// Durante sincronização CRM → Ecletech

10:00 - CRON busca clientes do GestaoClick
        → Retorna { id: "gc_99999", email: "joao@email.com" }

10:00:01 - Verifica vínculo
           SELECT * FROM crm_entity_links
           WHERE external_id='gc_99999'
           → Não encontrado

10:00:02 - Busca por email (matching)
           SELECT * FROM clientes
           WHERE email='joao@email.com'
           → Encontrado! ID: 450

10:00:03 - Cria vínculo retroativo
           INSERT INTO crm_entity_links
           (entity_id=450, external_id='gc_99999')

10:00:04 - Atualiza dados se necessário
           (merge ou last write wins)

Resultado: Vínculo criado sem duplicar ✅
```

---

## 8. RESUMO - CHECKLIST

### ✅ Ecletech → CRM

- [ ] Buscar dados do Ecletech
- [ ] Verificar se já tem vínculo (busca em `crm_entity_links`)
- [ ] Se tem vínculo: **ATUALIZAR** (PUT)
- [ ] Se não tem: **CRIAR** (POST)
- [ ] Salvar/atualizar vínculo
- [ ] Registrar log

### ✅ CRM → Ecletech

- [ ] Buscar dados do CRM (paginado)
- [ ] Para cada item:
  - [ ] Verificar se já tem vínculo
  - [ ] Se tem: **ATUALIZAR** Ecletech
  - [ ] Se não tem:
    - [ ] Buscar por chave única (email, CPF)
    - [ ] Se encontrar: criar vínculo retroativo
    - [ ] Se não encontrar: **CRIAR** novo
- [ ] Atualizar timestamps
- [ ] Registrar logs

### ✅ Resolução de Conflitos

- [ ] Definir estratégia (Last Write Wins, CRM Wins, etc)
- [ ] Comparar timestamps
- [ ] Aplicar regras por campo (se híbrida)
- [ ] Marcar para revisão manual (se necessário)
- [ ] Notificar usuário (se manual)

---

**Documento:** CRM_SINCRONIZACAO_BIDIRECIONAL.md
**Versão:** 1.0
**Data:** Janeiro 2025
