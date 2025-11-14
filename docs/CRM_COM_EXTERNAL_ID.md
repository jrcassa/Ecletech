# 🔗 SINCRONIZAÇÃO CRM - Usando `external_id`

**Versão simplificada usando campo `external_id` direto nas tabelas**

---

## 📋 DIFERENÇA DAS ABORDAGENS

### Proposta Original (documentos anteriores)

Usava tabela separada `crm_entity_links`:

```sql
-- Tabela de vínculo
CREATE TABLE crm_entity_links (
    entity_type VARCHAR(50),
    entity_id INT,
    external_id VARCHAR(100),
    provider VARCHAR(50)
);

-- Tabelas principais SEM external_id
CREATE TABLE clientes (
    id INT,
    nome VARCHAR(200),
    email VARCHAR(100)
    -- SEM external_id
);
```

**Vantagem:** Suporta múltiplos CRMs simultâneos
**Desvantagem:** Mais complexo

---

### Sua Abordagem ✅ (Mais Simples)

Campo `external_id` **diretamente nas tabelas**:

```sql
-- Tabelas principais COM external_id
CREATE TABLE clientes (
    id INT,
    nome VARCHAR(200),
    email VARCHAR(100),
    external_id VARCHAR(100) DEFAULT NULL  -- ✅ JÁ EXISTE
);

-- Outras entidades também
CREATE TABLE vendas (
    id INT,
    ...,
    external_id VARCHAR(100) DEFAULT NULL  -- ✅ JÁ EXISTE
);

CREATE TABLE produtos (
    id INT,
    ...,
    external_id VARCHAR(100) DEFAULT NULL  -- ✅ JÁ EXISTE
);
```

**Vantagem:** Muito mais simples
**Desvantagem:** Apenas 1 CRM por vez (suficiente para 99% dos casos)

---

## ✅ COMO FICA A SINCRONIZAÇÃO

### 1. ECLETECH → CRM (Enviar)

#### Fluxo Simplificado

```
1. Usuário cria cliente
   ↓
2. Salva no banco
   INSERT INTO clientes (nome, email, external_id)
   VALUES ('João Silva', 'joao@email.com', NULL)
   → id: 450, external_id: NULL
   ↓
3. Verifica se já foi sincronizado
   SELECT external_id FROM clientes WHERE id=450
   → external_id = NULL (não foi sincronizado)
   ↓
4. Envia para CRM
   POST /v1/customers
   { name: "João Silva", ... }
   ↓
5. CRM retorna
   { id: "gc_99999", ... }
   ↓
6. Atualiza external_id
   UPDATE clientes SET external_id='gc_99999' WHERE id=450
   ✅ PRONTO!
```

#### Código: ServiceCrm.php

```php
<?php

namespace App\CRM\Services;

use App\CRM\Core\CrmManager;
use App\Models\Cliente\ModelCliente;

class ServiceCrm
{
    private CrmManager $manager;
    private ModelCliente $modelCliente;

    public function __construct()
    {
        $this->manager = new CrmManager();
        $this->modelCliente = new ModelCliente();
    }

    /**
     * Sincroniza cliente para CRM externo
     */
    public function sincronizarClienteParaExterno(int $idCliente, int $idLoja): array
    {
        try {
            // 1. Buscar integração
            $integracao = $this->buscarIntegracao($idLoja);

            if (!$integracao) {
                return ['success' => false, 'message' => 'Integração não configurada'];
            }

            // 2. Buscar cliente
            $cliente = $this->modelCliente->buscarPorId($idCliente);

            if (!$cliente) {
                throw new \Exception("Cliente #{$idCliente} não encontrado");
            }

            // 3. Obter provider
            $provider = $this->manager->obterProvider($integracao['provider']);

            // 4. Verificar se já foi sincronizado
            if ($cliente['external_id']) {
                // === JÁ EXISTE NO CRM - ATUALIZAR ===

                $resultado = $provider->atualizar(
                    'cliente',
                    $cliente['external_id'],  // gc_99999
                    $cliente,
                    $idLoja
                );

                return [
                    'success' => true,
                    'operacao' => 'update',
                    'external_id' => $cliente['external_id']
                ];

            } else {
                // === NOVO NO CRM - CRIAR ===

                $resultado = $provider->criar(
                    'cliente',
                    $cliente,
                    $idLoja
                );

                // Atualizar external_id no Ecletech
                $this->modelCliente->atualizar($idCliente, [
                    'external_id' => $resultado['external_id']
                ]);

                return [
                    'success' => true,
                    'operacao' => 'create',
                    'external_id' => $resultado['external_id']
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function buscarIntegracao(int $idLoja): ?array
    {
        $db = \App\Core\BancoDados::obterInstancia();

        return $db->buscarUm(
            "SELECT * FROM crm_integracoes WHERE id_loja = ? AND ativo = 1",
            [$idLoja]
        );
    }
}
```

