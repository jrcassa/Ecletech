# Estrutura de Dados - Pagamentos (Contas a Pagar/Receber)

## 📋 Visão Geral

Esta documentação define a estrutura completa para **criação e listagem de pagamentos** no sistema Ecletech, incluindo tanto **contas a pagar** (despesas com fornecedores) quanto **contas a receber** (receitas de clientes).

---

## 🔑 Regra Principal: External ID

**IMPORTANTE:** Todos os campos que terminam com `_id` DEVEM ter um campo correspondente `_external_id` para permitir sincronização com sistemas externos (Bling, Omie, etc.).

### Padrão de Nomenclatura

```
produto_id          → produto_external_id
cliente_id          → cliente_external_id
fornecedor_id       → fornecedor_external_id
plano_contas_id     → plano_contas_external_id
```

---

## 📊 Estrutura Completa de Campos

### 🆔 Identificação

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | string | Não (listagem) | ID interno do pagamento no sistema |
| `external_id` | string | Não | ID do pagamento em sistema externo |
| `codigo` | string | Não | Código sequencial ou identificador único |

### 📝 Descrição

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `descricao` | string | ✅ Sim | Descrição detalhada do pagamento (3-500 caracteres) |

### 💰 Valores e Cálculos

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `valor` | string (decimal) | ✅ Sim | Valor original (formato: "0000.00") |
| `juros` | string (decimal) | Não | Valor de juros aplicado |
| `desconto` | string (decimal) | Não | Valor de desconto aplicado |
| `taxa_banco` | string (decimal) | Não | Taxa bancária cobrada |
| `taxa_operadora` | string (decimal) | Não | Taxa da operadora de cartão |
| `valor_total` | string (decimal) | ✅ Sim | Valor final calculado |

**Fórmula de Cálculo:**
```
valor_total = valor + juros - desconto - taxa_banco - taxa_operadora
```

### 📅 Datas

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `data_vencimento` | date | ✅ Sim | Data de vencimento (YYYY-MM-DD) |
| `data_liquidacao` | date | Condicional* | Data em que foi pago (YYYY-MM-DD) |
| `data_competencia` | date | Não | Data de competência contábil |
| `cadastrado_em` | datetime | Não (auto) | Data/hora de cadastro |
| `modificado_em` | datetime | Não (auto) | Data/hora da última modificação |

*Obrigatório quando `liquidado = "1"`

### 📚 Plano de Contas

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `plano_contas_id` | string | Recomendado | ID do plano de contas |
| `plano_contas_external_id` | string | Recomendado | External ID do plano de contas |
| `nome_plano_conta` | string | Não | Nome da conta (ex: "Aluguel", "Vendas") |

### 🎯 Centro de Custo

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `centro_custo_id` | string | Não | ID do centro de custo |
| `centro_custo_external_id` | string | Não | External ID do centro de custo |
| `nome_centro_custo` | string | Não | Nome do centro de custo |

### 🏦 Conta Bancária

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `conta_bancaria_id` | string | Recomendado | ID da conta bancária |
| `conta_bancaria_external_id` | string | Recomendado | External ID da conta bancária |
| `nome_conta_bancaria` | string | Não | Nome da conta (ex: "Conta Itaú") |

### 💳 Forma de Pagamento

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `forma_pagamento_id` | string | Recomendado | ID da forma de pagamento |
| `forma_pagamento_external_id` | string | Recomendado | External ID da forma de pagamento |
| `nome_forma_pagamento` | string | Não | Nome (ex: "PIX", "Boleto") |

### 👥 Entidade Relacionada

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `entidade` | string (enum) | ✅ Sim | Tipo: `C`=Cliente, `F`=Fornecedor, `T`=Transportadora, `U`=Funcionário |

#### 🛒 Cliente (quando `entidade = "C"`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `cliente_id` | string | Sim (se C) | ID do cliente |
| `cliente_external_id` | string | Sim (se C) | External ID do cliente |
| `nome_cliente` | string | Não | Nome do cliente |

#### 📦 Fornecedor (quando `entidade = "F"`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `fornecedor_id` | string | Sim (se F) | ID do fornecedor |
| `fornecedor_external_id` | string | Sim (se F) | External ID do fornecedor |
| `nome_fornecedor` | string | Não | Nome do fornecedor |

#### 🚚 Transportadora (quando `entidade = "T"`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `transportadora_id` | string | Sim (se T) | ID da transportadora |
| `transportadora_external_id` | string | Sim (se T) | External ID da transportadora |
| `nome_transportadora` | string | Não | Nome da transportadora |

#### 👔 Funcionário (quando `entidade = "U"`)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `funcionario_id` | string | Sim (se U) | ID do funcionário |
| `funcionario_external_id` | string | Sim (se U) | External ID do funcionário |
| `nome_funcionario` | string | Não | Nome do funcionário |

