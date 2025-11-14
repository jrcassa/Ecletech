# ✅ RESOLVIDO - Correção do Backend - Erro ao Salvar Nível

**Status:** CORRIGIDO em commit `dfb94ec`

## Problema

Erro 500 ao tentar atualizar (PUT) ou criar (POST) níveis de acesso:

```
PUT http://localhost/public_html/api/niveis/{id}
Status: 500 Internal Server Error

Mensagem: "explode(): Argument #2 ($string) must be of type string, array given"
Arquivo: D:\xampp8\htdocs\App\Helpers\AuxiliarValidacao.php
Linha: 356
```

## Dados Enviados pelo Frontend (CORRETOS)

O JavaScript está enviando os dados no formato correto como JSON:

```json
{
  "nome": "Administrador",
  "codigo": "admin",
  "descricao": "Nível de acesso administrativo",
  "ordem": 100,
  "ativo": 1
}
```

### Headers da Requisição
```
Content-Type: application/json
X-CSRF-Token: [token]
```

### Body
```
Body: JSON.stringify(dados)
```

## Causa do Erro

O erro ocorre em `AuxiliarValidacao.php:356` onde o código tenta fazer `explode('|', $valor)` mas `$valor` é um array ao invés de string.

Isso sugere que o backend está recebendo as regras de validação em formato incorreto. Possíveis causas:

1. **Regras de validação mal formatadas** - As regras podem estar sendo passadas como array quando deveriam ser string com pipes
2. **Parser JSON não está funcionando** - O backend pode não estar lendo corretamente o JSON do corpo da requisição
3. **Middleware de validação** - Pode estar transformando os dados antes de chegar ao validador

## Solução Sugerida

### 1. Verificar ControllerNivel.php (linha ~119)

```php
// ANTES (provavelmente está assim)
$regras = [
    'nome' => ['required', 'string', 'max:100'],  // ❌ Array
    'codigo' => ['required', 'string', 'max:50'], // ❌ Array
    // ...
];

// DEPOIS (deve ser assim)
$regras = [
    'nome' => 'required|string|max:100',          // ✅ String com pipes
    'codigo' => 'required|string|max:50',         // ✅ String com pipes
    'descricao' => 'nullable|string',             // ✅ String com pipes
    'ordem' => 'required|integer|min:0|max:100',  // ✅ String com pipes
    'ativo' => 'required|integer|in:0,1'          // ✅ String com pipes
];
```

### 2. Verificar AuxiliarValidacao.php (linha 356)

Adicionar verificação de tipo antes do explode:

```php
// Antes
$partes = explode('|', $regra);

// Depois
if (is_array($regra)) {
    // Se já é array, não precisa fazer explode
    $partes = $regra;
} else {
    // Se é string, faz explode normalmente
    $partes = explode('|', $regra);
}
```

### 3. Verificar se o JSON está sendo parseado

No `ControllerNivel.php`, adicionar antes da validação:

```php
// Debug para verificar o que está chegando
error_log("Dados recebidos: " . print_r($_POST, true));
error_log("Input raw: " . file_get_contents('php://input'));

// Garantir que está lendo JSON do corpo da requisição
$dados = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro ao decodificar JSON: " . json_last_error_msg());
}
```

## Arquivos Afetados (Backend)

1. `D:\xampp8\htdocs\App\Controllers\Nivel\ControllerNivel.php` - Linha 119
2. `D:\xampp8\htdocs\App\Helpers\AuxiliarValidacao.php` - Linha 356

## Teste Após Correção

1. Abrir Developer Tools (F12) no navegador
2. Acessar página de Gestão de Acessos
3. Clicar em "Editar" em qualquer nível
4. Alterar algum campo
5. Clicar em "Salvar"
6. Verificar no Console:
   - Logs de debug (🔍 DEBUG - Salvando Nível)
   - Dados sendo enviados
   - Resposta da API

### Resposta Esperada (Sucesso)

```json
{
  "sucesso": true,
  "mensagem": "Nível atualizado com sucesso",
  "dados": {
    "id": 5,
    "nome": "Administrador",
    "codigo": "admin",
    "descricao": "Nível de acesso administrativo",
    "ordem": 100,
    "ativo": 1
  }
}
```

## ✅ Correção Aplicada

### Arquivos Corrigidos

**`App/Controllers/Nivel/ControllerNivel.php`**

#### Método `criar()` (linha 69-75)
```php
// ANTES (ERRADO)
$erros = AuxiliarValidacao::validar($dados, [
    'nome' => ['obrigatorio', 'string', 'max:100'],  // ❌ Array
    'codigo' => ['obrigatorio', 'string', 'max:50'], // ❌ Array
    // ...
]);

// DEPOIS (CORRETO) ✅
$erros = AuxiliarValidacao::validar($dados, [
    'nome' => 'obrigatorio|string|max:100',          // ✅ String com pipes
    'codigo' => 'obrigatorio|string|max:50',         // ✅ String com pipes
    'descricao' => 'string',
    'ordem' => 'inteiro',
    'ativo' => 'inteiro'
]);
```

#### Método `atualizar()` (linha 119-125)
```php
// ANTES (ERRADO)
$erros = AuxiliarValidacao::validar($dados, [
    'nome' => ['string', 'max:100'],  // ❌ Array
    'codigo' => ['string', 'max:50'], // ❌ Array
    // ...
]);

// DEPOIS (CORRETO) ✅
$erros = AuxiliarValidacao::validar($dados, [
    'nome' => 'string|max:100',       // ✅ String com pipes
    'codigo' => 'string|max:50',      // ✅ String com pipes
    'descricao' => 'string',
    'ordem' => 'inteiro',
    'ativo' => 'inteiro'
]);
```

### Commits Relacionados

1. **`6bfbf97`** - Debug detalhado e documentação do problema
2. **`dfb94ec`** - Correção do backend PHP

### Resultado

✅ Criar níveis funciona
✅ Atualizar níveis funciona
✅ Erro 500 resolvido
✅ Validação funcional

## Nota

O frontend foi revisado completamente e **NÃO havia problemas no código JavaScript**. O erro era 100% do backend PHP e foi **corrigido**.
