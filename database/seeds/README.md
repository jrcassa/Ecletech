# Sistema de Seeds - Ecletech

Sistema completo para popular o banco de dados com dados fake/fictícios usando a biblioteca FakerPHP.

## 📋 Sumário

- [Visão Geral](#visão-geral)
- [Instalação](#instalação)
- [Uso Básico](#uso-básico)
- [Seeders Disponíveis](#seeders-disponíveis)
- [Exemplos de Uso](#exemplos-de-uso)
- [Personalização](#personalização)
- [Estrutura de Arquivos](#estrutura-de-arquivos)

## 🎯 Visão Geral

O sistema de seeds permite popular o banco de dados com dados realistas para:
- **Desenvolvimento**: Testar funcionalidades com dados variados
- **Demonstrações**: Apresentar o sistema com dados profissionais
- **Testes**: Validar comportamentos com grandes volumes de dados
- **Treinamento**: Treinar usuários com dados fictícios

## 📦 Instalação

As dependências já foram instaladas. Para reinstalar:

```bash
composer require --dev fakerphp/faker
```

## 🚀 Uso Básico

### Executar todos os seeders

```bash
php executar_seeds.php
```

Este comando irá popular:
- Estados e Cidades brasileiras
- 20 Colaboradores
- 50 Clientes (pessoas físicas e jurídicas)
- 30 Fornecedores
- 100 Produtos
- 100 Vendas (com itens, pagamentos e endereços)

### Executar um seeder específico

```bash
php executar_seeds.php --seeder=ClientesSeeder
```

### Definir quantidade de registros

```bash
php executar_seeds.php --seeder=ClientesSeeder --quantidade=200
```

### Ver ajuda

```bash
php executar_seeds.php --help
```

## 📊 Seeders Disponíveis

### 1. EstadosCidadesSeeder

Popula estados e cidades brasileiras.

**O que cria:**
- 27 estados brasileiros
- 150+ cidades principais

**Uso:**
```bash
php executar_seeds.php --seeder=EstadosCidadesSeeder
```

**Nota:** Este seeder NÃO sobrescreve dados existentes. Se já houver estados/cidades, ele pula a execução.

---

### 2. ColaboradoresSeeder

Popula colaboradores/funcionários do sistema.

**O que cria:**
- Colaboradores com dados completos (nome, CPF, email, telefone, etc.)
- Níveis de acesso (Administrador, Gerente, Vendedor, etc.)
- Endereços dos colaboradores
- 20 colaboradores por padrão

**Uso:**
```bash
# 20 colaboradores (padrão)
php executar_seeds.php --seeder=ColaboradoresSeeder

# 50 colaboradores
php executar_seeds.php --seeder=ColaboradoresSeeder --quantidade=50
```

**Dados gerados:**
- Nome completo (brasileiro)
- CPF válido
- Email profissional
- Telefone e celular
- Cargo, setor, salário, comissão
- Data de admissão
- 75% ativos, 25% inativos

---

### 3. ClientesSeeder

Popula clientes (pessoas físicas e jurídicas).

**O que cria:**
- Clientes pessoa física (CPF)
- Clientes pessoa jurídica (CNPJ)
- Contatos dos clientes
- Endereços completos
- 50 clientes por padrão

**Uso:**
```bash
# 50 clientes (padrão)
php executar_seeds.php --seeder=ClientesSeeder

# 100 clientes
php executar_seeds.php --seeder=ClientesSeeder --quantidade=100
```

**Dados gerados:**
- **Pessoa Física**: Nome, CPF, RG, email, telefone, data nascimento, profissão
- **Pessoa Jurídica**: Razão social, CNPJ, IE, email corporativo
- Limite de crédito
- Dia de vencimento
- Status ativo/inativo

---

### 4. FornecedoresSeeder

Popula fornecedores.

**O que cria:**
- Fornecedores (empresas)
- Contatos dos fornecedores
- Endereços comerciais
- 30 fornecedores por padrão

**Uso:**
```bash
# 30 fornecedores (padrão)
php executar_seeds.php --seeder=FornecedoresSeeder

# 80 fornecedores
php executar_seeds.php --seeder=FornecedoresSeeder --quantidade=80
```

**Dados gerados:**
- Razão social e nome fantasia
- CNPJ e IE válidos
- Email, telefone, site
- Categoria (Matéria Prima, Insumos, etc.)
- Prazo de entrega
- Limite de crédito

---

### 5. ProdutosSeeder

Popula produtos com valores e dados fiscais.

**O que cria:**
- Produtos de diversas categorias
- Grupos de produtos
- Valores (custo, preço venda, margem)
- Dados fiscais (ICMS, IPI, PIS, COFINS)
- 100 produtos por padrão

**Uso:**
```bash
# 100 produtos (padrão)
php executar_seeds.php --seeder=ProdutosSeeder

# 500 produtos
php executar_seeds.php --seeder=ProdutosSeeder --quantidade=500
```

**Categorias:**
- Eletrônicos
- Informática
- Escritório
- Papelaria
- Limpeza
- Ferramentas

**Dados gerados:**
- Nome, descrição
- Código interno e código de barras
- NCM, unidade de medida
- Estoque (mínimo, máximo, atual)
- Dimensões e peso
- Preços (custo, venda, promocional)
- Dados fiscais completos

---

### 6. VendasSeeder

Popula vendas completas com itens, pagamentos e endereços.

**O que cria:**
- Vendas vinculadas a clientes e vendedores
- Itens das vendas (produtos)
- Pagamentos parcelados
- Endereços de entrega
- 100 vendas por padrão

**Uso:**
```bash
# 100 vendas (padrão)
php executar_seeds.php --seeder=VendasSeeder

# 500 vendas
php executar_seeds.php --seeder=VendasSeeder --quantidade=500
```

**Requisitos:**
- Clientes cadastrados
- Produtos cadastrados
- Colaboradores cadastrados (opcional)

**Dados gerados:**
- Data da venda (último ano)
- Status (Pendente, Confirmada, Entregue, etc.)
- 1 a 8 itens por venda
- Descontos, acréscimos, frete
- Formas de pagamento variadas
- Parcelamento (1x a 12x)

## 💡 Exemplos de Uso

### Popular banco de dados do zero

```bash
# 1. Popular estados e cidades
php executar_seeds.php --seeder=EstadosCidadesSeeder

# 2. Popular colaboradores
php executar_seeds.php --seeder=ColaboradoresSeeder --quantidade=30

# 3. Popular clientes
php executar_seeds.php --seeder=ClientesSeeder --quantidade=100

# 4. Popular fornecedores
php executar_seeds.php --seeder=FornecedoresSeeder --quantidade=50

# 5. Popular produtos
php executar_seeds.php --seeder=ProdutosSeeder --quantidade=300

# 6. Popular vendas
php executar_seeds.php --seeder=VendasSeeder --quantidade=200
```

### Executar todos de uma vez

```bash
php executar_seeds.php
```

### Popular apenas para demonstração rápida

```bash
php executar_seeds.php --quantidade=10
```

Isso criará:
- 10 colaboradores
- 10 clientes
- 10 fornecedores
- 10 produtos
- 10 vendas

## 🎨 Personalização

### Criar um novo seeder

1. Crie um arquivo em `database/seeds/MeuSeeder.php`
2. Estenda a classe `BaseSeeder`
3. Implemente o método `run()`

```php
<?php

namespace Database\Seeds;

require_once __DIR__ . '/BaseSeeder.php';

class MeuSeeder extends BaseSeeder
{
    private int $quantidade = 50;

    public function run(): void
    {
        $this->info("Iniciando meu seeder...");

        for ($i = 0; $i < $this->quantidade; $i++) {
            // Usar $this->faker para gerar dados
            $dados = [
                'nome' => $this->faker->name,
                'email' => $this->faker->email,
                'cadastrado_em' => date('Y-m-d H:i:s'),
            ];

            $this->db->inserir('minha_tabela', $dados);
        }

        $this->success("{$this->quantidade} registros criados!");
    }

    public function setQuantidade(int $quantidade): self
    {
        $this->quantidade = $quantidade;
        return $this;
    }
}
```

4. Registre no `executar_seeds.php`

### Métodos úteis do BaseSeeder

```php
// Mensagens
$this->info("Mensagem informativa");
$this->success("Mensagem de sucesso");
$this->error("Mensagem de erro");
$this->warning("Mensagem de aviso");

// Banco de dados
$this->truncate('tabela'); // Limpa tabela
$this->count('tabela'); // Conta registros
$this->tableExists('tabela'); // Verifica se tabela existe

// Geradores
$this->generateCPF(); // CPF válido
$this->generateCNPJ(); // CNPJ válido
$this->generateCEP(); // CEP válido
$this->generateUUID(); // UUID v4
$this->randomDate('-1 year', 'now'); // Data aleatória

// Faker (pt_BR)
$this->faker->name; // Nome
$this->faker->email; // Email
$this->faker->cpf; // CPF
$this->faker->company; // Empresa
$this->faker->address; // Endereço
// ... e muito mais
```

## 📁 Estrutura de Arquivos

```
database/
├── seeds/
│   ├── BaseSeeder.php              # Classe base
│   ├── EstadosCidadesSeeder.php    # Estados e cidades
│   ├── ColaboradoresSeeder.php     # Colaboradores
│   ├── ClientesSeeder.php          # Clientes
│   ├── FornecedoresSeeder.php      # Fornecedores
│   ├── ProdutosSeeder.php          # Produtos
│   ├── VendasSeeder.php            # Vendas
│   └── README.md                   # Este arquivo
└── migrations/                     # Migrações SQL

executar_seeds.php                  # Script principal
```

## 🔧 Problemas Comuns

### Erro: "Não há clientes cadastrados"

Execute os seeders na ordem correta:
1. EstadosCidadesSeeder (opcional mas recomendado)
2. ColaboradoresSeeder
3. ClientesSeeder
4. FornecedoresSeeder
5. ProdutosSeeder
6. VendasSeeder

### Erro de conexão com banco

Verifique o arquivo `.env`:
```env
DB_HOST="localhost"
DB_PORTA="3306"
DB_NOME="ecletech"
DB_USUARIO="root"
DB_SENHA=""
```

### Executar novamente os seeds

Os seeders inserem novos dados sem limpar os existentes. Para limpar:

```sql
-- Atenção: Isso apagará TODOS os dados
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE vendas_pagamentos;
TRUNCATE TABLE vendas_itens;
TRUNCATE TABLE vendas_enderecos;
TRUNCATE TABLE vendas;
TRUNCATE TABLE produto_fiscal;
TRUNCATE TABLE produto_valores;
TRUNCATE TABLE produtos;
TRUNCATE TABLE clientes_contatos;
TRUNCATE TABLE clientes_enderecos;
TRUNCATE TABLE clientes;
TRUNCATE TABLE fornecedores_contatos;
TRUNCATE TABLE fornecedores_enderecos;
TRUNCATE TABLE fornecedores;
TRUNCATE TABLE colaboradores;
SET FOREIGN_KEY_CHECKS = 1;
```

## 📚 Recursos

- [FakerPHP Documentation](https://fakerphp.github.io/)
- [Ecletech - Documentação](../README.md)

## 🤝 Contribuindo

Para adicionar novos seeders ou melhorar os existentes:
1. Crie/edite o seeder em `database/seeds/`
2. Teste localmente
3. Atualize este README se necessário
4. Faça commit das alterações

---

**Desenvolvido para Ecletech** 🚀
