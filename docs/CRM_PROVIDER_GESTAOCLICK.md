# 📦 PROVIDER GESTAOCLICK - Código Completo

**Como fica o código do GestaoClick isolado do core**

---

## 📂 ESTRUTURA DE DIRETÓRIOS

```
App/CRM/
│
├── Core/                                    # ← CORE (NÃO MEXE AQUI)
│   ├── CrmManager.php
│   ├── CrmConfig.php
│   └── CrmException.php
│
├── Providers/                               # ← PROVIDERS (PLUGÁVEL)
│   │
│   ├── CrmProviderInterface.php             # Interface comum
│   │
│   └── GestaoClick/                         # ← TUDO DO GESTAOCLICK AQUI
│       │
│       ├── GestaoClickProvider.php          # Provider principal
│       │
│       ├── config.php                       # Configuração
│       │
│       └── Handlers/                        # Handlers por entidade
│           ├── ClienteHandler.php
│           ├── VendaHandler.php
│           ├── ProdutoHandler.php
│           └── AtividadeHandler.php
│
├── Models/                                  # ← MODELS DO CRM
│   ├── ModelCrmIntegracao.php
│   └── ModelCrmSyncLog.php
│
└── Services/                                # ← SERVICES DO CRM
    ├── ServiceCrm.php
    └── ServiceCrmSync.php
```

**Isolamento:**
- ✅ Pasta `GestaoClick/` totalmente isolada
- ✅ Adicionar Pipedrive = criar pasta `Pipedrive/`
- ✅ Não afeta outros providers
- ✅ Não precisa alterar Core

---

## 📄 ARQUIVO 1: Interface (Comum a Todos)

**Caminho:** `App/CRM/Providers/CrmProviderInterface.php`

```php
<?php

namespace App\CRM\Providers;

/**
 * Interface que TODOS os providers devem implementar
 */
interface CrmProviderInterface
{
    /**
     * Cria entidade no CRM externo
     */
    public function criar(string $entidade, array $dados, int $idLoja): array;

    /**
     * Atualiza entidade no CRM externo
     */
    public function atualizar(string $entidade, string $externalId, array $dados, int $idLoja): array;

    /**
     * Busca entidades (com paginação)
     */
    public function buscar(string $entidade, int $pagina, int $limite, int $idLoja): array;

    /**
     * Valida credenciais
     */
    public function validarCredenciais(array $credenciais): bool;

    /**
     * Retorna configuração do provider
     */
    public function obterConfig(): array;
}
```

**Esta interface é compartilhada, mas cada provider implementa do seu jeito.**

---

## 📄 ARQUIVO 2: Config do GestaoClick

**Caminho:** `App/CRM/Providers/GestaoClick/config.php`

```php
<?php

/**
 * Configuração do Provider GestaoClick
 *
 * Este arquivo é específico do GestaoClick e não afeta outros providers
 */

return [
    // Identificação
    'nome' => 'GestaoClick CRM',
    'slug' => 'gestao_click',
    'versao' => '1.0.0',
    'descricao' => 'Integração com GestaoClick CRM',

    // API
    'base_url' => 'https://api.gestaoclick.com/v1',
    'timeout' => 30,
    'retry_attempts' => 3,
    'retry_delay' => 2000,  // ms

    // Rate Limiting
    'rate_limit' => [
        'max_requests' => 100,
        'per_seconds' => 60
    ],

    // Credenciais necessárias
    'credenciais_necessarias' => [
        'api_token' => [
            'label' => 'Token de API',
            'tipo' => 'text',
            'obrigatorio' => true,
            'ajuda' => 'Obtido em: Configurações > API > Gerar Token'
        ]
    ],

    // Entidades suportadas
    'entidades' => [
        'cliente' => [
            'habilitado' => true,
            'read_only' => false,
            'sync_bidirecional' => true,
            'campos_obrigatorios' => ['nome', 'email'],
            'handler' => 'ClienteHandler'
        ],
        'venda' => [
            'habilitado' => true,
            'read_only' => false,
            'sync_bidirecional' => true,
            'campos_obrigatorios' => ['cliente_id', 'valor_total'],
            'handler' => 'VendaHandler'
        ],
        'produto' => [
            'habilitado' => true,
            'read_only' => true,  // Apenas importar, não exportar
            'sync_bidirecional' => false,
            'campos_obrigatorios' => [],
            'handler' => 'ProdutoHandler'
        ],
        'atividade' => [
            'habilitado' => true,
            'read_only' => false,
            'sync_bidirecional' => true,
            'campos_obrigatorios' => ['tipo', 'descricao'],
            'handler' => 'AtividadeHandler'
        ]
    ],

    // Endpoints da API
    'endpoints' => [
        'clientes' => [
            'listar' => '/customers',
            'buscar' => '/customers/{id}',
            'criar' => '/customers',
            'atualizar' => '/customers/{id}',
            'deletar' => '/customers/{id}'
        ],
        'vendas' => [
            'listar' => '/deals',
            'buscar' => '/deals/{id}',
            'criar' => '/deals',
            'atualizar' => '/deals/{id}',
            'deletar' => '/deals/{id}'
        ],
        'produtos' => [
            'listar' => '/products',
            'buscar' => '/products/{id}'
        ],
        'atividades' => [
            'listar' => '/activities',
            'buscar' => '/activities/{id}',
            'criar' => '/activities',
            'atualizar' => '/activities/{id}',
            'deletar' => '/activities/{id}'
        ]
    ],

    // Webhooks
    'webhooks' => [
        'suportado' => true,
        'eventos' => [
            'customer.created',
            'customer.updated',
            'customer.deleted',
            'deal.created',
            'deal.updated',
            'deal.deleted',
            'activity.created',
            'activity.updated'
        ],
        'assinatura' => [
            'tipo' => 'hmac_sha256',
            'header' => 'X-GestaoClick-Signature'
        ]
    ]
];
```

