# 🔄 ESTRUTURA DE DADOS - INPUT/OUTPUT CRM

**Mapeamento completo entre Ecletech e CRMs externos**

---

## 📋 ÍNDICE

1. [Visão Geral](#1-visão-geral)
2. [Estrutura Ecletech (Fonte)](#2-estrutura-ecletech-fonte)
3. [Estrutura CRMs Externos](#3-estrutura-crms-externos)
4. [Mapeamento e Transformação](#4-mapeamento-e-transformação)
5. [Exemplos Práticos](#5-exemplos-práticos)
6. [Casos Especiais](#6-casos-especiais)

---

## 1. VISÃO GERAL

### 1.1 Fluxo de Transformação

```
┌────────────────────────────────────────────────────────────────┐
│                  ECLETECH (Sistema Interno)                    │
│                                                                │
│  Cliente (Tabela clientes):                                   │
│  {                                                             │
│    "id": 450,                                                  │
│    "tipo_pessoa": "PF",                                        │
│    "nome": "João Silva",                                       │
│    "cpf": "12345678900",                                       │
│    "telefone": "11999998888",                                  │
│    "email": "joao@email.com",                                  │
│    ...                                                         │
│  }                                                             │
│                                                                │
└────────────────────┬───────────────────────────────────────────┘
                     │
                     │ TRANSFORMAÇÃO (Handler)
                     │ - Renomear campos
                     │ - Formatar dados
                     │ - Validar tipos
                     │ - Adicionar metadados
                     │
                     ↓
┌────────────────────────────────────────────────────────────────┐
│                   CRM EXTERNO (API)                            │
│                                                                │
│  Customer (API GestaoClick):                                   │
│  {                                                             │
│    "name": "João Silva",                                       │
│    "document": "123.456.789-00",                               │
│    "phone": "(11) 99999-8888",                                 │
│    "email": "joao@email.com",                                  │
│    "person_type": "individual",                                │
│    ...                                                         │
│  }                                                             │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## 2. ESTRUTURA ECLETECH (FONTE)

### 2.1 Tabela: `clientes`

**Schema completo:**

```sql
CREATE TABLE clientes (
    -- Identificação
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    external_id VARCHAR(50) DEFAULT NULL,      -- ID do CRM externo

    -- Tipo de Pessoa
    tipo_pessoa ENUM('PF', 'PJ') NOT NULL,     -- Pessoa Física ou Jurídica

    -- Dados Pessoa Física
    nome VARCHAR(200) NOT NULL,                -- Nome completo ou Nome Fantasia
    cpf VARCHAR(14) DEFAULT NULL,              -- 123.456.789-00
    rg VARCHAR(20) DEFAULT NULL,
    data_nascimento DATE DEFAULT NULL,

    -- Dados Pessoa Jurídica
    razao_social VARCHAR(200) DEFAULT NULL,
    cnpj VARCHAR(18) DEFAULT NULL,             -- 12.345.678/0001-90
    inscricao_estadual VARCHAR(20) DEFAULT NULL,
    inscricao_municipal VARCHAR(20) DEFAULT NULL,
    tipo_contribuinte VARCHAR(50) DEFAULT NULL,

    -- Contato
    telefone VARCHAR(20) DEFAULT NULL,         -- (11) 99999-8888
    celular VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,

    -- Status
    ativo BOOLEAN DEFAULT TRUE,

    -- Auditoria
    cadastrado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    modificado_em DATETIME ON UPDATE CURRENT_TIMESTAMP,
    deletado_em DATETIME DEFAULT NULL
);
```

### 2.2 Tabela: `clientes_enderecos`

```sql
CREATE TABLE clientes_enderecos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cliente_id BIGINT NOT NULL,

    -- Tipo
    tipo_endereco_id INT,                      -- 1=Comercial, 2=Residencial, etc
    principal BOOLEAN DEFAULT FALSE,

    -- Endereço
    cep VARCHAR(9),                            -- 01310-100
    logradouro VARCHAR(200),                   -- Av. Paulista
    numero VARCHAR(20),                        -- 1000
    complemento VARCHAR(100),                  -- Apto 101
    bairro VARCHAR(100),
    cidade_id INT,                             -- Referência tabela cidades
    uf CHAR(2),                                -- SP
    pais VARCHAR(50) DEFAULT 'Brasil',

    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);
```

### 2.3 Tabela: `clientes_contatos`

```sql
CREATE TABLE clientes_contatos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cliente_id BIGINT NOT NULL,

    -- Contato
    tipo_contato_id INT,                       -- 1=Telefone, 2=Email, 3=WhatsApp
    nome VARCHAR(100),                         -- Nome do contato
    cargo VARCHAR(100),                        -- Gerente, Diretor, etc
    telefone VARCHAR(20),
    email VARCHAR(100),
    observacoes TEXT,
    principal BOOLEAN DEFAULT FALSE,

    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);
```

### 2.4 Exemplo de Objeto Completo (Ecletech)

```json
{
    // TABELA: clientes
    "id": 450,
    "external_id": null,
    "tipo_pessoa": "PF",
    "nome": "João Silva Santos",
    "razao_social": null,
    "cpf": "12345678900",
    "cnpj": null,
    "rg": "MG1234567",
    "inscricao_estadual": null,
    "inscricao_municipal": null,
    "tipo_contribuinte": null,
    "data_nascimento": "1985-03-15",
    "telefone": "1133334444",
    "celular": "11999998888",
    "email": "joao.silva@email.com",
    "ativo": true,
    "cadastrado_em": "2025-01-10 10:30:00",
    "modificado_em": "2025-01-14 15:45:00",
    "deletado_em": null,

    // RELACIONAMENTO: enderecos
    "enderecos": [
        {
            "id": 100,
            "cliente_id": 450,
            "tipo_endereco_id": 2,
            "principal": true,
            "cep": "01310100",
            "logradouro": "Avenida Paulista",
            "numero": "1000",
            "complemento": "Apto 101 Bloco A",
            "bairro": "Bela Vista",
            "cidade_id": 5041,
            "uf": "SP",
            "pais": "Brasil"
        }
    ],

    // RELACIONAMENTO: contatos
    "contatos": [
        {
            "id": 50,
            "cliente_id": 450,
            "tipo_contato_id": 3,
            "nome": "João Silva",
            "cargo": null,
            "telefone": "11999998888",
            "email": "joao.silva@email.com",
            "observacoes": "WhatsApp comercial",
            "principal": true
        }
    ]
}
```

**Formato simplificado (usado na API):**

```json
{
    "id": 450,
    "tipo_pessoa": "PF",
    "nome": "João Silva Santos",
    "cpf": "123.456.789-00",
    "telefone": "(11) 3333-4444",
    "celular": "(11) 99999-8888",
    "email": "joao.silva@email.com",
    "endereco": {
        "cep": "01310-100",
        "logradouro": "Avenida Paulista",
        "numero": "1000",
        "complemento": "Apto 101 Bloco A",
        "bairro": "Bela Vista",
        "cidade": "São Paulo",
        "uf": "SP"
    }
}
```

---

## 3. ESTRUTURA CRMs EXTERNOS

### 3.1 GestaoClick CRM

**Documentação API:** https://docs.gestaoclick.com/api/v1/customers

**Estrutura de Customer:**

```json
{
    // Identificação
    "id": "gc_12345",                          // ID gerado pelo GestaoClick

    // Tipo de Pessoa
    "person_type": "individual",               // individual ou company

    // Pessoa Física
    "name": "João Silva Santos",
    "document": "123.456.789-00",              // CPF formatado
    "birth_date": "1985-03-15",

    // Pessoa Jurídica
    "company_name": null,
    "company_document": null,                  // CNPJ formatado
    "state_registration": null,
    "municipal_registration": null,

    // Contato
    "email": "joao.silva@email.com",
    "phone": "(11) 3333-4444",
    "mobile": "(11) 99999-8888",

    // Endereço (inline, não separado)
    "address": {
        "zipcode": "01310-100",
        "street": "Avenida Paulista",
        "number": "1000",
        "complement": "Apto 101 Bloco A",
        "district": "Bela Vista",
        "city": "São Paulo",
        "state": "SP",
        "country": "Brasil"
    },

    // Status
    "status": "active",                        // active ou inactive

    // Metadados
    "created_at": "2025-01-10T10:30:00Z",
    "updated_at": "2025-01-14T15:45:00Z",

    // Custom Fields (campos personalizados)
    "custom_fields": {
        "ecletech_id": "450",                  // Referência reversa
        "origem": "Ecletech CRM"
    }
}
```

**Endpoints:**

```
GET    /v1/customers              - Listar (paginado)
GET    /v1/customers/{id}         - Buscar por ID
POST   /v1/customers              - Criar
PUT    /v1/customers/{id}         - Atualizar
DELETE /v1/customers/{id}         - Deletar
```

**Paginação:**

```
GET /v1/customers?page=1&limit=100

Response:
{
    "data": [...],                            // Array de customers
    "pagination": {
        "current_page": 1,
        "per_page": 100,
        "total": 850,
        "total_pages": 9
    }
}
```

---

### 3.2 Pipedrive CRM

**Documentação API:** https://developers.pipedrive.com/docs/api/v1/Persons

**Estrutura de Person:**

```json
{
    // Identificação
    "id": 12345,                               // ID numérico

    // Dados
    "name": "João Silva Santos",
    "first_name": "João",
    "last_name": "Silva Santos",

    // Contato
    "email": [
        {
            "value": "joao.silva@email.com",
            "primary": true,
            "label": "work"
        }
    ],
    "phone": [
        {
            "value": "(11) 99999-8888",
            "primary": true,
            "label": "mobile"
        },
        {
            "value": "(11) 3333-4444",
            "primary": false,
            "label": "work"
        }
    ],

    // Organização (se PJ)
    "org_id": null,

    // Status
    "active_flag": true,

    // Metadados
    "add_time": "2025-01-10 10:30:00",
    "update_time": "2025-01-14 15:45:00",

    // Custom Fields (campos personalizados)
    "a1b2c3d4e5f6g7h8": "450",                // Hash = custom field ID
    "9i8h7g6f5e4d3c2": "123.456.789-00"       // CPF
}
```

**Endpoints:**

```
GET    /v1/persons                - Listar
GET    /v1/persons/{id}           - Buscar
POST   /v1/persons                - Criar
PUT    /v1/persons/{id}           - Atualizar
DELETE /v1/persons/{id}           - Deletar
```

**Paginação:**

```
GET /v1/persons?start=0&limit=100

Response:
{
    "success": true,
    "data": [...],
    "additional_data": {
        "pagination": {
            "start": 0,
            "limit": 100,
            "more_items_in_collection": true,
            "next_start": 100
        }
    }
}
```

---

### 3.3 Bling CRM

**Documentação API:** https://developer.bling.com.br/referencia#/Contatos

**Estrutura de Contato:**

```json
{
    // Identificação
    "id": 98765,

    // Tipo
    "tipo": "F",                               // F=Física, J=Jurídica

    // Dados PF
    "nome": "João Silva Santos",
    "cpf_cnpj": "123.456.789-00",
    "rg": "MG1234567",
    "data_nascimento": "15/03/1985",           // DD/MM/YYYY

    // Dados PJ
    "fantasia": null,
    "ie": null,
    "im": null,

    // Contato
    "fone": "(11) 3333-4444",
    "celular": "(11) 99999-8888",
    "email": "joao.silva@email.com",

    // Endereço
    "endereco": "Avenida Paulista",
    "numero": "1000",
    "complemento": "Apto 101 Bloco A",
    "bairro": "Bela Vista",
    "cep": "01310-100",
    "cidade": "São Paulo",
    "uf": "SP",

    // Status
    "situacao": "A",                           // A=Ativo, I=Inativo

    // Metadados
    "dataCadastro": "10/01/2025",
    "dataAlteracao": "14/01/2025"
}
```

**Endpoints:**

```
GET    /contatos                  - Listar
GET    /contatos/{id}             - Buscar
POST   /contatos                  - Criar
PUT    /contatos/{id}             - Atualizar
DELETE /contatos/{id}             - Deletar
```

---

## 4. MAPEAMENTO E TRANSFORMAÇÃO

### 4.1 Mapeamento de Campos (Cliente)

| Campo Ecletech | GestaoClick | Pipedrive | Bling |
|----------------|-------------|-----------|-------|
| `id` | `custom_fields.ecletech_id` | `{hash_field}` | N/A |
| `tipo_pessoa` | `person_type` | `org_id` (null=PF) | `tipo` |
| `nome` | `name` | `name` | `nome` |
| `cpf` | `document` | `{hash_cpf}` | `cpf_cnpj` |
| `cnpj` | `company_document` | `org.tax_id` | `cpf_cnpj` |
| `razao_social` | `company_name` | `org.name` | `fantasia` |
| `email` | `email` | `email[0].value` | `email` |
| `telefone` | `phone` | `phone[0].value` | `fone` |
| `celular` | `mobile` | `phone[1].value` | `celular` |
| `data_nascimento` | `birth_date` | `{hash_birth}` | `data_nascimento` |
| `ativo` | `status` | `active_flag` | `situacao` |
| **Endereço** | | | |
| `cep` | `address.zipcode` | N/A | `cep` |
| `logradouro` | `address.street` | N/A | `endereco` |
| `numero` | `address.number` | N/A | `numero` |
| `complemento` | `address.complement` | N/A | `complemento` |
| `bairro` | `address.district` | N/A | `bairro` |
| `uf` | `address.state` | N/A | `uf` |

### 4.2 Transformações Necessárias

#### 4.2.1 Formatação de Documentos

```php
// CPF: 12345678900 → 123.456.789-00
function formatarCpf(string $cpf): string {
    $limpo = preg_replace('/\D/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $limpo);
}

// CNPJ: 12345678000190 → 12.345.678/0001-90
function formatarCnpj(string $cnpj): string {
    $limpo = preg_replace('/\D/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $limpo);
}

// Limpar (formatar → sem formato)
function limparDocumento(string $doc): string {
    return preg_replace('/\D/', '', $doc);
}
```

#### 4.2.2 Formatação de Telefone

```php
// Telefone: 11999998888 → (11) 99999-8888
function formatarTelefone(string $telefone): string {
    $limpo = preg_replace('/\D/', '', $telefone);

    if (strlen($limpo) === 11) {
        // Celular: (11) 99999-8888
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $limpo);
    } elseif (strlen($limpo) === 10) {
        // Fixo: (11) 3333-4444
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $limpo);
    }

    return $telefone;
}
```

#### 4.2.3 Formatação de CEP

```php
// CEP: 01310100 → 01310-100
function formatarCep(string $cep): string {
    $limpo = preg_replace('/\D/', '', $cep);
    return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $limpo);
}
```

#### 4.2.4 Formatação de Data

```php
// MySQL: 1985-03-15 → ISO8601: 1985-03-15T00:00:00Z
function paraIso8601(string $data): string {
    return date('c', strtotime($data));
}

// Bling usa DD/MM/YYYY
function paraBling(string $data): string {
    return date('d/m/Y', strtotime($data));
}

// De Bling para MySQL
function deBlingParaMysql(string $data): string {
    return date('Y-m-d', strtotime(str_replace('/', '-', $data)));
}
```

#### 4.2.5 Mapeamento de Enums

```php
// Tipo de Pessoa
$mapTipoPessoa = [
    'ecletech_to_gestaoclick' => [
        'PF' => 'individual',
        'PJ' => 'company'
    ],
    'gestaoclick_to_ecletech' => [
        'individual' => 'PF',
        'company' => 'PJ'
    ],
    'ecletech_to_bling' => [
        'PF' => 'F',
        'PJ' => 'J'
    ],
    'bling_to_ecletech' => [
        'F' => 'PF',
        'J' => 'PJ'
    ]
];

// Status
$mapStatus = [
    'ecletech_to_gestaoclick' => [
        true => 'active',
        false => 'inactive'
    ],
    'gestaoclick_to_ecletech' => [
        'active' => true,
        'inactive' => false
    ],
    'ecletech_to_pipedrive' => [
        true => true,
        false => false
    ],
    'ecletech_to_bling' => [
        true => 'A',
        false => 'I'
    ],
    'bling_to_ecletech' => [
        'A' => true,
        'I' => false
    ]
];
```

---

## 5. EXEMPLOS PRÁTICOS

### 5.1 Exemplo Completo: Ecletech → GestaoClick

**Input (Ecletech - Cliente PF):**

```json
{
    "id": 450,
    "tipo_pessoa": "PF",
    "nome": "João Silva Santos",
    "cpf": "12345678900",
    "rg": "MG1234567",
    "data_nascimento": "1985-03-15",
    "telefone": "1133334444",
    "celular": "11999998888",
    "email": "joao.silva@email.com",
    "ativo": true,
    "enderecos": [
        {
            "principal": true,
            "cep": "01310100",
            "logradouro": "Avenida Paulista",
            "numero": "1000",
            "complemento": "Apto 101 Bloco A",
            "bairro": "Bela Vista",
            "cidade": "São Paulo",
            "uf": "SP"
        }
    ]
}
```

**Transformação (Handler):**

```php
// ClienteHandler::transformarParaExterno()

public function transformarParaExterno(array $clienteEcletech): array
{
    $endereçoPrincipal = null;
    foreach ($clienteEcletech['enderecos'] ?? [] as $end) {
        if ($end['principal']) {
            $endereçoPrincipal = $end;
            break;
        }
    }

    return [
        // Tipo
        'person_type' => $this->mapearTipoPessoa($clienteEcletech['tipo_pessoa']),

        // Dados PF
        'name' => $clienteEcletech['nome'],
        'document' => $this->formatarCpf($clienteEcletech['cpf'] ?? ''),
        'birth_date' => $clienteEcletech['data_nascimento'] ?? null,

        // Dados PJ
        'company_name' => $clienteEcletech['razao_social'] ?? null,
        'company_document' => $clienteEcletech['cnpj']
            ? $this->formatarCnpj($clienteEcletech['cnpj'])
            : null,

        // Contato
        'email' => $clienteEcletech['email'] ?? null,
        'phone' => $clienteEcletech['telefone']
            ? $this->formatarTelefone($clienteEcletech['telefone'])
            : null,
        'mobile' => $clienteEcletech['celular']
            ? $this->formatarTelefone($clienteEcletech['celular'])
            : null,

        // Endereço
        'address' => $endereçoPrincipal ? [
            'zipcode' => $this->formatarCep($endereçoPrincipal['cep']),
            'street' => $endereçoPrincipal['logradouro'],
            'number' => $endereçoPrincipal['numero'],
            'complement' => $endereçoPrincipal['complemento'] ?? null,
            'district' => $endereçoPrincipal['bairro'],
            'city' => $endereçoPrincipal['cidade'] ?? 'São Paulo',
            'state' => $endereçoPrincipal['uf'],
            'country' => 'Brasil'
        ] : null,

        // Status
        'status' => $clienteEcletech['ativo'] ? 'active' : 'inactive',

        // Custom Fields (referência reversa)
        'custom_fields' => [
            'ecletech_id' => (string) $clienteEcletech['id'],
            'origem' => 'Ecletech CRM'
        ]
    ];
}

private function mapearTipoPessoa(string $tipo): string {
    return $tipo === 'PF' ? 'individual' : 'company';
}

private function formatarCpf(string $cpf): string {
    $limpo = preg_replace('/\D/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $limpo);
}

private function formatarTelefone(string $telefone): string {
    $limpo = preg_replace('/\D/', '', $telefone);

    if (strlen($limpo) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $limpo);
    } elseif (strlen($limpo) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $limpo);
    }

    return $telefone;
}

private function formatarCep(string $cep): string {
    $limpo = preg_replace('/\D/', '', $cep);
    return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $limpo);
}
```

**Output (GestaoClick - Request Body):**

```json
{
    "person_type": "individual",
    "name": "João Silva Santos",
    "document": "123.456.789-00",
    "birth_date": "1985-03-15",
    "company_name": null,
    "company_document": null,
    "email": "joao.silva@email.com",
    "phone": "(11) 3333-4444",
    "mobile": "(11) 99999-8888",
    "address": {
        "zipcode": "01310-100",
        "street": "Avenida Paulista",
        "number": "1000",
        "complement": "Apto 101 Bloco A",
        "district": "Bela Vista",
        "city": "São Paulo",
        "state": "SP",
        "country": "Brasil"
    },
    "status": "active",
    "custom_fields": {
        "ecletech_id": "450",
        "origem": "Ecletech CRM"
    }
}
```

**Request HTTP:**

```http
POST /v1/customers HTTP/1.1
Host: api.gestaoclick.com
Authorization: Bearer xyz123abc456
Content-Type: application/json

{
    "person_type": "individual",
    "name": "João Silva Santos",
    ...
}
```

**Response do GestaoClick:**

```json
{
    "id": "gc_99999",
    "person_type": "individual",
    "name": "João Silva Santos",
    "document": "123.456.789-00",
    "email": "joao.silva@email.com",
    "phone": "(11) 3333-4444",
    "mobile": "(11) 99999-8888",
    "address": {
        "zipcode": "01310-100",
        "street": "Avenida Paulista",
        ...
    },
    "status": "active",
    "custom_fields": {
        "ecletech_id": "450",
        "origem": "Ecletech CRM"
    },
    "created_at": "2025-01-14T18:30:00Z",
    "updated_at": "2025-01-14T18:30:00Z"
}
```

**Resultado:**
- Cliente criado no GestaoClick com ID: `gc_99999`
- Vínculo salvo: `entity_id=450` ↔ `external_id=gc_99999`

---

### 5.2 Exemplo Completo: GestaoClick → Ecletech

**Input (GestaoClick - Response de GET /customers):**

```json
{
    "id": "gc_88888",
    "person_type": "company",
    "name": "TechSolutions Ltda",
    "company_name": "TechSolutions Desenvolvimento de Software Ltda",
    "company_document": "12.345.678/0001-90",
    "document": null,
    "state_registration": "123456789",
    "email": "contato@techsolutions.com.br",
    "phone": "(11) 4444-5555",
    "mobile": "(11) 98888-7777",
    "address": {
        "zipcode": "04538-133",
        "street": "Avenida Brigadeiro Faria Lima",
        "number": "3477",
        "complement": "13º andar",
        "district": "Itaim Bibi",
        "city": "São Paulo",
        "state": "SP",
        "country": "Brasil"
    },
    "status": "active",
    "created_at": "2025-01-12T09:00:00Z",
    "updated_at": "2025-01-14T14:20:00Z"
}
```

**Transformação (Handler):**

```php
// ClienteHandler::transformarParaLocal()

public function transformarParaLocal(array $clienteExterno): array
{
    $endereco = $clienteExterno['address'] ?? null;

    return [
        // Tipo
        'tipo_pessoa' => $clienteExterno['person_type'] === 'individual' ? 'PF' : 'PJ',

        // Dados PF
        'nome' => $clienteExterno['name'],
        'cpf' => $clienteExterno['document']
            ? $this->limparDocumento($clienteExterno['document'])
            : null,
        'data_nascimento' => $clienteExterno['birth_date'] ?? null,

        // Dados PJ
        'razao_social' => $clienteExterno['company_name'] ?? null,
        'cnpj' => $clienteExterno['company_document']
            ? $this->limparDocumento($clienteExterno['company_document'])
            : null,
        'inscricao_estadual' => $clienteExterno['state_registration'] ?? null,

        // Contato
        'email' => $clienteExterno['email'] ?? null,
        'telefone' => $clienteExterno['phone']
            ? $this->limparTelefone($clienteExterno['phone'])
            : null,
        'celular' => $clienteExterno['mobile']
            ? $this->limparTelefone($clienteExterno['mobile'])
            : null,

        // Status
        'ativo' => $clienteExterno['status'] === 'active',

        // Endereço (será inserido em clientes_enderecos)
        '_endereco' => $endereco ? [
            'tipo_endereco_id' => 1, // Comercial
            'principal' => true,
            'cep' => $this->limparCep($endereco['zipcode']),
            'logradouro' => $endereco['street'],
            'numero' => $endereco['number'],
            'complemento' => $endereco['complement'] ?? null,
            'bairro' => $endereco['district'],
            'uf' => $endereco['state'],
            'pais' => $endereco['country'] ?? 'Brasil',
            // cidade_id será resolvido depois via lookup
        ] : null,

        // Metadados
        'external_id' => $clienteExterno['id']
    ];
}

private function limparDocumento(string $doc): string {
    return preg_replace('/\D/', '', $doc);
}

private function limparTelefone(string $telefone): string {
    return preg_replace('/\D/', '', $telefone);
}

private function limparCep(string $cep): string {
    return preg_replace('/\D/', '', $cep);
}
```

**Output (Ecletech - Dados para INSERT):**

```json
{
    "tipo_pessoa": "PJ",
    "nome": "TechSolutions Ltda",
    "razao_social": "TechSolutions Desenvolvimento de Software Ltda",
    "cnpj": "12345678000190",
    "cpf": null,
    "inscricao_estadual": "123456789",
    "inscricao_municipal": null,
    "data_nascimento": null,
    "email": "contato@techsolutions.com.br",
    "telefone": "1144445555",
    "celular": "11988887777",
    "ativo": true,
    "external_id": "gc_88888",
    "_endereco": {
        "tipo_endereco_id": 1,
        "principal": true,
        "cep": "04538133",
        "logradouro": "Avenida Brigadeiro Faria Lima",
        "numero": "3477",
        "complemento": "13º andar",
        "bairro": "Itaim Bibi",
        "uf": "SP",
        "pais": "Brasil"
    }
}
```

**Salvando no banco:**

```php
// ServiceCrmSync::processarItem()

// 1. Inserir cliente
$idCliente = $modelCliente->criar([
    'tipo_pessoa' => $dados['tipo_pessoa'],
    'nome' => $dados['nome'],
    'razao_social' => $dados['razao_social'],
    'cnpj' => $dados['cnpj'],
    'email' => $dados['email'],
    'telefone' => $dados['telefone'],
    'celular' => $dados['celular'],
    'ativo' => $dados['ativo']
]);

// 2. Inserir endereço
if ($dados['_endereco']) {
    // Buscar cidade_id via nome da cidade
    $cidade = $this->buscarCidadePorNomeUf('São Paulo', 'SP');

    $modelEndereco->criar([
        'cliente_id' => $idCliente,
        'tipo_endereco_id' => $dados['_endereco']['tipo_endereco_id'],
        'principal' => true,
        'cep' => $dados['_endereco']['cep'],
        'logradouro' => $dados['_endereco']['logradouro'],
        'numero' => $dados['_endereco']['numero'],
        'complemento' => $dados['_endereco']['complemento'],
        'bairro' => $dados['_endereco']['bairro'],
        'cidade_id' => $cidade['id'],
        'uf' => $dados['_endereco']['uf'],
        'pais' => $dados['_endereco']['pais']
    ]);
}

// 3. Criar vínculo
$modelLink->criar([
    'id_loja' => $idLoja,
    'provider' => 'gestao_click',
    'entity_type' => 'cliente',
    'entity_id' => $idCliente,         // 500 (gerado agora)
    'external_id' => 'gc_88888'
]);
```

**Resultado:**
- Cliente PJ criado no Ecletech com ID: `500`
- Endereço criado vinculado ao cliente
- Vínculo criado: `entity_id=500` ↔ `external_id=gc_88888`

---

### 5.3 Exemplo: Cliente PJ (Ecletech → Pipedrive)

**Input (Ecletech - Cliente PJ):**

```json
{
    "id": 600,
    "tipo_pessoa": "PJ",
    "nome": "Acme Corporation",
    "razao_social": "Acme Corporation Brasil Ltda",
    "cnpj": "98765432000100",
    "inscricao_estadual": "987654321",
    "email": "contato@acme.com.br",
    "telefone": "1155556666",
    "celular": "11977776666"
}
```

**Transformação:**

```php
// ClienteHandler::transformarParaExterno() - Pipedrive

public function transformarParaExterno(array $clienteEcletech): array
{
    if ($clienteEcletech['tipo_pessoa'] === 'PJ') {
        // Primeiro criar Organization
        $org = $this->criarOrganizacao([
            'name' => $clienteEcletech['razao_social'],
            'address' => '...'
        ]);

        // Depois criar Person vinculada à org
        return [
            'name' => $clienteEcletech['nome'],
            'org_id' => $org['id'],
            'email' => [
                [
                    'value' => $clienteEcletech['email'],
                    'primary' => true,
                    'label' => 'work'
                ]
            ],
            'phone' => [
                [
                    'value' => $this->formatarTelefone($clienteEcletech['celular']),
                    'primary' => true,
                    'label' => 'mobile'
                ],
                [
                    'value' => $this->formatarTelefone($clienteEcletech['telefone']),
                    'primary' => false,
                    'label' => 'work'
                ]
            ],
            'active_flag' => $clienteEcletech['ativo'],

            // Custom fields (hashes obtidos da API de Fields)
            'a1b2c3d4e5f6g7h8' => (string) $clienteEcletech['id'], // Ecletech ID
            '9i8h7g6f5e4d3c2' => $this->formatarCnpj($clienteEcletech['cnpj'])
        ];
    }
}
```

**Output (Pipedrive):**

```json
{
    "name": "Acme Corporation",
    "org_id": 12345,
    "email": [
        {
            "value": "contato@acme.com.br",
            "primary": true,
            "label": "work"
        }
    ],
    "phone": [
        {
            "value": "(11) 97777-6666",
            "primary": true,
            "label": "mobile"
        },
        {
            "value": "(11) 5555-6666",
            "primary": false,
            "label": "work"
        }
    ],
    "active_flag": true,
    "a1b2c3d4e5f6g7h8": "600",
    "9i8h7g6f5e4d3c2": "98.765.432/0001-00"
}
```

---

## 6. CASOS ESPECIAIS

### 6.1 Endereços Múltiplos

**Problema:** Ecletech suporta múltiplos endereços, GestaoClick apenas 1.

**Solução:** Enviar apenas o endereço principal.

```php
// No Handler
$endereçoPrincipal = null;
foreach ($cliente['enderecos'] as $end) {
    if ($end['principal']) {
        $endereçoPrincipal = $end;
        break;
    }
}

// Usar apenas $endereçoPrincipal na transformação
```

### 6.2 Custom Fields no Pipedrive

**Problema:** Pipedrive usa hashes para custom fields (`a1b2c3d4...`)

**Solução:** Buscar mapeamento de fields uma vez e cachear.

```php
// ServiceCrm::obterCustomFields()

public function obterCustomFields(string $provider): array
{
    $cache = Cache::get("crm.{$provider}.custom_fields");

    if ($cache) {
        return $cache;
    }

    // Buscar da API
    $fields = $this->provider->getPersonFields();

    /* Response:
    [
        {
            "key": "a1b2c3d4e5f6g7h8",
            "name": "Ecletech ID",
            "field_type": "varchar"
        },
        ...
    ]
    */

    $mapa = [];
    foreach ($fields as $field) {
        $mapa[$field['name']] = $field['key'];
    }

    // Cachear por 24h
    Cache::set("crm.{$provider}.custom_fields", $mapa, 86400);

    return $mapa;
}

// Uso:
$fields = $this->obterCustomFields('pipedrive');
$data = [
    'name' => 'João Silva',
    $fields['Ecletech ID'] => '450',  // a1b2c3d4e5f6g7h8
    $fields['CPF'] => '123.456.789-00' // 9i8h7g6f5e4d3c2
];
```

### 6.3 Conflito de Dados (Mesmo email, nomes diferentes)

**Cenário:**
- Ecletech: `{ id: 450, nome: "João Silva", email: "joao@email.com" }`
- GestaoClick: `{ id: "gc_12345", nome: "João S.", email: "joao@email.com" }`

**Estratégia 1: Last Write Wins (última alteração vence)**

```php
if ($timestampExterno > $timestampLocal) {
    // Atualizar Ecletech com dados do CRM
    $modelCliente->atualizar($idLocal, $dadosExternos);
} else {
    // Atualizar CRM com dados do Ecletech
    $provider->atualizar($externalId, $dadosLocais);
}
```

**Estratégia 2: Merge inteligente (mesclar campos)**

```php
$merged = [
    'nome' => strlen($dadosExternos['nome']) > strlen($dadosLocais['nome'])
        ? $dadosExternos['nome']  // Usar o nome mais completo
        : $dadosLocais['nome'],
    'email' => $dadosLocais['email'],  // Sempre manter email do Ecletech
    'telefone' => $dadosExternos['telefone'] ?: $dadosLocais['telefone']
];
```

### 6.4 Campos Não Mapeáveis

**Problema:** RG existe no Ecletech mas não no GestaoClick.

**Solução 1:** Ignorar (campo opcional)

**Solução 2:** Usar custom field

```php
'custom_fields' => [
    'ecletech_id' => '450',
    'rg' => 'MG1234567'  // Guardar no CRM externo
]
```

**Solução 3:** Armazenar em observações

```php
'notes' => "RG: MG1234567\nOutras informações..."
```

---

## 7. RESUMO - CHECKLIST DE TRANSFORMAÇÃO

### ✅ Ao enviar para CRM externo (Ecletech → CRM)

- [ ] Mapear campos conforme tabela de mapeamento
- [ ] Formatar documentos (CPF, CNPJ)
- [ ] Formatar telefones
- [ ] Formatar CEP
- [ ] Converter datas (MySQL → ISO8601 ou outro formato)
- [ ] Mapear enums (PF→individual, true→active)
- [ ] Selecionar endereço principal (se CRM aceita apenas 1)
- [ ] Adicionar custom fields (ecletech_id)
- [ ] Validar campos obrigatórios do CRM
- [ ] Remover campos nulos/vazios (se CRM rejeita)

### ✅ Ao receber de CRM externo (CRM → Ecletech)

- [ ] Mapear campos conforme tabela reversa
- [ ] Limpar formatação (remover pontos, traços)
- [ ] Converter datas (ISO8601 → MySQL)
- [ ] Mapear enums reverso (individual→PF, active→true)
- [ ] Buscar cidade_id via lookup (nome+UF → ID)
- [ ] Separar dados em tabelas relacionadas (enderecos, contatos)
- [ ] Extrair external_id para vínculo
- [ ] Validar unicidade (CPF, CNPJ, email)
- [ ] Tratar campos faltantes (usar valores padrão)

---

## 8. CÓDIGO COMPLETO DO HANDLER

### ClienteHandler.php (GestaoClick)

```php
<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

class ClienteHandler
{
    /**
     * Ecletech → GestaoClick
     */
    public function transformarParaExterno(array $cliente): array
    {
        // Pegar endereço principal
        $endereco = $this->obterEnderecoPrincipal($cliente['enderecos'] ?? []);

        return array_filter([
            'person_type' => $cliente['tipo_pessoa'] === 'PF' ? 'individual' : 'company',

            // PF
            'name' => $cliente['nome'],
            'document' => $cliente['cpf'] ? $this->formatarCpf($cliente['cpf']) : null,
            'birth_date' => $cliente['data_nascimento'] ?? null,

            // PJ
            'company_name' => $cliente['razao_social'] ?? null,
            'company_document' => $cliente['cnpj'] ? $this->formatarCnpj($cliente['cnpj']) : null,
            'state_registration' => $cliente['inscricao_estadual'] ?? null,
            'municipal_registration' => $cliente['inscricao_municipal'] ?? null,

            // Contato
            'email' => $cliente['email'] ?? null,
            'phone' => $cliente['telefone'] ? $this->formatarTelefone($cliente['telefone']) : null,
            'mobile' => $cliente['celular'] ? $this->formatarTelefone($cliente['celular']) : null,

            // Endereço
            'address' => $endereco ? [
                'zipcode' => $this->formatarCep($endereco['cep']),
                'street' => $endereco['logradouro'],
                'number' => $endereco['numero'],
                'complement' => $endereco['complemento'] ?? null,
                'district' => $endereco['bairro'],
                'city' => $endereco['cidade'] ?? 'São Paulo',
                'state' => $endereco['uf'],
                'country' => 'Brasil'
            ] : null,

            // Status
            'status' => $cliente['ativo'] ? 'active' : 'inactive',

            // Custom
            'custom_fields' => [
                'ecletech_id' => (string) $cliente['id'],
                'origem' => 'Ecletech CRM'
            ]
        ], fn($v) => $v !== null);  // Remove nulls
    }

    /**
     * GestaoClick → Ecletech
     */
    public function transformarParaLocal(array $external): array
    {
        return array_filter([
            'tipo_pessoa' => $external['person_type'] === 'individual' ? 'PF' : 'PJ',

            // PF
            'nome' => $external['name'],
            'cpf' => $external['document'] ? $this->limpar($external['document']) : null,
            'data_nascimento' => $external['birth_date'] ?? null,

            // PJ
            'razao_social' => $external['company_name'] ?? null,
            'cnpj' => $external['company_document'] ? $this->limpar($external['company_document']) : null,
            'inscricao_estadual' => $external['state_registration'] ?? null,
            'inscricao_municipal' => $external['municipal_registration'] ?? null,

            // Contato
            'email' => $external['email'] ?? null,
            'telefone' => $external['phone'] ? $this->limpar($external['phone']) : null,
            'celular' => $external['mobile'] ? $this->limpar($external['mobile']) : null,

            // Status
            'ativo' => $external['status'] === 'active',

            // Endereço (separado)
            '_endereco' => isset($external['address']) ? [
                'tipo_endereco_id' => 1,
                'principal' => true,
                'cep' => $this->limpar($external['address']['zipcode']),
                'logradouro' => $external['address']['street'],
                'numero' => $external['address']['number'],
                'complemento' => $external['address']['complement'] ?? null,
                'bairro' => $external['address']['district'],
                'uf' => $external['address']['state']
            ] : null,

            // External ID
            'external_id' => $external['id']
        ], fn($v) => $v !== null);
    }

    // --- Helpers ---

    private function obterEnderecoPrincipal(array $enderecos): ?array
    {
        foreach ($enderecos as $end) {
            if ($end['principal'] ?? false) {
                return $end;
            }
        }
        return $enderecos[0] ?? null;
    }

    private function formatarCpf(string $cpf): string
    {
        $limpo = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $limpo);
    }

    private function formatarCnpj(string $cnpj): string
    {
        $limpo = preg_replace('/\D/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $limpo);
    }

    private function formatarTelefone(string $tel): string
    {
        $limpo = preg_replace('/\D/', '', $tel);

        if (strlen($limpo) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $limpo);
        } elseif (strlen($limpo) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $limpo);
        }

        return $tel;
    }

    private function formatarCep(string $cep): string
    {
        $limpo = preg_replace('/\D/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $limpo);
    }

    private function limpar(string $str): string
    {
        return preg_replace('/\D/', '', $str);
    }
}
```

---

**Documento:** CRM_ESTRUTURA_DADOS.md
**Versão:** 1.0
**Data:** Janeiro 2025
