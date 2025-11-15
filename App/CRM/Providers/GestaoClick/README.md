# Provider GestãoClick - Guia de Configuração

## 📋 Sobre

Este provider foi criado com uma estrutura **genérica** baseada em padrões REST comuns. Você deve ajustá-lo conforme a documentação oficial da API Gestão Click.

## 🔑 Acesso à Documentação

1. **Verificar addon API**: Acesse seu painel Gestão Click e confirme que o addon "API" está ativo
2. **Obter Token**: Gere seu token de acesso no painel
3. **Acessar docs**: A documentação completa está em https://gestaoclick.docs.apiary.io/ (requer login/token)
4. **Docs internas**: Verifique se há documentação dentro do próprio ERP

## 📁 Arquivos para Ajustar

```
App/CRM/Providers/GestaoClick/
├── config.php                    # ⚠️ AJUSTAR PRIMEIRO
├── GestaoClickProvider.php       # ⚠️ Verificar autenticação
└── Handlers/
    ├── ClienteHandler.php        # ⚠️ Ajustar campos
    ├── ProdutoHandler.php        # ⚠️ Ajustar campos
    ├── VendaHandler.php          # ⚠️ Ajustar campos
    └── AtividadeHandler.php      # ⚠️ Ajustar campos
```

---

## 1️⃣ Ajustar `config.php`

### URL Base da API

```php
// EXEMPLO - Ajustar conforme documentação
'api_base_url' => 'https://api.gestaoclick.com/v1',

// Pode ser algo como:
// 'https://api.gestaoclick.com.br/api/v1'
// 'https://gestaoclick.com/api'
// 'https://app.gestaoclick.com/api'
```

### Autenticação

Verifique na documentação qual método é usado:

```php
// Opção 1: Bearer Token (mais comum)
'auth' => [
    'type' => 'bearer',
    'header_name' => 'Authorization',
    'header_format' => 'Bearer {token}',
],

// Opção 2: API Key no header
'auth' => [
    'type' => 'api_key',
    'header_name' => 'X-API-Key',
    'header_format' => '{token}',
],

// Opção 3: Token simples
'auth' => [
    'type' => 'token',
    'header_name' => 'X-Auth-Token',
    'header_format' => '{token}',
],
```

### Endpoints

Ajuste os endpoints conforme documentação:

```php
'endpoints' => [
    'cliente' => [
        'listar' => '/clientes',    // ou /customers, /persons
        'criar' => '/clientes',
        'atualizar' => '/clientes/{id}',
        'buscar' => '/clientes/{id}',
        'deletar' => '/clientes/{id}',
    ],
    // ...
],
```

**Teste cada endpoint individualmente!**

### Paginação

Verifique como a API faz paginação:

```php
// Exemplo 1: page/limit
'pagination' => [
    'type' => 'query',
    'page_param' => 'page',      // ?page=1
    'limit_param' => 'limit',    // &limit=100
],

// Exemplo 2: offset/limit
'pagination' => [
    'type' => 'offset',
    'page_param' => 'offset',    // ?offset=0
    'limit_param' => 'limit',    // &limit=100
],

// Exemplo 3: cursor
'pagination' => [
    'type' => 'cursor',
    'page_param' => 'cursor',    // ?cursor=abc123
    'limit_param' => 'per_page', // &per_page=100
],
```

### Formato de Resposta

Identifique a estrutura da resposta:

```php
// Exemplo se a resposta for:
// {
//   "success": true,
//   "data": [...],
//   "pagination": {...}
// }

'response_format' => [
    'data_key' => 'data',
    'pagination_key' => 'pagination',
    'success_key' => 'success',
    'message_key' => 'message',
],

// OU se for:
// {
//   "items": [...],
//   "meta": {...}
// }

'response_format' => [
    'data_key' => 'items',
    'pagination_key' => 'meta',
    'success_key' => 'status',
    'message_key' => 'msg',
],
```

---

## 2️⃣ Ajustar `GestaoClickProvider.php`

### Método de Autenticação

Localize o método `requisicao()` e ajuste os headers:

```php
private function requisicao(string $metodo, string $endpoint, ?array $dados, int $idLoja): ?array
{
    $url = $this->config['api_base_url'] . $endpoint;

    // AJUSTAR conforme tipo de autenticação
    $authType = $this->config['auth']['type'] ?? 'bearer';
    $headerName = $this->config['auth']['header_name'] ?? 'Authorization';
    $headerFormat = $this->config['auth']['header_format'] ?? 'Bearer {token}';

    $authValue = str_replace('{token}', $this->credenciais['api_token'], $headerFormat);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        "{$headerName}: {$authValue}"
    ];

    // ... resto do código
}
```

### Tratamento de Respostas

Ajuste conforme estrutura real:

```php
// Se a resposta tiver estrutura diferente
$dataKey = $this->config['response_format']['data_key'] ?? 'data';
$responseData = $responseData[$dataKey] ?? $responseData;
```

---

## 3️⃣ Ajustar Handlers

### Identificar Campos da API

**Exemplo de Cliente:**

