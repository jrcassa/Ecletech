# 🗄️ CONFIGURAÇÃO CRM NO BANCO DE DADOS

**Como indicar qual CRM usar via banco de dados**

---

## 🎯 CONCEITO

```
┌────────────────────────────────────────────────────┐
│ CÓDIGO (App/CRM/Providers/)                        │
│                                                    │
│ Vários CRMs DISPONÍVEIS:                           │
│ ✅ GestaoClick/                                    │
│ ✅ Pipedrive/                                      │
│ ✅ Bling/                                          │
│ ✅ RDStation/                                      │
│ ✅ HubSpot/                                        │
│                                                    │
└────────────────────────────────────────────────────┘
                      ↓
            Qual está ATIVO?
                      ↓
┌────────────────────────────────────────────────────┐
│ BANCO DE DADOS (crm_integracoes)                   │
│                                                    │
│ Por LOJA, apenas 1 CRM ATIVO:                      │
│                                                    │
│ Loja 10 → provider='gestao_click', ativo=1         │
│ Loja 20 → provider='pipedrive', ativo=1            │
│ Loja 30 → provider='bling', ativo=1                │
│                                                    │
└────────────────────────────────────────────────────┘
```

---

## 📊 TABELA: `crm_integracoes`

### 1. Schema SQL

```sql
CREATE TABLE crm_integracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Loja
    id_loja INT NOT NULL,

    -- Qual CRM está sendo usado
    provider VARCHAR(50) NOT NULL,              -- 'gestao_click', 'pipedrive', 'bling'

    -- Status
    ativo TINYINT(1) DEFAULT 1,                 -- 1=ativo, 0=inativo

    -- Credenciais (criptografadas)
    credenciais TEXT NOT NULL,                  -- JSON criptografado

    -- Configurações específicas (JSON)
    configuracoes JSON DEFAULT NULL,            -- Timeout, batch_size, etc

    -- Timestamps
    ultima_sincronizacao DATETIME DEFAULT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por INT DEFAULT NULL,

    -- Constraints
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_loja (id_loja),            -- Apenas 1 CRM por loja
    INDEX idx_provider (provider),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Constraint importante:**
```sql
UNIQUE KEY unique_loja (id_loja)
```
**Garante que cada loja tenha apenas 1 integração ativa!**

---

### 2. Exemplos de Dados

```sql
-- Loja 10 usa GestaoClick
INSERT INTO crm_integracoes (id_loja, provider, ativo, credenciais, configuracoes) VALUES (
    10,
    'gestao_click',
    1,
    'ENCRYPTED_DATA_HERE',
    '{"sync_interval_minutes": 10, "batch_size": 100}'
);

-- Loja 20 usa Pipedrive
INSERT INTO crm_integracoes (id_loja, provider, ativo, credenciais, configuracoes) VALUES (
    20,
    'pipedrive',
    1,
    'ENCRYPTED_DATA_HERE',
    '{"sync_interval_minutes": 5, "batch_size": 50}'
);

-- Loja 30 usa Bling
INSERT INTO crm_integracoes (id_loja, provider, ativo, credenciais, configuracoes) VALUES (
    30,
    'bling',
    1,
    'ENCRYPTED_DATA_HERE',
    '{"sync_interval_minutes": 15, "batch_size": 200}'
);
```

**Resultado:**

| id | id_loja | provider | ativo | credenciais | configuracoes |
|----|---------|----------|-------|-------------|---------------|
| 1  | 10      | gestao_click | 1 | ENCRYPTED | {...} |
| 2  | 20      | pipedrive | 1 | ENCRYPTED | {...} |
| 3  | 30      | bling | 1 | ENCRYPTED | {...} |

---

## 🔧 COMO O SISTEMA USA

### 1. Listar CRMs Disponíveis (Código)

```php
// CrmManager.php