**Muito mais simples!** Apenas 1 query de UPDATE ao invés de INSERT em tabela separada.

---

### 2. CRM → ECLETECH (Receber)

#### Fluxo Simplificado

```
1. CRON busca clientes do CRM
   GET /v1/customers?page=1
   ↓
2. Para cada cliente retornado:
   { id: "gc_12345", name: "Maria Santos", ... }
   ↓
3. Busca por external_id
   SELECT * FROM clientes WHERE external_id='gc_12345'
   ↓
   ┌─────┴─────┐
   │           │
EXISTE?      NÃO?
   │           │
   │           └──→ Buscar por email/CPF
   │                SELECT * FROM clientes WHERE email='maria@email.com'
   │                │
   │           ┌────┴────┐
   │           │         │
   │        EXISTE?    NÃO?
   │           │         │
   │           │         └──→ CRIAR NOVO
   │           │              INSERT INTO clientes
   │           │              (nome, email, external_id)
   │           │              VALUES ('Maria', 'maria@...', 'gc_12345')
   │           │
   │           └──→ ATUALIZAR external_id
   │                UPDATE clientes
   │                SET external_id='gc_12345'
   │                WHERE email='maria@email.com'
   │
   └──→ ATUALIZAR DADOS
        UPDATE clientes
        SET nome='Maria Santos', ...
        WHERE external_id='gc_12345'
```

#### Código: ServiceCrmSync.php

