# API de Gerenciamento de Frota

## Descrição

A API de Frota permite gerenciar veículos da frota da empresa, incluindo cadastro, atualização, consulta e remoção de veículos. O sistema implementa controle de acesso baseado em permissões (ACL) e auditoria completa de todas as operações.

## Estrutura da Tabela

```sql
frotas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(100) NOT NULL,
    tipo                ENUM('motocicleta', 'automovel', 'caminhonete', 'caminhao', 'onibus', 'van'),
    placa               VARCHAR(8) NOT NULL UNIQUE,
    status              ENUM('ativo', 'inativo', 'manutencao', 'reservado', 'vendido'),
    marca               VARCHAR(50),
    modelo              VARCHAR(50),
    ano_fabricacao      YEAR,
    ano_modelo          YEAR,
    cor                 VARCHAR(30),
    chassi              VARCHAR(17) UNIQUE,
    renavam             VARCHAR(11) UNIQUE,
    quilometragem       INT UNSIGNED DEFAULT 0,
    capacidade_tanque   DECIMAL(5,2),
    data_aquisicao      DATE,
    valor_aquisicao     DECIMAL(10,2),
    observacoes         TEXT,
    criado_em           DATETIME NOT NULL,
    atualizado_em       DATETIME,
    deletado_em         DATETIME,
    ativo               BOOLEAN DEFAULT TRUE
)
```

## Permissões

O sistema implementa as seguintes permissões:

- `frota.visualizar` - Permite visualizar a lista de veículos e detalhes
- `frota.criar` - Permite cadastrar novos veículos na frota
- `frota.editar` - Permite editar informações dos veículos
- `frota.deletar` - Permite remover veículos da frota

## Como Executar a Migration

```bash
# Conecte-se ao MySQL
mysql -u seu_usuario -p seu_banco_de_dados

# Execute a migration
source database/migrations/009_criar_tabela_frota.sql
```

Ou via PHP:

```php
$db = BancoDados::obterInstancia();
$sql = file_get_contents(__DIR__ . '/migrations/009_criar_tabela_frota.sql');
$db->obterConexao()->exec($sql);
```

## Endpoints da API

### Base URL
```
/api/frota
```

Todos os endpoints requerem autenticação via JWT e as permissões apropriadas.

### Headers Necessários

```http
Authorization: Bearer {access_token}
Content-Type: application/json
X-CSRF-Token: {csrf_token}
```

---

## 1. Listar Veículos

**Endpoint:** `GET /api/frota`

**Permissão:** `frota.visualizar`

**Parâmetros de Query:**

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| pagina | integer | Número da página (padrão: 1) | `?pagina=1` |
| por_pagina | integer | Itens por página (padrão: 20) | `?por_pagina=20` |
| busca | string | Busca por nome, placa, marca ou modelo | `?busca=civic` |
| tipo | string | Filtrar por tipo | `?tipo=automovel` |
| status | string | Filtrar por status | `?status=ativo` |
| marca | string | Filtrar por marca | `?marca=Honda` |
| modelo | string | Filtrar por modelo | `?modelo=Civic` |
| ativo | boolean | Filtrar por ativo (0 ou 1) | `?ativo=1` |
| ordenacao | string | Campo para ordenação | `?ordenacao=nome` |
| direcao | string | Direção da ordenação (ASC/DESC) | `?direcao=ASC` |

**Exemplo de Requisição:**

```bash
curl -X GET "http://localhost/api/frota?pagina=1&por_pagina=20&status=ativo" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token"
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Veículos da frota listados com sucesso",
  "codigo": 200,
  "dados": {
    "itens": [
      {
        "id": 1,
        "nome": "Honda Civic 2020",
        "tipo": "automovel",
        "placa": "ABC1D23",
        "status": "ativo",
        "marca": "Honda",
        "modelo": "Civic",
        "ano_fabricacao": 2020,
        "ano_modelo": 2020,
        "cor": "Prata",
        "quilometragem": 15000,
        "criado_em": "2025-01-15 10:30:00",
        "atualizado_em": "2025-01-20 14:20:00"
      }
    ],
    "paginacao": {
      "total": 50,
      "por_pagina": 20,
      "pagina_atual": 1,
      "total_paginas": 3,
      "proxima_pagina": 2,
      "pagina_anterior": null
    }
  }
}
```

---

## 2. Buscar Veículo por ID

**Endpoint:** `GET /api/frota/{id}`

**Permissão:** `frota.visualizar`

**Exemplo de Requisição:**