public function listarProvidersDisponiveis(): array
{
    // Registro manual de providers disponíveis no código
    $providers = [
        'gestao_click' => \App\CRM\Providers\GestaoClick\GestaoClickProvider::class,
        'pipedrive' => \App\CRM\Providers\Pipedrive\PipedriveProvider::class,
        'bling' => \App\CRM\Providers\Bling\BlingProvider::class,
        'rd_station' => \App\CRM\Providers\RDStation\RDStationProvider::class,
        'hubspot' => \App\CRM\Providers\HubSpot\HubSpotProvider::class,
    ];

    $lista = [];

    foreach ($providers as $slug => $classe) {
        $provider = new $classe();
        $config = $provider->obterConfig();

        $lista[] = [
            'slug' => $slug,
            'nome' => $config['nome'],
            'descricao' => $config['descricao'],
            'versao' => $config['versao'],
            'icone' => $config['icone'] ?? null,
            'credenciais_necessarias' => $config['credenciais_necessarias']
        ];
    }

    return $lista;
}
```

**Response:**
```json
[
    {
        "slug": "gestao_click",
        "nome": "GestaoClick CRM",
        "descricao": "Integração com GestaoClick CRM",
        "versao": "1.0.0",
        "icone": "/images/providers/gestaoclick.svg",
        "credenciais_necessarias": {
            "api_token": {
                "label": "Token de API",
                "tipo": "text",
                "obrigatorio": true
            }
        }
    },
    {
        "slug": "pipedrive",
        "nome": "Pipedrive",
        "descricao": "Integração com Pipedrive CRM",
        "versao": "1.0.0",
        "icone": "/images/providers/pipedrive.svg",
        "credenciais_necessarias": {
            "api_token": {
                "label": "API Token",
                "tipo": "text",
                "obrigatorio": true
            },
            "company_domain": {
                "label": "Company Domain",
                "tipo": "text",
                "obrigatorio": true,
                "ajuda": "Ex: sua-empresa.pipedrive.com"
            }
        }
    },
    {
        "slug": "bling",
        "nome": "Bling",
        "descricao": "Integração com Bling ERP",
        "versao": "1.0.0",
        "icone": "/images/providers/bling.svg",
        "credenciais_necessarias": {
            "api_key": {
                "label": "API Key",
                "tipo": "text",
                "obrigatorio": true
            }
        }
    }
]
```

---

### 2. Obter CRM Ativo da Loja (Banco)

```php
// ServiceCrm.php

public function obterIntegracaoAtiva(int $idLoja): ?array
{
    $db = \App\Core\BancoDados::obterInstancia();

    return $db->buscarUm(
        "SELECT * FROM crm_integracoes
         WHERE id_loja = ? AND ativo = 1",
        [$idLoja]
    );
}
```

**Response:**
```php
[
    'id' => 1,
    'id_loja' => 10,
    'provider' => 'gestao_click',  // ← Qual CRM usar
    'ativo' => 1,
    'credenciais' => 'ENCRYPTED_DATA',
    'configuracoes' => '{"sync_interval_minutes": 10}',
    'ultima_sincronizacao' => '2025-01-14 15:30:00',
    'criado_em' => '2025-01-10 10:00:00'
]
```

---

### 3. Usar o Provider Correto

```php
// ServiceCrm.php

public function sincronizarCliente(int $idCliente, int $idLoja): array
{
    // 1. Buscar qual CRM está ativo no banco
    $integracao = $this->obterIntegracaoAtiva($idLoja);

    if (!$integracao) {
        return ['success' => false, 'message' => 'Nenhum CRM configurado'];
    }

    // 2. Obter provider do código (baseado no 'provider' do banco)
    $manager = new CrmManager();
    $provider = $manager->obterProvider($integracao['provider']);

    // Agora $provider é:
    // - GestaoClickProvider se provider='gestao_click'
    // - PipedriveProvider se provider='pipedrive'
    // - BlingProvider se provider='bling'

    // 3. Usar o provider
    $cliente = $this->modelCliente->buscarPorId($idCliente);

    if ($cliente['external_id']) {
        // Atualizar
        $result = $provider->atualizar('cliente', $cliente['external_id'], $cliente, $idLoja);
    } else {
        // Criar
        $result = $provider->criar('cliente', $cliente, $idLoja);
    }

    return $result;
}
```

**Fluxo:**
```
1. Busca no banco: provider='gestao_click'
2. Código carrega: GestaoClickProvider
3. Usa métodos: criar(), atualizar(), buscar()
```

---

## 🎨 PAINEL ADMINISTRATIVO

### 1. Tela de Seleção de CRM

**Endpoint:** `GET /api/crm/providers`

```php
// ControllerCrmConfig.php