**Vantagens do PHP vs JSON:**
- ✅ Cache via OPcache (10x mais rápido)
- ✅ Comentários nativos
- ✅ Validação em dev-time
- ✅ Autocomplete na IDE

---

## 📄 ARQUIVO 3: Provider Principal

**Caminho:** `App/CRM/Providers/GestaoClick/GestaoClickProvider.php`

```php
<?php

namespace App\CRM\Providers\GestaoClick;

use App\CRM\Providers\CrmProviderInterface;
use App\CRM\Core\CrmException;

/**
 * Provider para GestaoClick CRM
 *
 * TOTALMENTE ISOLADO - não afeta outros providers
 */
class GestaoClickProvider implements CrmProviderInterface
{
    private array $config;
    private array $handlers = [];

    public function __construct()
    {
        // Carrega config do arquivo PHP
        $this->config = require __DIR__ . '/config.php';
    }

    /**
     * Criar entidade no GestaoClick
     */
    public function criar(string $entidade, array $dados, int $idLoja): array
    {
        // 1. Obter handler da entidade
        $handler = $this->obterHandler($entidade);

        // 2. Transformar dados Ecletech → GestaoClick
        $dadosTransformados = $handler->transformarParaExterno($dados);

        // 3. Obter endpoint
        $endpoint = $this->obterEndpoint($entidade, 'criar');

        // 4. Fazer requisição
        $response = $this->requisicao('POST', $endpoint, $dadosTransformados, $idLoja);

        // 5. Retornar external_id
        return [
            'external_id' => $response['id'],
            'dados' => $response
        ];
    }

    /**
     * Atualizar entidade no GestaoClick
     */
    public function atualizar(string $entidade, string $externalId, array $dados, int $idLoja): array
    {
        // 1. Obter handler
        $handler = $this->obterHandler($entidade);

        // 2. Transformar dados
        $dadosTransformados = $handler->transformarParaExterno($dados);

        // 3. Obter endpoint com ID
        $endpoint = $this->obterEndpoint($entidade, 'atualizar');
        $endpoint = str_replace('{id}', $externalId, $endpoint);

        // 4. Fazer requisição
        $response = $this->requisicao('PUT', $endpoint, $dadosTransformados, $idLoja);

        return [
            'dados' => $response
        ];
    }

    /**
     * Buscar entidades do GestaoClick (paginado)
     */
    public function buscar(string $entidade, int $pagina, int $limite, int $idLoja): array
    {
        // 1. Obter endpoint
        $endpoint = $this->obterEndpoint($entidade, 'listar');
        $endpoint .= "?page={$pagina}&limit={$limite}";

        // 2. Fazer requisição
        $response = $this->requisicao('GET', $endpoint, null, $idLoja);

        // 3. Obter handler
        $handler = $this->obterHandler($entidade);

        // 4. Transformar cada item GestaoClick → Ecletech
        $dadosTransformados = array_map(
            fn($item) => $handler->transformarParaLocal($item),
            $response['data'] ?? []
        );

        return [
            'dados' => $dadosTransformados,
            'total' => $response['total'] ?? 0,
            'pagina_atual' => $pagina,
            'total_paginas' => ceil(($response['total'] ?? 0) / $limite)
        ];
    }

    /**
     * Validar credenciais
     */
    public function validarCredenciais(array $credenciais): bool
    {
        try {
            $response = $this->requisicao(
                'GET',
                '/auth/validate',
                null,
                null,
                $credenciais
            );

            return isset($response['valid']) && $response['valid'] === true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obter configuração
     */
    public function obterConfig(): array
    {
        return $this->config;
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Obter handler da entidade (lazy loading)
     */
    private function obterHandler(string $entidade): object
    {
        if (!isset($this->handlers[$entidade])) {
            $config = $this->config['entidades'][$entidade] ?? null;

            if (!$config) {
                throw new CrmException("Entidade não suportada: {$entidade}");
            }

            $handlerClass = __NAMESPACE__ . "\\Handlers\\" . $config['handler'];

            if (!class_exists($handlerClass)) {
                throw new CrmException("Handler não encontrado: {$handlerClass}");
            }

            $this->handlers[$entidade] = new $handlerClass();
        }

        return $this->handlers[$entidade];
    }

    /**
     * Obter endpoint da config
     */
    private function obterEndpoint(string $entidade, string $acao): string
    {
        $mapa = [
            'cliente' => 'clientes',
            'venda' => 'vendas',
            'produto' => 'produtos',
            'atividade' => 'atividades'
        ];

        $chave = $mapa[$entidade] ?? $entidade;

        $endpoint = $this->config['endpoints'][$chave][$acao] ?? null;

        if (!$endpoint) {
            throw new CrmException("Endpoint não encontrado: {$entidade}.{$acao}");
        }

        return $endpoint;
    }

    /**
     * Fazer requisição HTTP para GestaoClick
     */
    private function requisicao(
        string $metodo,
        string $endpoint,
        ?array $dados,
        ?int $idLoja,
        ?array $credenciaisCustom = null
    ): array {
        // 1. Obter credenciais
        if ($credenciaisCustom) {
            $creds = $credenciaisCustom;
        } else {
            $creds = $this->obterCredenciais($idLoja);
        }

        // 2. Montar URL
        $url = rtrim($this->config['base_url'], '/') . '/' . ltrim($endpoint, '/');

        // 3. Preparar headers
        $headers = [
            'Authorization: Bearer ' . $creds['api_token'],
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Ecletech-CRM/1.0'
        ];

        // 4. cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        if ($dados && in_array($metodo, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        }

        // 5. Executar
        $inicio = microtime(true);
        $response = curl_exec($ch);
        $duracao = (int)((microtime(true) - $inicio) * 1000);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 6. Validar resposta
        if ($curlError) {
            throw new CrmException("Erro cURL: {$curlError}");
        }

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $message = $error['message'] ?? "HTTP {$httpCode}";
            throw new CrmException("Erro GestaoClick: {$message}", $httpCode);
        }

        // 7. Parsear JSON
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CrmException("Erro ao parsear JSON: " . json_last_error_msg());
        }

        return $data ?? [];
    }

    /**
     * Obter credenciais da integração
     */
    private function obterCredenciais(int $idLoja): array
    {
        $db = \App\Core\BancoDados::obterInstancia();

        $integracao = $db->buscarUm(
            "SELECT credenciais FROM crm_integracoes
             WHERE id_loja = ? AND provider = 'gestao_click' AND ativo = 1",
            [$idLoja]
        );

        if (!$integracao) {
            throw new CrmException("Integração não configurada para loja #{$idLoja}");
        }

        // Descriptografar credenciais
        $credenciais = $this->descriptografar($integracao['credenciais']);

        return json_decode($credenciais, true);
    }

    /**
     * Descriptografar credenciais
     */
    private function descriptografar(string $encrypted): string
    {
        $key = getenv('CRM_ENCRYPTION_KEY');

        if (!$key) {
            throw new CrmException("CRM_ENCRYPTION_KEY não configurada");
        }

        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        if ($decrypted === false) {
            throw new CrmException("Erro ao descriptografar credenciais");
        }

        return $decrypted;
    }
}
```