### ✅ Status e Liquidação

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `liquidado` | string (enum) | ✅ Sim | `0`=Pendente, `1`=Liquidado/Pago |

### 👤 Usuário Responsável

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `usuario_id` | string | Não (auto) | ID do usuário que cadastrou |
| `usuario_external_id` | string | Não | External ID do usuário |
| `nome_usuario` | string | Não | Nome do usuário |

### 🏢 Loja/Filial

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `loja_id` | string | Não | ID da loja/filial |
| `loja_external_id` | string | Não | External ID da loja |
| `nome_loja` | string | Não | Nome da loja (ex: "Matriz") |

### 🏷️ Atributos Customizados

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `atributos` | array | Não | Lista de atributos customizados |

**Estrutura do item de atributo:**
```json
{
  "chave": "nome_do_atributo",
  "valor": "valor_do_atributo"
}
```

---

## 📋 Mapeamento Completo ID → External ID

| Campo ID | Campo External ID | Tabela Referência | Descrição |
|----------|-------------------|-------------------|-----------|
| `plano_contas_id` | `plano_contas_external_id` | `plano_de_contas` | Plano de contas contábil |
| `centro_custo_id` | `centro_custo_external_id` | `centros_custo` | Centro de custo |
| `conta_bancaria_id` | `conta_bancaria_external_id` | `contas_bancarias` | Conta bancária |
| `forma_pagamento_id` | `forma_pagamento_external_id` | `forma_de_pagamento` | Forma de pagamento |
| `cliente_id` | `cliente_external_id` | `clientes` | Cliente (entidade=C) |
| `fornecedor_id` | `fornecedor_external_id` | `fornecedores` | Fornecedor (entidade=F) |
| `transportadora_id` | `transportadora_external_id` | `transportadoras` | Transportadora (entidade=T) |
| `funcionario_id` | `funcionario_external_id` | `colaboradores` | Funcionário (entidade=U) |
| `usuario_id` | `usuario_external_id` | `colaboradores` | Usuário responsável |
| `loja_id` | `loja_external_id` | `lojas` | Loja/Filial |

---

## 📌 Exemplos de Uso

### Exemplo 1: Criar Conta a RECEBER (Venda para Cliente)

```json
{
  "descricao": "Compra de TV 33",
  "data_vencimento": "2020-01-25",
  "valor": "1599.99",
  "juros": "2.00",
  "desconto": "1.00",
  "taxa_banco": "0.00",
  "taxa_operadora": "0.00",
  "valor_total": "1600.99",

  "centro_custo_id": "1",
  "centro_custo_external_id": "CC001",
  "nome_centro_custo": "Centro de Custo 01",

  "conta_bancaria_id": "1",
  "conta_bancaria_external_id": "CB001",
  "nome_conta_bancaria": "Conta padrão",

  "forma_pagamento_id": "640517",
  "forma_pagamento_external_id": "FP001",
  "nome_forma_pagamento": "Dinheiro à Vista",

  "entidade": "C",
  "cliente_id": "6",
  "cliente_external_id": "CLI006",
  "nome_cliente": "Jarvis Stark",

  "liquidado": "1",
  "data_liquidacao": "2020-01-25",
  "data_competencia": "2020-01-25",

  "plano_contas_id": "2512",
  "plano_contas_external_id": "PC2512",
  "nome_plano_conta": "Vendas de produtos"
}
```

### Exemplo 2: Criar Conta a PAGAR (Despesa com Fornecedor)

```json
{
  "codigo": "37602",
  "descricao": "ALUGUEL IMOVEL - RUA FIRMINO SOARES 151 - (ATE MES 04-2027 - VER OBS)",
  "valor": "3000.00",
  "juros": "0.00",
  "desconto": "68.56",
  "taxa_banco": "0.00",
  "taxa_operadora": "0.00",
  "valor_total": "2931.44",

  "plano_contas_id": "10896691",
  "plano_contas_external_id": "PC10896691",
  "nome_plano_conta": "Aluguel",

  "centro_custo_id": "",
  "centro_custo_external_id": "",
  "nome_centro_custo": "",

  "conta_bancaria_id": "237504",
  "conta_bancaria_external_id": "CB237504",
  "nome_conta_bancaria": "Conta Itaú",

  "forma_pagamento_id": "1752825",
  "forma_pagamento_external_id": "FP1752825",
  "nome_forma_pagamento": "Transferencia bancaria",

  "entidade": "F",
  "fornecedor_id": "1107555",
  "fornecedor_external_id": "FOR1107555",
  "nome_fornecedor": "AUREA LUCIA DA SILVA RODRIGUES",

  "cliente_id": "",
  "cliente_external_id": "",
  "nome_cliente": "",

  "transportadora_id": "",
  "transportadora_external_id": "",
  "nome_transportadora": "",

  "funcionario_id": "",
  "funcionario_external_id": "",
  "nome_funcionario": "",

  "liquidado": "1",
  "data_vencimento": "2025-11-01",
  "data_liquidacao": "2025-11-03",
  "data_competencia": "2025-11-01",

  "usuario_id": "326109",
  "usuario_external_id": "USR326109",
  "nome_usuario": "Adiel Hebo",

  "loja_id": "178114",
  "loja_external_id": "LJ178114",
  "nome_loja": "Matriz",

  "atributos": []
}
```