public function listarProviders(Requisicao $req): Resposta
{
    $manager = new CrmManager();
    $providers = $manager->listarProvidersDisponiveis();

    return Resposta::json([
        'success' => true,
        'providers' => $providers
    ]);
}
```

**Frontend (React/Vue):**

```jsx
<div className="crm-selector">
    <h2>Escolha seu CRM</h2>

    <div className="crm-grid">
        {/* GestaoClick */}
        <div className="crm-card" onClick={() => selecionarCRM('gestao_click')}>
            <img src="/images/providers/gestaoclick.svg" />
            <h3>GestaoClick</h3>
            <p>Integração com GestaoClick CRM</p>
            <span className="badge">v1.0.0</span>
        </div>

        {/* Pipedrive */}
        <div className="crm-card" onClick={() => selecionarCRM('pipedrive')}>
            <img src="/images/providers/pipedrive.svg" />
            <h3>Pipedrive</h3>
            <p>Integração com Pipedrive CRM</p>
            <span className="badge">v1.0.0</span>
        </div>

        {/* Bling */}
        <div className="crm-card" onClick={() => selecionarCRM('bling')}>
            <img src="/images/providers/bling.svg" />
            <h3>Bling</h3>
            <p>Integração com Bling ERP</p>
            <span className="badge">v1.0.0</span>
        </div>
    </div>
</div>
```

---

### 2. Formulário de Configuração

Usuário clica em "GestaoClick":

```jsx
<div className="crm-config-form">
    <h2>Configurar GestaoClick</h2>

    <form onSubmit={salvarConfiguracao}>
        {/* Campo dinâmico baseado em credenciais_necessarias */}
        <div className="form-group">
            <label>Token de API</label>
            <input
                type="text"
                name="api_token"
                placeholder="Cole seu token aqui"
                required
            />
            <small>Obtido em: Configurações > API > Gerar Token</small>
        </div>

        <div className="form-group">
            <label>Intervalo de Sincronização (minutos)</label>
            <select name="sync_interval_minutes">
                <option value="5">5 minutos</option>
                <option value="10" selected>10 minutos</option>
                <option value="15">15 minutos</option>
                <option value="30">30 minutos</option>
            </select>
        </div>

        <div className="form-group">
            <label>Tamanho do Lote</label>
            <input type="number" name="batch_size" value="100" />
        </div>

        <button type="button" onClick={testarConexao}>
            Testar Conexão
        </button>

        <button type="submit">Salvar e Ativar</button>
    </form>
</div>
```

---

### 3. Salvar Configuração

**Endpoint:** `POST /api/crm/config`

```php
// ControllerCrmConfig.php