**Características:**
- ✅ Totalmente isolado
- ✅ Usa handlers para transformação
- ✅ HTTP via cURL
- ✅ Tratamento de erros
- ✅ Lazy loading de handlers

---

## 📄 ARQUIVO 4: Handler de Cliente

**Caminho:** `App/CRM/Providers/GestaoClick/Handlers/ClienteHandler.php`

```php
<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformar dados de Cliente
 * Ecletech ↔ GestaoClick
 */
class ClienteHandler
{
    /**
     * Transforma Ecletech → GestaoClick
     */
    public function transformarParaExterno(array $cliente): array
    {
        return array_filter([
            // Tipo de pessoa
            'person_type' => $this->mapearTipoPessoa($cliente['tipo_pessoa'] ?? 'PF'),

            // Dados PF
            'name' => $cliente['nome'] ?? '',
            'document' => $cliente['cpf'] ? $this->formatarCpf($cliente['cpf']) : null,
            'birth_date' => $cliente['data_nascimento'] ?? null,

            // Dados PJ
            'company_name' => $cliente['razao_social'] ?? null,
            'company_document' => $cliente['cnpj'] ? $this->formatarCnpj($cliente['cnpj']) : null,
            'state_registration' => $cliente['inscricao_estadual'] ?? null,
            'municipal_registration' => $cliente['inscricao_municipal'] ?? null,

            // Contato
            'email' => $cliente['email'] ?? null,
            'phone' => $cliente['telefone'] ? $this->formatarTelefone($cliente['telefone']) : null,
            'mobile' => $cliente['celular'] ? $this->formatarTelefone($cliente['celular']) : null,

            // Endereço (pegar o principal)
            'address' => $this->transformarEndereco($cliente['enderecos'] ?? []),

            // Status
            'status' => $cliente['ativo'] ? 'active' : 'inactive',

            // Custom fields (referência reversa)
            'custom_fields' => [
                'ecletech_id' => (string) $cliente['id'],
                'origem' => 'Ecletech CRM'
            ]

        ], fn($v) => $v !== null);  // Remove nulls
    }

    /**
     * Transforma GestaoClick → Ecletech
     */
    public function transformarParaLocal(array $clienteExterno): array
    {
        return array_filter([
            // Tipo
            'tipo_pessoa' => $this->mapearTipoPessoaReverso($clienteExterno['person_type'] ?? 'individual'),

            // Dados PF
            'nome' => $clienteExterno['name'] ?? '',
            'cpf' => $clienteExterno['document'] ? $this->limpar($clienteExterno['document']) : null,
            'data_nascimento' => $clienteExterno['birth_date'] ?? null,

            // Dados PJ
            'razao_social' => $clienteExterno['company_name'] ?? null,
            'cnpj' => $clienteExterno['company_document'] ? $this->limpar($clienteExterno['company_document']) : null,
            'inscricao_estadual' => $clienteExterno['state_registration'] ?? null,
            'inscricao_municipal' => $clienteExterno['municipal_registration'] ?? null,

            // Contato
            'email' => $clienteExterno['email'] ?? null,
            'telefone' => $clienteExterno['phone'] ? $this->limpar($clienteExterno['phone']) : null,
            'celular' => $clienteExterno['mobile'] ? $this->limpar($clienteExterno['mobile']) : null,

            // Status
            'ativo' => $clienteExterno['status'] === 'active',

            // External ID
            'external_id' => $clienteExterno['id'] ?? null,

            // Endereço (separado para inserção posterior)
            '_endereco' => $this->transformarEnderecoReverso($clienteExterno['address'] ?? null)

        ], fn($v) => $v !== null);
    }

    // ========== HELPERS ==========

    private function mapearTipoPessoa(string $tipo): string
    {
        return $tipo === 'PF' ? 'individual' : 'company';
    }

    private function mapearTipoPessoaReverso(string $tipo): string
    {
        return $tipo === 'individual' ? 'PF' : 'PJ';
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

    private function formatarTelefone(string $telefone): string
    {
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

    private function limpar(string $str): string
    {
        return preg_replace('/\D/', '', $str);
    }

    private function transformarEndereco(array $enderecos): ?array
    {
        // Pegar endereço principal
        $principal = null;
        foreach ($enderecos as $end) {
            if ($end['principal'] ?? false) {
                $principal = $end;
                break;
            }
        }

        if (!$principal) {
            $principal = $enderecos[0] ?? null;
        }

        if (!$principal) {
            return null;
        }

        return [
            'zipcode' => $this->formatarCep($principal['cep'] ?? ''),
            'street' => $principal['logradouro'] ?? '',
            'number' => $principal['numero'] ?? '',
            'complement' => $principal['complemento'] ?? null,
            'district' => $principal['bairro'] ?? '',
            'city' => $principal['cidade'] ?? '',
            'state' => $principal['uf'] ?? '',
            'country' => 'Brasil'
        ];
    }

    private function transformarEnderecoReverso(?array $address): ?array
    {
        if (!$address) {
            return null;
        }

        return [
            'tipo_endereco_id' => 1,  // Comercial
            'principal' => true,
            'cep' => $this->limpar($address['zipcode'] ?? ''),
            'logradouro' => $address['street'] ?? '',
            'numero' => $address['number'] ?? '',
            'complemento' => $address['complement'] ?? null,
            'bairro' => $address['district'] ?? '',
            'uf' => $address['state'] ?? '',
            'pais' => $address['country'] ?? 'Brasil'
            // cidade_id será resolvido via lookup
        ];
    }

    private function formatarCep(string $cep): string
    {
        $limpo = preg_replace('/\D/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $limpo);
    }
}
```