```php
<?php

namespace App\CRM\Services;

use App\CRM\Core\CrmManager;
use App\Models\Cliente\ModelCliente;

class ServiceCrmSync
{
    private CrmManager $manager;
    private ModelCliente $modelCliente;

    public function __construct()
    {
        $this->manager = new CrmManager();
        $this->modelCliente = new ModelCliente();
    }

    /**
     * Sincroniza do CRM externo para Ecletech
     */
    public function sincronizarClientesDoExterno(int $idLoja): array
    {
        try {
            // 1. Buscar integração
            $integracao = $this->buscarIntegracao($idLoja);

            if (!$integracao) {
                return ['success' => false, 'message' => 'Integração não configurada'];
            }

            // 2. Obter provider
            $provider = $this->manager->obterProvider($integracao['provider']);

            // 3. Buscar clientes do CRM (paginado)
            $pagina = 1;
            $limite = 100;
            $totalProcessados = 0;
            $totalCriados = 0;
            $totalAtualizados = 0;

            do {
                $resultado = $provider->buscar('cliente', $pagina, $limite, $idLoja);

                foreach ($resultado['dados'] as $clienteExterno) {
                    $acao = $this->processarCliente($clienteExterno, $idLoja);

                    $totalProcessados++;

                    if ($acao === 'criado') {
                        $totalCriados++;
                    } elseif ($acao === 'atualizado') {
                        $totalAtualizados++;
                    }
                }

                $pagina++;

            } while ($pagina <= $resultado['total_paginas']);

            return [
                'success' => true,
                'total_processados' => $totalProcessados,
                'total_criados' => $totalCriados,
                'total_atualizados' => $totalAtualizados
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Processa um cliente individual
     */
    private function processarCliente(array $clienteExterno, int $idLoja): string
    {
        // 1. Buscar por external_id
        $cliente = $this->modelCliente->buscarPorExternalId($clienteExterno['external_id']);

        if ($cliente) {
            // === JÁ EXISTE - ATUALIZAR ===

            // Verificar se precisa atualizar (comparar timestamps)
            if ($this->deveAtualizar($cliente, $clienteExterno)) {
                $this->modelCliente->atualizar($cliente['id'], [
                    'nome' => $clienteExterno['nome'],
                    'email' => $clienteExterno['email'],
                    'telefone' => $clienteExterno['telefone'],
                    'celular' => $clienteExterno['celular'],
                    // ... outros campos
                ]);

                return 'atualizado';
            }

            return 'ignorado';

        } else {
            // === NÃO EXISTE - VERIFICAR DUPLICAÇÃO ===

            // Buscar por email (evitar duplicação)
            $clientePorEmail = null;
            if (!empty($clienteExterno['email'])) {
                $clientePorEmail = $this->modelCliente->buscarPorEmail($clienteExterno['email']);
            }

            // Buscar por CPF (evitar duplicação)
            $clientePorCpf = null;
            if (!empty($clienteExterno['cpf'])) {
                $clientePorCpf = $this->modelCliente->buscarPorCpf($clienteExterno['cpf']);
            }

            if ($clientePorEmail || $clientePorCpf) {
                // === JÁ EXISTE MAS SEM EXTERNAL_ID - ATUALIZAR ===

                $clienteExistente = $clientePorEmail ?: $clientePorCpf;

                // Atualizar external_id + dados
                $this->modelCliente->atualizar($clienteExistente['id'], [
                    'external_id' => $clienteExterno['external_id'],  // ← Adiciona vínculo
                    'nome' => $clienteExterno['nome'],
                    'email' => $clienteExterno['email'],
                    // ... outros campos
                ]);

                return 'vinculado';

            } else {
                // === REALMENTE NOVO - CRIAR ===

                $this->modelCliente->criar([
                    'id_loja' => $idLoja,
                    'external_id' => $clienteExterno['external_id'],  // ← Já cria com vínculo
                    'tipo_pessoa' => $clienteExterno['tipo_pessoa'],
                    'nome' => $clienteExterno['nome'],
                    'email' => $clienteExterno['email'],
                    'cpf' => $clienteExterno['cpf'] ?? null,
                    'cnpj' => $clienteExterno['cnpj'] ?? null,
                    'telefone' => $clienteExterno['telefone'] ?? null,
                    'celular' => $clienteExterno['celular'] ?? null,
                    'ativo' => $clienteExterno['ativo'] ?? true,
                    // ... outros campos
                ]);

                return 'criado';
            }
        }
    }

    /**
     * Verifica se deve atualizar (compara timestamps)
     */
    private function deveAtualizar(array $clienteLocal, array $clienteExterno): bool
    {
        $timestampLocal = strtotime($clienteLocal['modificado_em'] ?? $clienteLocal['cadastrado_em']);
        $timestampExterno = strtotime($clienteExterno['updated_at'] ?? $clienteExterno['created_at']);

        // Atualiza se CRM é mais recente
        return $timestampExterno > $timestampLocal;
    }

    private function buscarIntegracao(int $idLoja): ?array
    {
        $db = \App\Core\BancoDados::obterInstancia();

        return $db->buscarUm(
            "SELECT * FROM crm_integracoes WHERE id_loja = ? AND ativo = 1",
            [$idLoja]
        );
    }
}
```

---

## 🔄 COMPARAÇÃO: ANTES vs DEPOIS

### ANTES (com tabela crm_entity_links)

```php
// Verificar vínculo
$link = $db->buscarUm(
    "SELECT * FROM crm_entity_links
     WHERE entity_type='cliente' AND entity_id=?",
    [450]
);

if ($link) {
    // Atualizar CRM
    $provider->atualizar('cliente', $link['external_id'], $dados);
} else {
    // Criar no CRM
    $result = $provider->criar('cliente', $dados);

    // Salvar vínculo
    $db->inserir("INSERT INTO crm_entity_links ...");
}
```

**Queries:** 2-3 (SELECT, INSERT/UPDATE na tabela de vínculo)

---

### DEPOIS (com external_id) ✅

```php
// Buscar cliente
$cliente = $modelCliente->buscarPorId(450);

if ($cliente['external_id']) {
    // Atualizar CRM
    $provider->atualizar('cliente', $cliente['external_id'], $cliente);
} else {
    // Criar no CRM
    $result = $provider->criar('cliente', $cliente);

    // Atualizar external_id
    $modelCliente->atualizar(450, [
        'external_id' => $result['external_id']
    ]);
}
```