```bash
curl -X GET "http://localhost/api/frota/1" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token"
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Veículo encontrado",
  "codigo": 200,
  "dados": {
    "id": 1,
    "nome": "Honda Civic 2020",
    "tipo": "automovel",
    "placa": "ABC1D23",
    "status": "ativo",
    "marca": "Honda",
    "modelo": "Civic",
    "ano_fabricacao": 2020,
    "ano_modelo": 2020,
    "cor": "Prata",
    "chassi": "9BWZZZ377VT004251",
    "renavam": "12345678901",
    "quilometragem": 15000,
    "capacidade_tanque": 50.00,
    "data_aquisicao": "2020-01-15",
    "valor_aquisicao": 85000.00,
    "observacoes": "Veículo em perfeito estado",
    "criado_em": "2025-01-15 10:30:00",
    "atualizado_em": "2025-01-20 14:20:00",
    "ativo": true
  }
}
```

---

## 3. Criar Veículo

**Endpoint:** `POST /api/frota`

**Permissão:** `frota.criar`

**Campos Obrigatórios:**

- `nome` (string, 3-100 caracteres) - Nome/identificação do veículo
- `tipo` (string) - Tipo do veículo: `motocicleta`, `automovel`, `caminhonete`, `caminhao`, `onibus`, `van`
- `placa` (string) - Placa no formato Mercosul (ABC1D23) ou antigo (ABC1234)

**Campos Opcionais:**

- `status` (string) - Status: `ativo`, `inativo`, `manutencao`, `reservado`, `vendido` (padrão: `ativo`)
- `marca` (string, até 50 caracteres) - Fabricante do veículo
- `modelo` (string, até 50 caracteres) - Modelo do veículo
- `ano_fabricacao` (integer) - Ano de fabricação
- `ano_modelo` (integer) - Ano do modelo
- `cor` (string, até 30 caracteres) - Cor do veículo
- `chassi` (string, 17 caracteres) - Número do chassi (VIN)
- `renavam` (string, 11 dígitos) - Código RENAVAM
- `quilometragem` (integer) - Quilometragem atual
- `capacidade_tanque` (decimal) - Capacidade do tanque em litros
- `data_aquisicao` (date, YYYY-MM-DD) - Data de aquisição
- `valor_aquisicao` (decimal) - Valor pago na aquisição
- `observacoes` (text) - Observações gerais

**Exemplo de Requisição:**

```bash
curl -X POST "http://localhost/api/frota" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Honda Civic 2020",
    "tipo": "automovel",
    "placa": "ABC1D23",
    "status": "ativo",
    "marca": "Honda",
    "modelo": "Civic",
    "ano_fabricacao": 2020,
    "ano_modelo": 2020,
    "cor": "Prata",
    "chassi": "9BWZZZ377VT004251",
    "renavam": "12345678901",
    "quilometragem": 0,
    "capacidade_tanque": 50.00,
    "data_aquisicao": "2020-01-15",
    "valor_aquisicao": 85000.00,
    "observacoes": "Veículo novo adquirido via licitação"
  }'
```

**Resposta de Sucesso (201):**

```json
{
  "sucesso": true,
  "mensagem": "Veículo cadastrado na frota com sucesso",
  "codigo": 201,
  "dados": {
    "id": 1,
    "nome": "Honda Civic 2020",
    "tipo": "automovel",
    "placa": "ABC1D23",
    ...
  }
}
```

---

## 4. Atualizar Veículo

**Endpoint:** `PUT /api/frota/{id}`

**Permissão:** `frota.editar`

**Campos:** Todos os campos são opcionais, envie apenas os que deseja atualizar.

**Exemplo de Requisição:**

```bash
curl -X PUT "http://localhost/api/frota/1" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "manutencao",
    "quilometragem": 15500,
    "observacoes": "Veículo em manutenção preventiva"
  }'
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Veículo atualizado com sucesso",
  "codigo": 200,
  "dados": {
    "id": 1,
    "nome": "Honda Civic 2020",
    "status": "manutencao",
    "quilometragem": 15500,
    ...
  }
}
```

---

## 5. Deletar Veículo

**Endpoint:** `DELETE /api/frota/{id}`

**Permissão:** `frota.deletar`

**Nota:** Esta operação realiza um soft delete (exclusão lógica). O veículo não é removido fisicamente do banco de dados.

**Exemplo de Requisição:**

```bash
curl -X DELETE "http://localhost/api/frota/1" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token"
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Veículo removido da frota com sucesso",
  "codigo": 200,
  "dados": null
}
```

---

## 6. Atualizar Quilometragem

**Endpoint:** `PATCH /api/frota/{id}/quilometragem`

**Permissão:** `frota.editar`

**Campos Obrigatórios:**

- `quilometragem` (integer) - Nova quilometragem do veículo

**Exemplo de Requisição:**

```bash
curl -X PATCH "http://localhost/api/frota/1/quilometragem" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token" \
  -H "Content-Type: application/json" \
  -d '{
    "quilometragem": 16000
  }'
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Quilometragem atualizada com sucesso",
  "codigo": 200,
  "dados": {
    "id": 1,
    "quilometragem": 16000,
    ...
  }
}
```