**Responsabilidade:** Apenas transformação de dados Cliente

---

## 📄 ARQUIVO 5: Handler de Venda

**Caminho:** `App/CRM/Providers/GestaoClick/Handlers/VendaHandler.php`

```php
<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

class VendaHandler
{
    public function transformarParaExterno(array $venda): array
    {
        return array_filter([
            'title' => $venda['titulo'] ?? "Venda #{$venda['id']}",
            'value' => $venda['valor_total'] ?? 0,
            'currency' => 'BRL',

            // Cliente (precisa ter external_id)
            'customer_id' => $venda['cliente_external_id'] ?? null,

            // Status
            'status' => $this->mapearStatus($venda['status'] ?? 'aberta'),

            // Datas
            'expected_close_date' => $venda['data_previsao_fechamento'] ?? null,
            'closed_date' => $venda['data_fechamento'] ?? null,

            // Itens (produtos)
            'items' => $this->transformarItens($venda['itens'] ?? []),

            // Custom fields
            'custom_fields' => [
                'ecletech_id' => (string) $venda['id'],
                'origem' => 'Ecletech CRM'
            ]

        ], fn($v) => $v !== null);
    }

    public function transformarParaLocal(array $vendaExterna): array
    {
        return array_filter([
            'titulo' => $vendaExterna['title'] ?? '',
            'valor_total' => $vendaExterna['value'] ?? 0,
            'status' => $this->mapearStatusReverso($vendaExterna['status'] ?? 'open'),
            'data_previsao_fechamento' => $vendaExterna['expected_close_date'] ?? null,
            'data_fechamento' => $vendaExterna['closed_date'] ?? null,
            'external_id' => $vendaExterna['id'] ?? null,

            // Cliente será resolvido via external_id
            '_cliente_external_id' => $vendaExterna['customer_id'] ?? null,

            // Itens serão inseridos separadamente
            '_itens' => $this->transformarItensReverso($vendaExterna['items'] ?? [])

        ], fn($v) => $v !== null);
    }

    private function mapearStatus(string $status): string
    {
        $mapa = [
            'aberta' => 'open',
            'ganha' => 'won',
            'perdida' => 'lost',
            'cancelada' => 'cancelled'
        ];

        return $mapa[$status] ?? 'open';
    }

    private function mapearStatusReverso(string $status): string
    {
        $mapa = [
            'open' => 'aberta',
            'won' => 'ganha',
            'lost' => 'perdida',
            'cancelled' => 'cancelada'
        ];

        return $mapa[$status] ?? 'aberta';
    }

    private function transformarItens(array $itens): array
    {
        return array_map(function($item) {
            return [
                'product_id' => $item['produto_external_id'] ?? null,
                'quantity' => $item['quantidade'] ?? 1,
                'unit_price' => $item['valor_unitario'] ?? 0,
                'discount' => $item['desconto'] ?? 0
            ];
        }, $itens);
    }

    private function transformarItensReverso(array $itens): array
    {
        return array_map(function($item) {
            return [
                '_produto_external_id' => $item['product_id'] ?? null,
                'quantidade' => $item['quantity'] ?? 1,
                'valor_unitario' => $item['unit_price'] ?? 0,
                'desconto' => $item['discount'] ?? 0
            ];
        }, $itens);
    }
}
```