### Exemplo 3: Resposta de Listagem

```json
{
  "sucesso": true,
  "dados": [
    {
      "id": "484696308",
      "external_id": "PAG484696308",
      "codigo": "37602",
      "descricao": "ALUGUEL IMOVEL - RUA FIRMINO SOARES 151",
      "valor": "3000.00",
      "juros": "0.00",
      "desconto": "68.56",
      "taxa_banco": "0.00",
      "taxa_operadora": "0.00",
      "valor_total": "2931.44",

      "plano_contas_id": "10896691",
      "plano_contas_external_id": "PC10896691",
      "nome_plano_conta": "Aluguel",

      "conta_bancaria_id": "237504",
      "conta_bancaria_external_id": "CB237504",
      "nome_conta_bancaria": "Conta Itaú",

      "forma_pagamento_id": "1752825",
      "forma_pagamento_external_id": "FP1752825",
      "nome_forma_pagamento": "Transferencia bancaria",

      "entidade": "F",
      "fornecedor_id": "1107555",
      "fornecedor_external_id": "FOR1107555",
      "nome_fornecedor": "AUREA LUCIA DA SILVA RODRIGUES",

      "liquidado": "1",
      "data_vencimento": "2025-11-01",
      "data_liquidacao": "2025-11-03",
      "data_competencia": "2025-11-01",

      "usuario_id": "326109",
      "usuario_external_id": "USR326109",
      "nome_usuario": "Adiel Hebo",

      "loja_id": "178114",
      "loja_external_id": "LJ178114",
      "nome_loja": "Matriz",

      "cadastrado_em": "2025-10-01 00:32:06",
      "modificado_em": "2025-11-03 14:27:20",
      "atributos": []
    }
  ],
  "paginacao": {
    "pagina_atual": 1,
    "por_pagina": 20,
    "total_registros": 1,
    "total_paginas": 1
  }
}
```

---

## ⚙️ Regras de Negócio

### 1. Cálculo de Valor Total

```
valor_total = valor + juros - desconto - taxa_banco - taxa_operadora
```

### 2. Tipos de Entidade

| Código | Tipo | Uso |
|--------|------|-----|
| `C` | Cliente | Contas a RECEBER (receitas/vendas) |
| `F` | Fornecedor | Contas a PAGAR (despesas/compras) |
| `T` | Transportadora | Pagamentos de frete |
| `U` | Funcionário | Folha de pagamento ou reembolsos |

### 3. Status de Liquidação

| Valor | Status | Descrição |
|-------|--------|-----------|
| `0` | Pendente | Pagamento não realizado |
| `1` | Liquidado | Pagamento realizado (requer `data_liquidacao`) |

### 4. Campos Obrigatórios por Entidade

**Quando `entidade = "C"` (Cliente):**
- `cliente_id`
- `cliente_external_id`

**Quando `entidade = "F"` (Fornecedor):**
- `fornecedor_id`
- `fornecedor_external_id`

**Quando `entidade = "T"` (Transportadora):**
- `transportadora_id`
- `transportadora_external_id`

**Quando `entidade = "U"` (Funcionário):**
- `funcionario_id`
- `funcionario_external_id`

### 5. Soft Delete

Ao deletar um pagamento, usar **soft delete** (`deletado_em`) para manter integridade referencial e histórico.

---

## 🔗 Integrações Existentes no Sistema

O sistema Ecletech já possui as seguintes estruturas implementadas:

- ✅ **Forma de Pagamento** (`forma_de_pagamento`)
- ✅ **Conta Bancária** (`contas_bancarias`)
- ✅ **Plano de Contas** (`plano_de_contas`)

**Próximos passos:**
- ⏳ Implementar tabela de **Pagamentos** (contas_pagar_receber)
- ⏳ Implementar tabela de **Centro de Custo**
- ⏳ Integrar com módulos de **Clientes** e **Fornecedores**

---

## 📚 Referências

- [Estrutura JSON Schema](./estrutura-pagamentos.json)
- Migrations existentes: `043`, `044`, `046`
- Padrão: MVC + ACL + Soft Delete + Auditoria