public function salvar(Requisicao $req): Resposta
{
    $dados = $req->obterCorpo();
    $idLoja = $req->obterIdLoja();

    // Validar
    $provider = $dados['provider'];  // 'gestao_click'
    $credenciais = $dados['credenciais'];  // ['api_token' => 'xyz...']
    $configuracoes = $dados['configuracoes'] ?? [];

    // 1. Validar credenciais
    $manager = new CrmManager();
    $providerInstance = $manager->obterProvider($provider);

    $valido = $providerInstance->validarCredenciais($credenciais);

    if (!$valido) {
        return Resposta::json([
            'success' => false,
            'message' => 'Credenciais inválidas'
        ], 400);
    }

    // 2. Criptografar credenciais
    $credenciaisCriptografadas = $this->criptografar(json_encode($credenciais));

    // 3. Verificar se já existe integração para esta loja
    $db = \App\Core\BancoDados::obterInstancia();

    $existente = $db->buscarUm(
        "SELECT id FROM crm_integracoes WHERE id_loja = ?",
        [$idLoja]
    );

    if ($existente) {
        // Atualizar
        $db->executar(
            "UPDATE crm_integracoes SET
                provider = ?,
                credenciais = ?,
                configuracoes = ?,
                ativo = 1,
                atualizado_em = NOW()
             WHERE id_loja = ?",
            [
                $provider,
                $credenciaisCriptografadas,
                json_encode($configuracoes),
                $idLoja
            ]
        );

        $idIntegracao = $existente['id'];

    } else {
        // Inserir
        $idIntegracao = $db->inserir(
            "INSERT INTO crm_integracoes (
                id_loja, provider, credenciais, configuracoes, ativo, criado_em
             ) VALUES (?, ?, ?, ?, 1, NOW())",
            [
                $idLoja,
                $provider,
                $credenciaisCriptografadas,
                json_encode($configuracoes)
            ]
        );
    }

    return Resposta::json([
        'success' => true,
        'message' => 'Integração configurada com sucesso',
        'id' => $idIntegracao,
        'provider' => $provider
    ]);
}

private function criptografar(string $dados): string
{
    $key = getenv('CRM_ENCRYPTION_KEY');
    $iv = openssl_random_pseudo_bytes(16);

    $encrypted = openssl_encrypt($dados, 'AES-256-CBC', $key, 0, $iv);

    return base64_encode($iv . $encrypted);
}
```

---

### 4. Resultado no Banco

Após salvar:

```sql
SELECT * FROM crm_integracoes WHERE id_loja = 10;
```

| id | id_loja | provider | ativo | credenciais | configuracoes |
|----|---------|----------|-------|-------------|---------------|
| 1  | 10      | gestao_click | 1 | dGVzdC4uLg== | {"sync_interval_minutes":10,"batch_size":100} |

**Agora a loja 10 está usando GestaoClick!** ✅

---

## 🔄 TROCAR DE CRM

### Cenário: Trocar GestaoClick → Pipedrive

```php
// 1. Usuário vai no painel e seleciona Pipedrive
// 2. Preenche credenciais do Pipedrive
// 3. Sistema executa:

UPDATE crm_integracoes SET
    provider = 'pipedrive',
    credenciais = 'NOVO_ENCRYPTED',
    configuracoes = '{"sync_interval_minutes": 5}',
    atualizado_em = NOW()
WHERE id_loja = 10;

// 4. Sistema vai usar PipedriveProvider automaticamente
```

**Observação:** `external_id` dos clientes ainda apontam para GestaoClick.

**Solução:** Re-sincronizar

```php
// Opcional: Limpar external_id ao trocar de CRM
UPDATE clientes SET external_id = NULL WHERE id_loja = 10;
UPDATE vendas SET external_id = NULL WHERE id_loja = 10;

// Depois sincronizar tudo novamente com Pipedrive
php cli/crm-sync-bulk.php --loja=10
```

---

## 📋 MIGRATION COMPLETA

```sql
-- =====================================================
-- Migration: Criar tabela de integrações CRM
-- Descrição: Armazena qual CRM está ativo por loja
-- Data: 2025-01-14
-- =====================================================

CREATE TABLE IF NOT EXISTS crm_integracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Loja
    id_loja INT UNSIGNED NOT NULL,

    -- Provider (qual CRM)
    provider VARCHAR(50) NOT NULL COMMENT 'Slug do provider: gestao_click, pipedrive, bling',

    -- Status
    ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',

    -- Credenciais (criptografadas com AES-256-CBC)
    credenciais TEXT NOT NULL COMMENT 'JSON criptografado com credenciais da API',

    -- Configurações (JSON)
    configuracoes JSON DEFAULT NULL COMMENT 'Configurações específicas do provider',

    -- Metadados
    ultima_sincronizacao DATETIME DEFAULT NULL COMMENT 'Última vez que sincronizou',

    -- Auditoria
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por INT UNSIGNED DEFAULT NULL,
    atualizado_por INT UNSIGNED DEFAULT NULL,

    -- Constraints
    FOREIGN KEY (id_loja) REFERENCES lojas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_loja (id_loja),
    INDEX idx_provider (provider),
    INDEX idx_ativo (ativo)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuração de integração CRM por loja';