---

## 🎯 COMO É TOTALMENTE ISOLADO

### 1. Adicionar Pipedrive (sem tocar no GestaoClick)

```
App/CRM/Providers/
│
├── GestaoClick/           # ← NÃO MEXE AQUI
│   ├── GestaoClickProvider.php
│   ├── config.php
│   └── Handlers/
│
└── Pipedrive/             # ← NOVO (ISOLADO)
    ├── PipedriveProvider.php
    ├── config.php
    └── Handlers/
        ├── ClienteHandler.php
        └── VendaHandler.php
```

**Zero conflito!** Cada provider é independente.

---

### 2. Registro no Core

**Caminho:** `App/CRM/Core/CrmManager.php`

```php
<?php

namespace App\CRM\Core;

class CrmManager
{
    private static array $providers = [];

    /**
     * Registro manual de providers
     */
    public static function registrar(): void
    {
        if (!empty(self::$providers)) {
            return;
        }

        // Registra providers disponíveis
        self::$providers = [
            'gestao_click' => \App\CRM\Providers\GestaoClick\GestaoClickProvider::class,
            'pipedrive' => \App\CRM\Providers\Pipedrive\PipedriveProvider::class,
            'bling' => \App\CRM\Providers\Bling\BlingProvider::class
        ];
    }

    public function obterProvider(string $slug): CrmProviderInterface
    {
        self::registrar();

        if (!isset(self::$providers[$slug])) {
            throw new CrmException("Provider não encontrado: {$slug}");
        }

        $classe = self::$providers[$slug];
        return new $classe();
    }
}
```