**Queries:** 1-2 (SELECT, UPDATE se necessário)

**Redução:** ~33% menos queries! ✅

---

## 📊 EXEMPLOS PRÁTICOS

### Exemplo 1: Cliente Criado no Ecletech

```sql
-- ESTADO INICIAL
clientes:
  id=450, nome="João Silva", external_id=NULL

-- APÓS SINCRONIZAR
clientes:
  id=450, nome="João Silva", external_id="gc_99999"
```

**Código:**

```php
$cliente = $modelCliente->buscarPorId(450);
// external_id = NULL

$result = $provider->criar('cliente', $cliente, $idLoja);
// Response: { id: "gc_99999", ... }

$modelCliente->atualizar(450, [
    'external_id' => 'gc_99999'
]);

// Agora: external_id = "gc_99999" ✅
```

---

### Exemplo 2: Cliente Criado no CRM

```sql
-- CRM tem:
{ id: "gc_88888", name: "Maria Santos", email: "maria@email.com" }

-- Ecletech NÃO tem

-- APÓS IMPORTAR
clientes:
  id=500, nome="Maria Santos", email="maria@email.com", external_id="gc_88888"
```

**Código:**

```php
$clienteExterno = [
    'external_id' => 'gc_88888',
    'nome' => 'Maria Santos',
    'email' => 'maria@email.com'
];

// Buscar por external_id
$existe = $modelCliente->buscarPorExternalId('gc_88888');
// null

// Buscar por email (evitar duplicação)
$existe = $modelCliente->buscarPorEmail('maria@email.com');
// null

// Criar novo
$modelCliente->criar([
    'id_loja' => 10,
    'external_id' => 'gc_88888',  // ← Já cria com vínculo
    'nome' => 'Maria Santos',
    'email' => 'maria@email.com'
]);
```

---

### Exemplo 3: Evitar Duplicação

```sql
-- ECLETECH JÁ TEM:
clientes:
  id=450, nome="João Silva", email="joao@email.com", external_id=NULL

-- CRM TEM:
{ id: "gc_99999", name: "João Silva", email: "joao@email.com" }

-- São a mesma pessoa!
```

**Código:**

```php
$clienteExterno = [
    'external_id' => 'gc_99999',
    'nome' => 'João Silva',
    'email' => 'joao@email.com'
];

// 1. Buscar por external_id
$cliente = $modelCliente->buscarPorExternalId('gc_99999');
// null (não tem external_id ainda)

// 2. Buscar por email
$cliente = $modelCliente->buscarPorEmail('joao@email.com');
// { id: 450, nome: "João Silva", external_id: NULL }

// 3. JÁ EXISTE! Apenas atualizar external_id
$modelCliente->atualizar(450, [
    'external_id' => 'gc_99999'
]);

// Agora estão vinculados ✅
```

**Resultado:**

```sql
clientes:
  id=450, nome="João Silva", email="joao@email.com", external_id="gc_99999"
```

**Sem duplicação!** ✅

---

## ⚠️ LIMITAÇÃO: Apenas 1 CRM por vez

Com `external_id` único, você só pode ter **1 CRM ativo por vez**.

### Cenário Problemático

```sql
-- Quero integrar com GestaoClick E Pipedrive simultaneamente

clientes:
  id=450, nome="João Silva", external_id=???

-- Qual ID usar?
-- gc_99999 (GestaoClick) OU 12345 (Pipedrive)?
```

**Não é possível com external_id único!**

---

### Solução 1: Apenas 1 CRM ativo (Recomendado)

```php
// Na tabela crm_integracoes
// Apenas 1 registro por loja (UNIQUE constraint)

crm_integracoes:
  id=1, id_loja=10, provider='gestao_click', ativo=1

// Se quiser trocar para Pipedrive:
// 1. Desativar GestaoClick
// 2. Ativar Pipedrive
// 3. Re-sincronizar (atualizará external_id)
```

**99% dos casos usam apenas 1 CRM** ✅

---