-- =====================================================
-- Inserir permissões
-- =====================================================

INSERT INTO colaborador_permissions (nome, codigo, descricao, modulo, ativo, criado_em) VALUES
('Visualizar CRM', 'crm.visualizar', 'Permite visualizar configurações de CRM', 'crm', 1, NOW()),
('Configurar CRM', 'crm.configurar', 'Permite configurar integração com CRM', 'crm', 1, NOW()),
('Sincronizar CRM', 'crm.sincronizar', 'Permite executar sincronização manual', 'crm', 1, NOW());
```

---

## 🔐 CRIPTOGRAFIA DE CREDENCIAIS

### Gerar Chave (Uma Vez)

```bash
# Gerar chave de 32 bytes
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
# Output: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

### Adicionar ao `.env`

```env
CRM_ENCRYPTION_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

### Usar no Código

```php
// Criptografar
function criptografar(string $dados): string
{
    $key = getenv('CRM_ENCRYPTION_KEY');
    $iv = openssl_random_pseudo_bytes(16);

    $encrypted = openssl_encrypt($dados, 'AES-256-CBC', $key, 0, $iv);

    // IV + encrypted data
    return base64_encode($iv . $encrypted);
}

// Descriptografar
function descriptografar(string $encrypted): string
{
    $key = getenv('CRM_ENCRYPTION_KEY');
    $data = base64_decode($encrypted);

    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);

    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}
```

---

## 📊 EXEMPLO COMPLETO

### 1. Código tem 5 CRMs disponíveis

```
App/CRM/Providers/
├── GestaoClick/
├── Pipedrive/
├── Bling/
├── RDStation/
└── HubSpot/
```

### 2. Painel mostra opções

```
┌─────────────────────────────────────┐
│ Escolha seu CRM                     │
├─────────────────────────────────────┤
│                                     │
│ [📊] GestaoClick  [📊] Pipedrive    │
│                                     │
│ [📊] Bling        [📊] RD Station   │
│                                     │
│ [📊] HubSpot                        │
│                                     │
└─────────────────────────────────────┘
```

### 3. Usuário escolhe GestaoClick

```sql
INSERT INTO crm_integracoes (id_loja, provider, credenciais, ativo)
VALUES (10, 'gestao_click', 'ENCRYPTED', 1);
```

### 4. Sistema usa GestaoClick

```php
// Busca no banco
$integracao = buscar('id_loja = 10');
// provider = 'gestao_click'

// Carrega do código
$provider = obterProvider('gestao_click');
// Instancia: GestaoClickProvider

// Usa
$provider->criar('cliente', $dados);
// POST https://api.gestaoclick.com/v1/customers
```

---

## 🎯 RESUMO

### Código (App/CRM/Providers/)
```
✅ GestaoClick/  ← Disponível
✅ Pipedrive/    ← Disponível
✅ Bling/        ← Disponível
✅ RDStation/    ← Disponível
✅ HubSpot/      ← Disponível
```

### Banco (crm_integracoes)
```sql
-- Por loja, apenas 1 ativo
id_loja | provider      | ativo
--------|---------------|------
10      | gestao_click  | 1      ← LOJA 10 USA ESTE
20      | pipedrive     | 1      ← LOJA 20 USA ESTE
30      | bling         | 1      ← LOJA 30 USA ESTE
```

### Fluxo
```
1. Usuário seleciona CRM no painel
2. Preenche credenciais
3. Sistema salva no banco: provider='gestao_click'
4. Código usa: GestaoClickProvider
5. Sincronização usa API do GestaoClick
```

**Simples e flexível!** ✅

---

**Documento:** CRM_CONFIGURACAO_BANCO.md
**Versão:** 1.0
**Data:** Janeiro 2025