**Para adicionar Pipedrive:**
1. Criar pasta `Pipedrive/`
2. Adicionar linha no registro
3. **PRONTO!**

---

## 📊 ISOLAMENTO VISUAL

```
┌──────────────────────────────────────────────┐
│ CORE (Compartilhado)                         │
│ - CrmManager                                 │
│ - CrmProviderInterface                       │
│ - CrmException                               │
└──────────────────────────────────────────────┘
                    ↕
        ┌───────────┴────────────┐
        │                        │
┌───────────────────┐  ┌───────────────────┐
│ GESTAOCLICK       │  │ PIPEDRIVE         │
│ (Isolado)         │  │ (Isolado)         │
│                   │  │                   │
│ ├─ Provider       │  │ ├─ Provider       │
│ ├─ config.php     │  │ ├─ config.php     │
│ └─ Handlers/      │  │ └─ Handlers/      │
│    ├─ Cliente     │  │    ├─ Cliente     │
│    └─ Venda       │  │    └─ Venda       │
└───────────────────┘  └───────────────────┘

    NÃO SE COMUNICAM!
    Totalmente independentes
```

---

## ✅ VANTAGENS DO ISOLAMENTO

| Vantagem | Descrição |
|----------|-----------|
| **Zero acoplamento** | GestaoClick não sabe que Pipedrive existe |
| **Fácil adicionar** | Criar pasta = novo provider |
| **Fácil remover** | Deletar pasta = remove provider |
| **Fácil testar** | Testar um provider não afeta outros |
| **Múltiplos devs** | Cada dev trabalha em um provider |
| **Versionamento** | Cada provider tem sua versão |

---

## 📦 RESUMO DA ESTRUTURA

```
GestaoClick/
├── GestaoClickProvider.php     # ~200 linhas
│   ├── criar()
│   ├── atualizar()
│   ├── buscar()
│   ├── validarCredenciais()
│   └── requisicao() [HTTP]
│
├── config.php                  # ~120 linhas
│   ├── Configuração da API
│   ├── Entidades suportadas
│   ├── Endpoints
│   └── Webhooks
│
└── Handlers/
    ├── ClienteHandler.php      # ~150 linhas
    │   ├── transformarParaExterno()
    │   └── transformarParaLocal()
    │
    ├── VendaHandler.php        # ~100 linhas
    ├── ProdutoHandler.php      # ~80 linhas
    └── AtividadeHandler.php    # ~80 linhas

Total: ~730 linhas para provider completo
```

**Comparação:**
- Proposta original: ~1.500 linhas por provider
- Versão simplificada: ~730 linhas (51% menos)

---

## 🎯 CONCLUSÃO

**SIM, é totalmente isolado!**

✅ **GestaoClick** = 1 pasta isolada
✅ **Pipedrive** = 1 pasta isolada
✅ **Bling** = 1 pasta isolada

**Adicionar novo provider:**
1. Criar pasta `App/CRM/Providers/NomeCRM/`
2. Implementar `NomeCRMProvider.php`
3. Criar `config.php`
4. Criar handlers
5. Registrar em `CrmManager.php`

**Tempo:** 1-2 dias por provider

**Não afeta:** Código existente, outros providers, core

---

**Arquivo:** CRM_PROVIDER_GESTAOCLICK.md
**Data:** Janeiro 2025
**Versão:** 1.0
