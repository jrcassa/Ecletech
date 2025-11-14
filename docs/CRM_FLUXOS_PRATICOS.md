# 🔄 FLUXOS PRÁTICOS - INTEGRAÇÃO CRM

**Como sincronizar dados entre Ecletech e CRM externo**

---

## 📋 ÍNDICE

1. [Fluxos Principais](#1-fluxos-principais)
2. [Ecletech → CRM (Enviar)](#2-ecletech--crm-enviar)
3. [CRM → Ecletech (Receber)](#3-crm--ecletech-receber)
4. [Webhooks em Tempo Real](#4-webhooks-em-tempo-real)
5. [Sincronização Inicial](#5-sincronização-inicial)
6. [Resolução de Conflitos](#6-resolução-de-conflitos)
7. [Exemplos Completos](#7-exemplos-completos)

---

## 1. FLUXOS PRINCIPAIS

Existem **3 formas** de sincronizar dados:

### 1.1 Fluxo Automático (Recomendado)

```
ECLETECH                           CRM EXTERNO
   │                                    │
   │  Cliente cadastrado/editado        │
   │  ↓                                 │
   │  Event: CustomerSaved              │
   │  ↓                                 │
   │  ServiceCrm::sincronizarParaExterno()
   │  ↓                                 │
   │  ────────── POST /customers ─────→ │
   │                                    │ Cliente criado
   │  ←──────── external_id=123 ────── │
   │                                    │
   │  Salva em crm_entity_links         │
   │  (entity_id=450, external_id=123)  │
   │                                    │
```

### 1.2 Fluxo Manual

```
PAINEL ADMIN                      ECLETECH                CRM EXTERNO
     │                               │                         │
     │  Botão "Sincronizar Agora"    │                         │
     │  ─────────────────────────→   │                         │
     │                               │                         │
     │                               │  ServiceCrm::sincronizar()
     │                               │  ──────────────────────→ │
     │                               │                         │
     │                               │  ←───── Sincronizado ── │
     │  ←─── Resultado ────────────  │                         │
```

### 1.3 Fluxo por Webhook (Tempo Real)

```
CRM EXTERNO                       ECLETECH
     │                               │
     │  Cliente alterado no CRM      │
     │  ↓                            │
     │  POST /webhook/gestao_click ─→│
     │                               │ ControllerCrmWebhook::receber()
     │                               │ ↓
     │                               │ ServiceCrm::processarWebhook()
     │                               │ ↓
     │                               │ Atualiza cliente local
     │                               │
     │  ←──────── 200 OK ─────────── │
```

---

## 2. ECLETECH → CRM (ENVIAR)

### 2.1 Cenário: Usuário Cadastra Cliente no Ecletech

**Fluxo:**

```php
// 1. Usuário cadastra cliente na interface web
// POST /api/clientes

// 2. ControllerCliente::criar()
public function criar(Requisicao $req): Resposta
{
    $dados = $req->obterCorpo();

    // Valida dados
    $this->validar($dados);

    // Salva no banco LOCAL
    $modelCliente = new ModelCliente();
    $idCliente = $modelCliente->criar($dados);

    // 🔥 AQUI ENTRA A INTEGRAÇÃO CRM
    $this->sincronizarComCrm($idCliente, $req->obterIdLoja());

    return Resposta::json([
        'success' => true,
        'id' => $idCliente
    ]);
}

// 3. Método de sincronização
private function sincronizarComCrm(int $idCliente, int $idLoja): void
{
    try {
        $serviceCrm = new ServiceCrm();

        // Tenta sincronizar
        $resultado = $serviceCrm->sincronizarParaExterno(
            'cliente',
            $idCliente,
            $idLoja
        );

        // Se der erro, apenas loga (não bloqueia cadastro)
        if (!$resultado['success']) {
            error_log("Erro ao sincronizar cliente #{$idCliente} com CRM: " .
                      $resultado['message']);
        }

    } catch (\Exception $e) {
        // Não lança exceção - integração CRM não deve quebrar fluxo principal
        error_log("Exceção ao sincronizar com CRM: " . $e->getMessage());
    }
}
```

**O que acontece internamente:**

```php
// ServiceCrm::sincronizarParaExterno()

public function sincronizarParaExterno(
    string $entityType,     // 'cliente'
    int $entityId,          // 450
    int $idLoja             // 10
): array {

    // 1. Verifica se tem integração ativa
    $integracao = $this->modelIntegracao->buscarPorLoja($idLoja);

    if (!$integracao || !$integracao['ativo']) {
        return ['success' => false, 'message' => 'Integração não ativa'];
    }

    // 2. Obtém provider configurado (ex: GestaoClick)
    $provider = $this->manager->obterProvider($integracao['provider']);
    // $provider agora é uma instância de GestaoClickProvider

    // 3. Busca dados do cliente no banco LOCAL
    $modelCliente = new ModelCliente();
    $cliente = $modelCliente->buscarPorId($entityId);

    /* $cliente = [
        'id' => 450,
        'nome' => 'João Silva',
        'email' => 'joao@email.com',
        'telefone' => '11999998888',
        'cpf_cnpj' => '123.456.789-00',
        ...
    ] */

    // 4. Verifica se JÁ existe vínculo (cliente já foi sincronizado antes?)
    $link = $this->modelLink->buscarPorEntidade(
        $idLoja,
        $integracao['provider'],
        $entityType,
        $entityId
    );

    if ($link) {
        // ===== CLIENTE JÁ EXISTE NO CRM - ATUALIZAR =====

        $resultado = $provider->atualizar(
            'cliente',
            $link['external_id'],  // ex: 'gc_12345'
            $cliente,
            $idLoja
        );

        /* O que acontece dentro do provider:

        1. ClienteHandler transforma dados:
           Local → Externo

           ['nome' => 'João Silva'] → ['name' => 'João Silva']
           ['telefone' => '11999998888'] → ['phone' => '(11) 99999-8888']

        2. Faz requisição HTTP:
           PUT https://api.gestaoclick.com/v1/customers/gc_12345
           {
               "name": "João Silva",
               "phone": "(11) 99999-8888",
               ...
           }

        3. Response:
           {
               "id": "gc_12345",
               "name": "João Silva",
               "updated_at": "2025-01-14T10:30:00Z"
           }
        */

        // Log de auditoria
        $this->modelLog->criar([
            'tipo' => 'manual',
            'operacao' => 'update',
            'entity_type' => 'cliente',
            'entity_id' => $entityId,
            'external_id' => $link['external_id'],
            'status' => 'sucesso'
        ]);

    } else {
        // ===== CLIENTE NOVO - CRIAR NO CRM =====

        $resultado = $provider->criar(
            'cliente',
            $cliente,
            $idLoja
        );

        /* Response do CRM:
        [
            'external_id' => 'gc_99999',  // ID gerado pelo GestaoClick
            'dados' => [...]
        ]
        */

        // Salva vínculo entre ID local e ID externo
        $this->modelLink->criar([
            'id_loja' => $idLoja,
            'provider' => $integracao['provider'],
            'entity_type' => 'cliente',
            'entity_id' => $entityId,           // 450 (Ecletech)
            'external_id' => $resultado['external_id']  // gc_99999 (GestaoClick)
        ]);

        // Log
        $this->modelLog->criar([
            'tipo' => 'manual',
            'operacao' => 'create',
            'entity_type' => 'cliente',
            'entity_id' => $entityId,
            'external_id' => $resultado['external_id'],
            'status' => 'sucesso'
        ]);
    }

    return ['success' => true, 'data' => $resultado];
}
```

### 2.2 Tabela de Vínculos (Essencial!)

Depois da sincronização, a tabela `crm_entity_links` fica assim:

```sql
SELECT * FROM crm_entity_links WHERE entity_type = 'cliente' AND entity_id = 450;
```

| id | id_loja | provider | entity_type | entity_id | external_id | sincronizado_em |
|----|---------|----------|-------------|-----------|-------------|-----------------|
| 1  | 10      | gestao_click | cliente | 450 | gc_99999 | 2025-01-14 10:30:00 |

**Esta linha significa:**
- Cliente #450 do Ecletech (loja 10)
- Está vinculado ao cliente `gc_99999` do GestaoClick
- Sincronizado em 14/01/2025 às 10:30

Agora, quando houver **qualquer atualização** no cliente 450, o sistema:
1. Consulta `crm_entity_links`
2. Descobre que existe `external_id = gc_99999`
3. Faz `PUT /customers/gc_99999` (atualização, não criação)

---

## 3. CRM → ECLETECH (RECEBER)

### 3.1 Sincronização Paginada (CRON)

**Cenário:** Buscar todos os clientes do GestaoClick e atualizar Ecletech

```bash
# Crontab - a cada 10 minutos
*/10 * * * * php /var/www/ecletech/cli/crm-sync.php --entity=cliente
```

**Script CLI:**

```php
// cli/crm-sync.php

require __DIR__ . '/../bootstrap.php';

use App\CRM\Services\ServiceCrmSync;

$entity = 'cliente'; // do argumento --entity=cliente

// Busca lojas com integração ativa
$db = BancoDados::obterInstancia();
$lojas = $db->buscarTodos(
    "SELECT id FROM lojas
     WHERE id IN (SELECT id_loja FROM crm_integracoes WHERE ativo = 1)"
);

$service = new ServiceCrmSync();

foreach ($lojas as $loja) {
    echo "Sincronizando clientes para loja {$loja['id']}...\n";

    $resultado = $service->sincronizarDoExterno(
        $loja['id'],
        'cliente',
        100  // 100 registros por página
    );

    echo "✓ Processados: {$resultado['total_processados']}\n";
}
```

**Fluxo interno:**

```php
// ServiceCrmSync::sincronizarDoExterno()

public function sincronizarDoExterno(
    int $idLoja,
    string $entityType,
    int $limite = 100
): array {

    $integracao = $this->modelIntegracao->buscarPorLoja($idLoja);
    $provider = $this->manager->obterProvider($integracao['provider']);

    $pagina = 1;
    $totalProcessados = 0;

    do {
        // 1. Busca página do CRM externo
        $resultado = $provider->buscar($entityType, $pagina, $limite, $idLoja);

        /* Response:
        [
            'dados' => [
                [
                    'external_id' => 'gc_12345',
                    'nome' => 'Maria Santos',
                    'email' => 'maria@email.com',
                    ...
                ],
                [
                    'external_id' => 'gc_12346',
                    'nome' => 'Pedro Oliveira',
                    ...
                ],
                ... (100 registros)
            ],
            'total' => 850,
            'pagina_atual' => 1,
            'total_paginas' => 9
        ]
        */

        // 2. Processa cada cliente retornado
        foreach ($resultado['dados'] as $clienteExterno) {
            $this->processarItem($idLoja, $integracao, $entityType, $clienteExterno);
            $totalProcessados++;
        }

        $pagina++;

    } while ($pagina <= $resultado['total_paginas']);

    return ['total_processados' => $totalProcessados];
}

// Processa um cliente individual
private function processarItem(
    int $idLoja,
    array $integracao,
    string $entityType,
    array $clienteExterno
): void {

    /* $clienteExterno = [
        'external_id' => 'gc_12345',
        'nome' => 'Maria Santos',
        'email' => 'maria@email.com',
        'telefone' => '11988887777',
        ...
    ] */

    // 1. Verifica se já existe vínculo
    $link = $this->modelLink->buscarPorExternalId(
        $idLoja,
        $integracao['provider'],
        $entityType,
        $clienteExterno['external_id']  // gc_12345
    );

    if ($link) {
        // ===== CLIENTE JÁ EXISTE NO ECLETECH - ATUALIZAR =====

        $modelCliente = new ModelCliente();
        $modelCliente->atualizar($link['entity_id'], $clienteExterno);

        /* Atualiza cliente #450 com dados vindos do GestaoClick */

        // Atualiza timestamp do vínculo
        $this->modelLink->atualizarTimestamp($link['id']);

    } else {
        // ===== CLIENTE NOVO (existe no CRM mas não no Ecletech) - CRIAR =====

        $modelCliente = new ModelCliente();
        $novoId = $modelCliente->criar([
            'id_loja' => $idLoja,
            'nome' => $clienteExterno['nome'],
            'email' => $clienteExterno['email'],
            'telefone' => $clienteExterno['telefone'],
            // ... outros campos
        ]);

        // Cria vínculo
        $this->modelLink->criar([
            'id_loja' => $idLoja,
            'provider' => $integracao['provider'],
            'entity_type' => $entityType,
            'entity_id' => $novoId,                      // 500 (novo ID no Ecletech)
            'external_id' => $clienteExterno['external_id']  // gc_12345
        ]);
    }
}
```

### 3.2 Resultado da Sincronização

Após executar, a tabela `crm_entity_links` tem todos os vínculos:

```sql
SELECT * FROM crm_entity_links WHERE entity_type = 'cliente' LIMIT 5;
```

| entity_id (Ecletech) | external_id (GestaoClick) | sincronizado_em |
|----------------------|---------------------------|-----------------|
| 450 | gc_12345 | 2025-01-14 10:30 |
| 451 | gc_12346 | 2025-01-14 10:30 |
| 452 | gc_12347 | 2025-01-14 10:30 |
| 500 | gc_99999 | 2025-01-14 10:30 |
| ... | ... | ... |

---

## 4. WEBHOOKS EM TEMPO REAL

### 4.1 Configuração no CRM Externo

No painel do GestaoClick:

```
Webhooks > Adicionar novo
URL: https://seu-ecletech.com/api/crm/webhook/gestao_click
Eventos: customer.created, customer.updated, customer.deleted
```

### 4.2 Recebimento do Webhook

```php
// ControllerCrmWebhook::receber()

/**
 * POST /api/crm/webhook/gestao_click
 */
public function receber(Requisicao $req, string $provider): Resposta
{
    $payload = $req->obterCorpo();

    /* Exemplo de payload do GestaoClick:
    {
        "event": "customer.updated",
        "data": {
            "id": "gc_12345",
            "name": "Maria Santos Silva",   // Nome mudou!
            "email": "maria@email.com",
            "phone": "(11) 98888-7777",
            "updated_at": "2025-01-14T15:45:00Z"
        }
    }
    */

    // Valida assinatura (segurança)
    $signature = $req->obterCabecalho('X-Webhook-Signature');
    if (!$this->validarAssinatura($provider, $payload, $signature)) {
        return Resposta::json(['erro' => 'Assinatura inválida'], 403);
    }

    try {
        // Processa webhook
        $resultado = $this->processarWebhook($provider, $payload);

        return Resposta::json(['success' => true]);

    } catch (\Exception $e) {
        return Resposta::json(['erro' => $e->getMessage()], 500);
    }
}

private function processarWebhook(string $provider, array $payload): void
{
    $evento = $payload['event'];
    $dados = $payload['data'];

    // Determina tipo de entidade
    if (str_contains($evento, 'customer')) {
        $entityType = 'cliente';
    } elseif (str_contains($evento, 'deal')) {
        $entityType = 'venda';
    } else {
        throw new \Exception("Evento desconhecido: {$evento}");
    }

    // Busca vínculo
    $modelLink = new ModelCrmEntityLink();
    $link = $modelLink->buscarPorExternalId(
        null,  // busca em todas as lojas
        $provider,
        $entityType,
        $dados['id']  // gc_12345
    );

    if (!$link) {
        // Cliente existe no CRM mas não no Ecletech - criar
        $this->criarEntidadeLocal($entityType, $dados);
        return;
    }

    // Atualiza dados locais
    if (str_contains($evento, 'updated')) {
        $this->atualizarEntidadeLocal($entityType, $link['entity_id'], $dados);
    }

    if (str_contains($evento, 'deleted')) {
        $this->marcarComoInativo($entityType, $link['entity_id']);
    }
}

private function atualizarEntidadeLocal(string $entityType, int $entityId, array $dados): void
{
    $modelCliente = new ModelCliente();

    $modelCliente->atualizar($entityId, [
        'nome' => $dados['name'],
        'email' => $dados['email'],
        'telefone' => $this->limparTelefone($dados['phone'])
    ]);

    // Log
    $modelLog = new ModelCrmSyncLog();
    $modelLog->criar([
        'tipo' => 'webhook',
        'operacao' => 'update',
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'external_id' => $dados['id'],
        'status' => 'sucesso',
        'mensagem' => 'Atualizado via webhook'
    ]);
}
```

### 4.3 Fluxo Completo do Webhook

```
GESTAOCLICK                              ECLETECH
     │                                       │
     │  1. Usuário edita cliente no CRM     │
     │     (Maria Santos → Maria Santos Silva)
     │                                       │
     │  2. Webhook disparado                │
     │     POST /webhook/gestao_click ─────→│
     │     {                                 │
     │       "event": "customer.updated",    │
     │       "data": {                       │
     │         "id": "gc_12345",             │ 3. Busca vínculo:
     │         "name": "Maria Santos Silva"  │    external_id = gc_12345
     │       }                               │    → entity_id = 450
     │     }                                 │
     │                                       │ 4. Atualiza cliente #450:
     │                                       │    UPDATE clientes
     │                                       │    SET nome = 'Maria Santos Silva'
     │                                       │    WHERE id = 450
     │                                       │
     │  ←────────── 200 OK ──────────────── │ 5. Retorna sucesso
```

**Vantagem:** Atualização em **tempo real** sem esperar CRON!

---

## 5. SINCRONIZAÇÃO INICIAL

### 5.1 Cenário: Você já tem 1000 clientes no Ecletech

**Pergunta:** Como sincronizar todos de uma vez?

**Resposta:** Script de sincronização em lote

```php
// cli/crm-sync-bulk.php

require __DIR__ . '/../bootstrap.php';

use App\CRM\Services\ServiceCrm;

$idLoja = 10;
$serviceCrm = new ServiceCrm();
$modelCliente = new ModelCliente();

// Busca todos os clientes da loja
$clientes = $modelCliente->buscarPorLoja($idLoja);

echo "Total de clientes: " . count($clientes) . "\n";

$sucesso = 0;
$erros = 0;

foreach ($clientes as $cliente) {
    echo "Sincronizando cliente #{$cliente['id']} - {$cliente['nome']}... ";

    try {
        $resultado = $serviceCrm->sincronizarParaExterno(
            'cliente',
            $cliente['id'],
            $idLoja
        );

        if ($resultado['success']) {
            echo "✓\n";
            $sucesso++;
        } else {
            echo "✗ {$resultado['message']}\n";
            $erros++;
        }

        // Delay para não estourar rate limit da API
        usleep(200000); // 200ms entre requisições

    } catch (\Exception $e) {
        echo "✗ Erro: {$e->getMessage()}\n";
        $erros++;
    }
}

echo "\n";
echo "Concluído!\n";
echo "Sucessos: {$sucesso}\n";
echo "Erros: {$erros}\n";
```

**Execução:**

```bash
$ php cli/crm-sync-bulk.php

Total de clientes: 1000
Sincronizando cliente #1 - João Silva... ✓
Sincronizando cliente #2 - Maria Santos... ✓
Sincronizando cliente #3 - Pedro Oliveira... ✓
...
Sincronizando cliente #1000 - Ana Costa... ✓

Concluído!
Sucessos: 998
Erros: 2
```

### 5.2 Sincronização Bidirecional Inicial

**Cenário:** Você tem clientes no Ecletech E no GestaoClick

**Estratégia:** Usar email como chave de matching

```php
// cli/crm-sync-bidirectional.php

$serviceCrmSync = new ServiceCrmSync();

// 1. Busca todos do CRM externo
$clientesExternos = [];
$pagina = 1;
do {
    $resultado = $provider->buscar('cliente', $pagina, 100, $idLoja);
    $clientesExternos = array_merge($clientesExternos, $resultado['dados']);
    $pagina++;
} while ($pagina <= $resultado['total_paginas']);

echo "Clientes no GestaoClick: " . count($clientesExternos) . "\n";

// 2. Busca todos do Ecletech
$modelCliente = new ModelCliente();
$clientesLocais = $modelCliente->buscarPorLoja($idLoja);

echo "Clientes no Ecletech: " . count($clientesLocais) . "\n";

// 3. Matching por email
foreach ($clientesLocais as $clienteLocal) {

    // Procura cliente externo com mesmo email
    $clienteExterno = array_filter($clientesExternos, function($ext) use ($clienteLocal) {
        return strtolower($ext['email']) === strtolower($clienteLocal['email']);
    });

    if (!empty($clienteExterno)) {
        $clienteExterno = reset($clienteExterno);

        // JÁ EXISTE NOS DOIS - Criar vínculo
        $modelLink->criar([
            'id_loja' => $idLoja,
            'provider' => 'gestao_click',
            'entity_type' => 'cliente',
            'entity_id' => $clienteLocal['id'],
            'external_id' => $clienteExterno['external_id']
        ]);

        echo "✓ Vínculo criado: {$clienteLocal['nome']} ↔ {$clienteExterno['external_id']}\n";

    } else {
        // Existe apenas no Ecletech - Enviar para CRM
        $serviceCrm->sincronizarParaExterno('cliente', $clienteLocal['id'], $idLoja);

        echo "→ Enviado para CRM: {$clienteLocal['nome']}\n";
    }
}

// 4. Clientes que existem apenas no CRM
foreach ($clientesExternos as $clienteExterno) {

    $existe = $modelLink->buscarPorExternalId(
        $idLoja,
        'gestao_click',
        'cliente',
        $clienteExterno['external_id']
    );

    if (!$existe) {
        // Criar no Ecletech
        $novoId = $modelCliente->criar([
            'id_loja' => $idLoja,
            'nome' => $clienteExterno['nome'],
            'email' => $clienteExterno['email'],
            ...
        ]);

        $modelLink->criar([
            'id_loja' => $idLoja,
            'provider' => 'gestao_click',
            'entity_type' => 'cliente',
            'entity_id' => $novoId,
            'external_id' => $clienteExterno['external_id']
        ]);

        echo "← Importado do CRM: {$clienteExterno['nome']}\n";
    }
}
```

---

## 6. RESOLUÇÃO DE CONFLITOS

### 6.1 Conflito: Editado nos Dois Lugares

**Cenário:**
- Cliente editado no Ecletech às 10:30
- Mesmo cliente editado no GestaoClick às 10:35
- Sincronização CRON roda às 10:40

**Estratégia 1: Última atualização vence (Last Write Wins)**

```php
private function processarItem(...): void
{
    $link = $this->modelLink->buscarPorExternalId(...);

    if ($link) {
        $clienteLocal = $modelCliente->buscarPorId($link['entity_id']);

        // Compara timestamps
        $timestampLocal = strtotime($clienteLocal['atualizado_em']);
        $timestampExterno = strtotime($clienteExterno['updated_at']);

        if ($timestampExterno > $timestampLocal) {
            // CRM é mais recente - atualizar Ecletech
            $modelCliente->atualizar($link['entity_id'], $clienteExterno);
            echo "← Atualizado do CRM (mais recente)\n";
        } else {
            // Ecletech é mais recente - atualizar CRM
            $provider->atualizar('cliente', $link['external_id'], $clienteLocal, $idLoja);
            echo "→ Atualizado no CRM (mais recente)\n";
        }
    }
}
```

**Estratégia 2: CRM sempre vence (Read-only em entidades)**

```php
// No config do provider
'entidades' => [
    'produto' => [
        'read_only' => true,  // Produtos só são lidos do CRM, nunca enviados
        'endpoints' => [
            'listar' => '/products'
        ]
    ]
]
```

---

## 7. EXEMPLOS COMPLETOS

### 7.1 Exemplo Completo: Cadastro de Cliente

```
INTERFACE WEB
     │
     │ 1. Usuário preenche formulário
     │    Nome: João Silva
     │    Email: joao@email.com
     │    Telefone: (11) 99999-8888
     │
     ↓
FRONTEND (JavaScript)
     │
     │ 2. POST /api/clientes
     │    { nome: "João Silva", email: "joao@email.com", ... }
     │
     ↓
BACKEND - ControllerCliente
     │
     │ 3. Valida dados
     │ 4. Salva no banco
     │    INSERT INTO clientes (...) VALUES (...)
     │    → ID gerado: 450
     │
     ↓
ServiceCrm::sincronizarParaExterno('cliente', 450, 10)
     │
     │ 5. Busca integração da loja 10
     │    → Provider: gestao_click, ativo: 1
     │
     │ 6. Obtém provider
     │    → new GestaoClickProvider()
     │
     │ 7. Busca cliente #450
     │    → { id: 450, nome: "João Silva", ... }
     │
     │ 8. Busca vínculo
     │    SELECT * FROM crm_entity_links
     │    WHERE entity_type='cliente' AND entity_id=450
     │    → Não encontrado (cliente novo)
     │
     ↓
GestaoClickProvider::criar('cliente', [...], 10)
     │
     │ 9. ClienteHandler::transformarParaExterno()
     │    Ecletech → GestaoClick
     │    { nome: "João Silva" } → { name: "João Silva" }
     │    { telefone: "11999998888" } → { phone: "(11) 99999-8888" }
     │
     │ 10. Requisição HTTP
     │     POST https://api.gestaoclick.com/v1/customers
     │     Authorization: Bearer xyz123
     │     {
     │       "name": "João Silva",
     │       "email": "joao@email.com",
     │       "phone": "(11) 99999-8888"
     │     }
     │
     ↓
API GESTAOCLICK
     │
     │ 11. Processa requisição
     │ 12. Cria cliente no banco deles
     │     → ID gerado: gc_99999
     │
     │ 13. Retorna resposta
     │     200 OK
     │     {
     │       "id": "gc_99999",
     │       "name": "João Silva",
     │       "created_at": "2025-01-14T10:30:00Z"
     │     }
     │
     ↓
GestaoClickProvider (recebe response)
     │
     │ 14. Retorna
     │     [
     │       'external_id' => 'gc_99999',
     │       'dados' => [...]
     │     ]
     │
     ↓
ServiceCrm (recebe resultado)
     │
     │ 15. Salva vínculo
     │     INSERT INTO crm_entity_links (
     │       id_loja = 10,
     │       provider = 'gestao_click',
     │       entity_type = 'cliente',
     │       entity_id = 450,
     │       external_id = 'gc_99999'
     │     )
     │
     │ 16. Log de auditoria
     │     INSERT INTO crm_sync_log (
     │       tipo = 'manual',
     │       operacao = 'create',
     │       entity_type = 'cliente',
     │       entity_id = 450,
     │       external_id = 'gc_99999',
     │       status = 'sucesso'
     │     )
     │
     ↓
ControllerCliente (retorna)
     │
     │ 17. Response
     │     200 OK
     │     { success: true, id: 450 }
     │
     ↓
FRONTEND (recebe)
     │
     │ 18. Exibe mensagem
     │     "Cliente cadastrado com sucesso!"
     │
     ↓
USUÁRIO
```

**Resultado:**
- Cliente salvo no Ecletech (ID: 450)
- Cliente salvo no GestaoClick (ID: gc_99999)
- Vínculo criado entre os dois
- Log de auditoria registrado

**Tempo total:** ~500ms

---

### 7.2 Exemplo Completo: Atualização via Webhook

```
GESTAOCLICK (Interface Web)
     │
     │ 1. Usuário edita cliente gc_99999
     │    Nome: João Silva → João Silva Santos
     │
     │ 2. GestaoClick salva no banco deles
     │
     │ 3. Dispara webhook
     │    POST https://seu-ecletech.com/api/crm/webhook/gestao_click
     │    X-Webhook-Signature: abc123...
     │    {
     │      "event": "customer.updated",
     │      "data": {
     │        "id": "gc_99999",
     │        "name": "João Silva Santos",
     │        "email": "joao@email.com",
     │        "updated_at": "2025-01-14T15:45:00Z"
     │      }
     │    }
     │
     ↓
ECLETECH - ControllerCrmWebhook::receber()
     │
     │ 4. Valida assinatura
     │    hash_hmac('sha256', payload, secret) === signature
     │    → Válido ✓
     │
     │ 5. Identifica evento
     │    event = "customer.updated"
     │    → entity_type = 'cliente'
     │
     │ 6. Busca vínculo
     │    SELECT * FROM crm_entity_links
     │    WHERE provider='gestao_click'
     │      AND entity_type='cliente'
     │      AND external_id='gc_99999'
     │    → entity_id = 450
     │
     │ 7. Atualiza cliente local
     │    UPDATE clientes
     │    SET nome = 'João Silva Santos',
     │        atualizado_em = NOW()
     │    WHERE id = 450
     │
     │ 8. Log
     │    INSERT INTO crm_sync_log (
     │      tipo = 'webhook',
     │      operacao = 'update',
     │      entity_id = 450,
     │      status = 'sucesso'
     │    )
     │
     │ 9. Retorna
     │    200 OK
     │    { success: true }
     │
     ↓
GESTAOCLICK (recebe confirmação)
     │
     │ Webhook processado com sucesso ✓
```

**Tempo total:** ~100ms

**Vantagem:** Atualização instantânea, sem esperar CRON!

---

## 8. RESUMO DOS FLUXOS

### 8.1 Quando usar cada método

| Método | Quando usar | Frequência | Latência |
|--------|-------------|------------|----------|
| **Automático (Events)** | Operações normais do dia a dia | Toda vez que criar/editar | Instantâneo |
| **Manual (Botão)** | Re-sincronizar registro específico | Sob demanda | Instantâneo |
| **CRON (Paginado)** | Importar novos registros do CRM | A cada 5-10 min | Até 10 min |
| **Webhook** | Mudanças no CRM externo | Tempo real | ~100ms |
| **Bulk (Script)** | Sincronização inicial | Uma vez | Minutos/horas |

### 8.2 Fluxo Recomendado para Produção

```
┌─────────────────────────────────────────────────────────────┐
│ DIA A DIA (Operação Normal)                                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Usuário cadastra/edita no Ecletech                        │
│  ↓                                                          │
│  Dispara evento → ServiceCrm::sincronizarParaExterno()     │
│  ↓                                                          │
│  Envia para CRM externo (async se possível)                │
│                                                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ SINCRONIZAÇÃO REVERSA (CRM → Ecletech)                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Webhooks (tempo real):                                     │
│  CRM externo → POST /webhook → Atualiza Ecletech           │
│                                                             │
│  CRON (backup):                                             │
│  A cada 10 min → Busca novos/atualizados → Importa         │
│                                                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ SETUP INICIAL (Uma vez)                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Script bulk → Sincroniza tudo → Cria vínculos             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 9. CONCLUSÃO

### Perguntas Frequentes

**Q: E se o CRM cair no momento da sincronização?**
A: O cadastro no Ecletech é salvo normalmente. A sincronização falha silenciosamente e será tentada novamente no próximo CRON.

**Q: Como garantir que não duplica?**
A: A tabela `crm_entity_links` garante vínculo único entre IDs.

**Q: Posso integrar com múltiplos CRMs?**
A: Sim! Cada linha em `crm_entity_links` tem campo `provider`.

**Q: Performance com milhares de registros?**
A: Sincronização paginada + processamento assíncrono (filas).

---

**Documento:** CRM_FLUXOS_PRATICOS.md
**Versão:** 1.0
**Data:** Janeiro 2025