### Solução 2: Múltiplos external_id (Se realmente precisar)

```sql
-- Adicionar campo por provider
ALTER TABLE clientes
ADD COLUMN external_id_gestaoclick VARCHAR(100),
ADD COLUMN external_id_pipedrive VARCHAR(100),
ADD COLUMN external_id_bling VARCHAR(100);
```

**Desvantagem:** Polui schema, não escala

---

### Solução 3: Voltar para tabela crm_entity_links

Se realmente precisar de múltiplos CRMs simultâneos:

```sql
CREATE TABLE crm_entity_links (
    id BIGINT PRIMARY KEY,
    entity_type VARCHAR(50),
    entity_id INT,
    provider VARCHAR(50),
    external_id VARCHAR(100),
    UNIQUE(provider, entity_type, entity_id)
);

-- Permite:
crm_entity_links:
  entity_id=450, provider='gestao_click', external_id='gc_99999'
  entity_id=450, provider='pipedrive', external_id='12345'
```

Mas **adiciona complexidade** que você provavelmente não precisa.

---

## ✅ RECOMENDAÇÃO FINAL

### Para 1 CRM (99% dos casos) - Use external_id ✅

**Vantagens:**
- ✅ Muito mais simples
- ✅ Menos queries
- ✅ Menos tabelas
- ✅ Código mais limpo
- ✅ Performance melhor

**Desvantagens:**
- ❌ Apenas 1 CRM por vez

**Quando usar:**
- Você vai usar apenas GestaoClick, OU Pipedrive, OU Bling
- Não precisa de múltiplos CRMs simultâneos
- **99% das empresas**

---

### Para múltiplos CRMs - Use crm_entity_links

**Vantagens:**
- ✅ Suporta N CRMs simultâneos
- ✅ Histórico de integrações

**Desvantagens:**
- ❌ Mais complexo
- ❌ Mais queries
- ❌ Mais código

**Quando usar:**
- Precisa integrar com 2+ CRMs ao mesmo tempo
- Empresas grandes com múltiplos sistemas
- **1% das empresas**

---

## 📝 ALTERAÇÕES NOS DOCUMENTOS ANTERIORES

### Se usar external_id (sua abordagem):

**REMOVER:**
- ❌ Tabela `crm_entity_links`
- ❌ `ModelCrmEntityLink.php`
- ❌ Queries em `crm_entity_links`

**MANTER:**
- ✅ Tabela `crm_integracoes`
- ✅ Tabela `crm_sync_log`
- ✅ Providers
- ✅ Handlers
- ✅ Toda lógica de sincronização

**ALTERAR:**
- `ServiceCrm.php` - Usar `external_id` ao invés de buscar em `crm_entity_links`
- `ServiceCrmSync.php` - Usar `external_id` ao invés de buscar em `crm_entity_links`
- Migrations - Não criar `crm_entity_links`

---

## 🎯 RESUMO

Você já tem `external_id` nas tabelas? **Ótimo!** Isso simplifica:

### Sincronização Ecletech → CRM

```php
if ($cliente['external_id']) {
    $provider->atualizar('cliente', $cliente['external_id'], $cliente);
} else {
    $result = $provider->criar('cliente', $cliente);
    $modelCliente->atualizar($id, ['external_id' => $result['external_id']]);
}
```

### Sincronização CRM → Ecletech

```php
$cliente = $modelCliente->buscarPorExternalId($externalId);

if ($cliente) {
    $modelCliente->atualizar($cliente['id'], $dados);
} else {
    // Verificar duplicação por email/CPF
    $clientePorEmail = $modelCliente->buscarPorEmail($dados['email']);

    if ($clientePorEmail) {
        // Apenas adicionar external_id
        $modelCliente->atualizar($clientePorEmail['id'], [
            'external_id' => $externalId
        ]);
    } else {
        // Criar novo
        $modelCliente->criar([
            ...$dados,
            'external_id' => $externalId
        ]);
    }
}
```

**Simples, direto e eficiente!** ✅

---

**Documento:** CRM_COM_EXTERNAL_ID.md
**Versão:** 1.0
**Data:** Janeiro 2025
**Nota:** Versão simplificada para uso com campo `external_id` direto nas tabelas