---

## 7. Atualizar Status

**Endpoint:** `PATCH /api/frota/{id}/status`

**Permissão:** `frota.editar`

**Campos Obrigatórios:**

- `status` (string) - Novo status: `ativo`, `inativo`, `manutencao`, `reservado`, `vendido`

**Exemplo de Requisição:**

```bash
curl -X PATCH "http://localhost/api/frota/1/status" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "ativo"
  }'
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Status do veículo atualizado com sucesso",
  "codigo": 200,
  "dados": {
    "id": 1,
    "status": "ativo",
    ...
  }
}
```

---

## 8. Obter Estatísticas

**Endpoint:** `GET /api/frota/estatisticas`

**Permissão:** `frota.visualizar`

**Exemplo de Requisição:**

```bash
curl -X GET "http://localhost/api/frota/estatisticas" \
  -H "Authorization: Bearer seu_token_jwt" \
  -H "X-CSRF-Token: seu_csrf_token"
```

**Resposta de Sucesso (200):**

```json
{
  "sucesso": true,
  "mensagem": "Estatísticas da frota obtidas com sucesso",
  "codigo": 200,
  "dados": {
    "total_ativos": 45,
    "por_tipo": [
      {
        "tipo": "automovel",
        "total": 25
      },
      {
        "tipo": "caminhonete",
        "total": 10
      },
      {
        "tipo": "motocicleta",
        "total": 10
      }
    ],
    "por_status": [
      {
        "status": "ativo",
        "total": 35
      },
      {
        "status": "manutencao",
        "total": 8
      },
      {
        "status": "reservado",
        "total": 2
      }
    ],
    "em_manutencao": 8
  }
}
```

---

## Códigos de Erro

| Código | Descrição |
|--------|-----------|
| 400 | Requisição inválida |
| 401 | Não autenticado |
| 403 | Sem permissão |
| 404 | Veículo não encontrado |
| 409 | Conflito (placa, chassi ou RENAVAM já cadastrado) |
| 422 | Dados de validação inválidos |
| 429 | Muitas requisições (rate limit) |
| 500 | Erro interno do servidor |

**Exemplo de Erro de Validação (422):**

```json
{
  "sucesso": false,
  "mensagem": "Dados inválidos",
  "codigo": 422,
  "erros": {
    "nome": ["O campo nome é obrigatório"],
    "placa": ["O campo placa deve ser uma placa válida (formato Mercosul ou antigo)"],
    "tipo": ["O campo tipo contém um valor inválido"]
  }
}
```

**Exemplo de Erro de Conflito (409):**

```json
{
  "sucesso": false,
  "mensagem": "Placa já cadastrada no sistema",
  "codigo": 409
}
```

---

## Validações Implementadas

### Placa
- Formato Mercosul: ABC1D23 (3 letras + 1 número + 1 letra + 2 números)
- Formato Antigo: ABC1234 (3 letras + 4 números)

### Chassi
- Exatamente 17 caracteres alfanuméricos
- Não pode conter as letras I, O ou Q (para evitar confusão com números)

### RENAVAM
- Exatamente 11 dígitos
- Validação do dígito verificador

---

## Auditoria

Todas as operações (criar, atualizar, deletar) são registradas no sistema de auditoria com:
- ID do usuário que realizou a operação
- Data e hora da operação
- Dados anteriores e novos (para operações de atualização)
- Tipo de operação realizada

---

## Como Usar o Model

```php
use App\Models\Frota\ModelFrota;

$model = new ModelFrota();

// Listar veículos (tabela: frotas)
$veiculos = $model->listar(['status' => 'ativo', 'tipo' => 'automovel']);

// Buscar por ID
$veiculo = $model->buscarPorId(1);

// Buscar por placa
$veiculo = $model->buscarPorPlaca('ABC1D23');

// Criar veículo
$id = $model->criar([
    'nome' => 'Honda Civic 2020',
    'tipo' => 'automovel',
    'placa' => 'ABC1D23',
    'status' => 'ativo',
    'colaborador_id' => 1
]);

// Atualizar veículo
$model->atualizar(1, ['quilometragem' => 16000], $colaboradorId);

// Deletar veículo (soft delete)
$model->deletar(1, $colaboradorId);

// Verificar duplicatas
if ($model->placaExiste('ABC1D23')) {
    echo "Placa já cadastrada";
}

// Obter estatísticas
$stats = $model->obterEstatisticas();
```

---

## Segurança

- Todas as rotas requerem autenticação via JWT
- Controle de acesso baseado em permissões (ACL)
- Validação de entrada para prevenir XSS e SQL Injection
- Proteção CSRF obrigatória
- Rate limiting aplicado
- Soft delete para preservar histórico
- Auditoria completa de todas as operações