```php
// 1. Faça um GET /clientes para ver a estrutura
// 2. Identifique os campos retornados
// 3. Ajuste o Handler

public function transformarParaExterno(array $cliente): array
{
    return [
        // AJUSTAR nomes de campos conforme API
        'nome' => $cliente['nome'],              // ou 'name', 'full_name'
        'email' => $cliente['email'],
        'telefone' => $cliente['telefone'],      // ou 'phone', 'tel'
        'cpf' => $cliente['cpf'],                // ou 'document', 'tax_id'
        'cnpj' => $cliente['cnpj'],              // ou 'company_tax_id'
        'tipo_pessoa' => $cliente['tipo_pessoa'] === 'PF' ? 'individual' : 'company',
        // ... outros campos
    ];
}
```

### Campos Comuns em CRMs

**Cliente/Customer:**
- `name`, `full_name`, `company_name`
- `email`, `primary_email`
- `phone`, `mobile`, `whatsapp`
- `document`, `cpf`, `cnpj`, `tax_id`
- `person_type`, `customer_type` (individual/company)
- `address` (objeto ou campos separados)
- `notes`, `observations`

**Produto/Product:**
- `name`, `description`
- `code`, `sku`, `reference`
- `price`, `cost`, `list_price`
- `stock`, `quantity`, `stock_quantity`
- `unit`, `unit_of_measure`
- `active`, `status`, `enabled`
- `category`, `group`

**Venda/Deal/Order:**
- `title`, `name`, `order_number`
- `customer_id`, `client_id`
- `total`, `total_value`, `amount`
- `status`, `stage`, `pipeline_stage`
- `items`, `products`, `order_items`
- `discount`, `tax`
- `date`, `created_at`, `order_date`

**Atividade/Activity/Task:**
- `subject`, `title`, `name`
- `type`, `activity_type` (call, email, meeting)
- `description`, `notes`
- `due_date`, `deadline`
- `done`, `completed`, `is_completed`
- `assigned_to`, `user_id`, `owner_id`

---

## 🧪 Como Testar

### 1. Teste de Conexão

No painel CRM do Ecletech:
1. Vá em "Nova Integração"
2. Selecione "Gestão Click"
3. Cole seu Token
4. Clique "Testar Conexão"

### 2. Teste Manual (via cURL)

```bash
# Substitua {TOKEN} pelo seu token real

# Testar autenticação
curl -X GET "https://api.gestaoclick.com/v1/clientes" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"

# Criar cliente (ajustar campos)
curl -X POST "https://api.gestaoclick.com/v1/clientes" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Teste",
    "email": "teste@example.com",
    "cpf": "12345678900"
  }'
```

### 3. Teste via Postman/Insomnia

1. Importe a collection (se disponível na docs)
2. Configure o token nas variáveis de ambiente
3. Teste cada endpoint
4. Anote a estrutura de request/response
5. Ajuste os Handlers conforme

---

## 📝 Checklist de Ajustes

- [ ] **config.php**
  - [ ] URL base da API confirmada
  - [ ] Método de autenticação configurado
  - [ ] Endpoints ajustados (testados 1 por 1)
  - [ ] Paginação configurada
  - [ ] Formato de resposta definido
  - [ ] Rate limits verificados

- [ ] **GestaoClickProvider.php**
  - [ ] Headers de autenticação corretos
  - [ ] Tratamento de erros ajustado
  - [ ] Paginação implementada
  - [ ] Retry configurado

- [ ] **ClienteHandler.php**
  - [ ] Campos de entrada mapeados
  - [ ] Campos de saída mapeados
  - [ ] Formatações ajustadas
  - [ ] Validações implementadas

- [ ] **ProdutoHandler.php**
  - [ ] Mesmos ajustes do Cliente

- [ ] **VendaHandler.php**
  - [ ] Mesmos ajustes + itens da venda

- [ ] **AtividadeHandler.php**
  - [ ] Mesmos ajustes

- [ ] **Testes**
  - [ ] Conexão testada
  - [ ] Criar cliente testado
  - [ ] Listar clientes testado
  - [ ] Atualizar cliente testado
  - [ ] Buscar cliente testado
  - [ ] Sincronização bidirecional testada

---

## 🆘 Troubleshooting

### Erro 401 Unauthorized
- ✅ Verifique se o token está correto
- ✅ Confirme o header de autenticação
- ✅ Verifique se o addon API está ativo

### Erro 404 Not Found
- ✅ Confirme os endpoints na documentação
- ✅ Verifique a URL base
- ✅ Teste manualmente com cURL

### Erro 422 Validation Error
- ✅ Verifique campos obrigatórios
- ✅ Confira o formato dos dados
- ✅ Veja a mensagem de erro retornada

### Erro 429 Too Many Requests
- ✅ Ajuste `rate_limit` no config
- ✅ Aguarde antes de tentar novamente
- ✅ Considere aumentar delays

### Dados não aparecem / campos vazios
- ✅ Verifique o mapeamento no Handler
- ✅ Faça um GET para ver estrutura real
- ✅ Compare nomes de campos

---

## 📞 Suporte

- **Docs oficiais**: https://gestaoclick.docs.apiary.io/
- **Suporte Gestão Click**: https://gestaoclick.com.br/
- **Logs**: Verifique `crm_sync_log` no banco de dados

---

## 💡 Dicas

1. **Comece simples**: Teste primeiro o endpoint de listagem
2. **Use Postman**: Facilita muito os testes
3. **Salve exemplos**: Guarde JSONs de request/response
4. **Teste em homologação**: Se disponível
5. **Documente mudanças**: Anote cada ajuste feito
6. **Versione**: Faça commits após cada endpoint funcionar

---

**Última atualização**: 2025-01-15

**Status**: Configuração genérica - requer ajustes conforme documentação real
