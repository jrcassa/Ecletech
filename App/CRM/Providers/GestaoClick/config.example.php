<?php

/**
 * EXEMPLO de Configuração - Baseado em padrões REST comuns
 *
 * Este é um EXEMPLO de como a configuração pode ficar.
 * Os valores reais dependem da documentação oficial da API Gestão Click.
 *
 * COPIE ESTE ARQUIVO para config.php e ajuste conforme necessário.
 */

return [
    // =====================================================
    // API BASE
    // =====================================================

    // AJUSTAR: Verifique na documentação a URL correta
    // Exemplos possíveis:
    // - https://api.gestaoclick.com/v1
    // - https://api.gestaoclick.com.br/api/v1
    // - https://app.gestaoclick.com/api
    'api_base_url' => 'https://api.gestaoclick.com/v1',

    // Versão da API (se aplicável)
    'api_version' => 'v1',

    // =====================================================
    // AUTENTICAÇÃO
    // =====================================================

    /**
     * IMPORTANTE: Teste manualmente primeiro!
     *
     * curl -X GET "{URL}/clientes" \
     *   -H "Authorization: Bearer {SEU_TOKEN}" \
     *   -H "Content-Type: application/json"
     *
     * Se funcionar, use 'bearer'. Se não, tente 'api_key' ou 'token'
     */
    'auth' => [
        'type' => 'bearer', // bearer, api_key, token, basic
        'header_name' => 'Authorization',
        'header_format' => 'Bearer {token}', // ou apenas '{token}'
    ],

    // =====================================================
    // LIMITS & TIMEOUTS
    // =====================================================

    'rate_limit' => 100, // requisições por minuto (ajustar conforme plano)
    'batch_size' => 100, // registros por requisição
    'timeout' => 30, // timeout de request
    'connect_timeout' => 10, // timeout de conexão

    // =====================================================
    // ENDPOINTS
    // =====================================================

    /**
     * AJUSTAR: Teste cada endpoint individualmente
     *
     * Possíveis variações:
     * - /clientes ou /customers ou /clients ou /pessoas
     * - /produtos ou /products ou /items ou /mercadorias
     * - /vendas ou /orders ou /sales ou /pedidos ou /deals
     * - /atividades ou /activities ou /tasks ou /tarefas
     */
    'endpoints' => [
        'cliente' => [
            'listar' => '/clientes',
            'criar' => '/clientes',
            'atualizar' => '/clientes/{id}',
            'buscar' => '/clientes/{id}',
            'deletar' => '/clientes/{id}',
        ],
        'produto' => [
            'listar' => '/produtos',
            'criar' => '/produtos',
            'atualizar' => '/produtos/{id}',
            'buscar' => '/produtos/{id}',
            'deletar' => '/produtos/{id}',
        ],
        'venda' => [
            'listar' => '/vendas',
            'criar' => '/vendas',
            'atualizar' => '/vendas/{id}',
            'buscar' => '/vendas/{id}',
            'deletar' => '/vendas/{id}',
        ],
        'atividade' => [
            'listar' => '/atividades',
            'criar' => '/atividades',
            'atualizar' => '/atividades/{id}',
            'buscar' => '/atividades/{id}',
            'deletar' => '/atividades/{id}',
        ],
    ],

    // =====================================================
    // PAGINAÇÃO
    // =====================================================

    /**
     * VERIFICAR: Faça um GET com diferentes parâmetros para descobrir
     *
     * Teste 1: /clientes?page=1&limit=10
     * Teste 2: /clientes?offset=0&limit=10
     * Teste 3: /clientes?pagina=1&por_pagina=10
     *
     * Veja qual funciona!
     */
    'pagination' => [
        'type' => 'query', // query, offset, cursor
        'page_param' => 'page', // ou 'pagina', 'offset'
        'limit_param' => 'limit', // ou 'per_page', 'limite', 'por_pagina'
        'default_limit' => 100,
        'max_limit' => 500,
    ],

    // =====================================================
    // FORMATO DE RESPOSTA
    // =====================================================

    /**
     * VERIFICAR: Faça um GET e veja a estrutura do JSON
     *
     * Exemplo 1:
     * {
     *   "success": true,
     *   "data": [...],
     *   "pagination": {...}
     * }
     *
     * Exemplo 2:
     * {
     *   "items": [...],
     *   "meta": {...}
     * }
     *
     * Exemplo 3:
     * {
     *   "clientes": [...],
     *   "total": 100
     * }
     */
    'response_format' => [
        'data_key' => 'data', // ou 'items', 'results', 'clientes', etc
        'pagination_key' => 'pagination', // ou 'meta', 'paging'
        'success_key' => 'success', // ou 'status', 'ok'
        'message_key' => 'message', // ou 'msg', 'mensagem'
        'error_key' => 'error', // ou 'errors', 'erro'
        'total_key' => 'total', // ou 'total_count', 'count'
        'current_page_key' => 'current_page', // ou 'page', 'pagina_atual'
        'total_pages_key' => 'total_pages', // ou 'pages', 'total_paginas'
    ],

    // =====================================================
    // MAPEAMENTOS
    // =====================================================

    // Status de venda (AJUSTAR conforme valores aceitos pela API)
    'status_venda' => [
        'pendente' => 'pending',
        'aprovado' => 'approved',
        'cancelado' => 'cancelled',
        'em_andamento' => 'in_progress',
        'concluido' => 'completed',
        'faturado' => 'invoiced',
    ],

    'status_venda_reverso' => [
        'pending' => 'pendente',
        'approved' => 'aprovado',
        'cancelled' => 'cancelado',
        'in_progress' => 'em_andamento',
        'completed' => 'concluido',
        'invoiced' => 'faturado',
    ],

    // Tipo de pessoa (AJUSTAR)
    'tipo_pessoa' => [
        'PF' => 'individual', // ou 'fisica', 'person', 'F'
        'PJ' => 'company', // ou 'juridica', 'business', 'J'
    ],

    'tipo_pessoa_reverso' => [
        'individual' => 'PF',
        'company' => 'PJ',
    ],

    // Tipo de atividade (AJUSTAR)
    'tipo_atividade' => [
        'tarefa' => 'task',
        'ligacao' => 'call',
        'email' => 'email',
        'reuniao' => 'meeting',
        'visita' => 'visit',
    ],

    'tipo_atividade_reverso' => [
        'task' => 'tarefa',
        'call' => 'ligacao',
        'email' => 'email',
        'meeting' => 'reuniao',
        'visit' => 'visita',
    ],

    // =====================================================
    // CAMPOS OBRIGATÓRIOS
    // =====================================================

    /**
     * VERIFICAR: Tente criar um registro vazio e veja quais
     * campos a API reclama que estão faltando
     */
    'campos_obrigatorios' => [
        'cliente' => ['nome', 'documento'], // ajustar
        'produto' => ['nome', 'preco'], // ajustar
        'venda' => ['cliente_id', 'valor_total'], // ajustar
        'atividade' => ['tipo', 'descricao'], // ajustar
    ],

    // =====================================================
    // RETRY & ERROR HANDLING
    // =====================================================

    'retry' => [
        'max_tentativas' => 3,
        'delay_inicial' => 2, // segundos
        'multiplicador' => 2, // 2s, 4s, 8s
        'retry_on_codes' => [429, 500, 502, 503, 504],
    ],

    // =====================================================
    // HEADERS CUSTOMIZADOS
    // =====================================================

    /**
     * VERIFICAR: Alguns CRMs exigem headers adicionais
     * como X-Company-ID, X-User-ID, etc
     */
    'custom_headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        // 'X-Company-Id' => '{company_id}', // se necessário
        // 'X-User-Id' => '{user_id}', // se necessário
    ],

    // =====================================================
    // FEATURES (recursos disponíveis)
    // =====================================================

    'features' => [
        'bulk_operations' => false, // POST /clientes/bulk
        'async_processing' => false, // jobs assíncronos
        'field_selection' => false, // ?fields=id,nome,email
        'sorting' => true, // ?sort=nome&order=asc
        'filtering' => true, // ?filter[nome]=João
        'search' => false, // ?search=termo
        'webhooks' => false, // suporte a webhooks
    ],

    // =====================================================
    // VALIDAÇÕES
    // =====================================================

    'validations' => [
        'cpf_length' => 11,
        'cnpj_length' => 14,
        'cep_length' => 8,
        'telefone_min' => 10,
        'telefone_max' => 11,
        'email_required' => false,
    ],

    // =====================================================
    // CÓDIGOS DE ERRO DA API
    // =====================================================

    /**
     * MAPEAR: Veja os códigos de erro que a API retorna
     * e mapeie para mensagens em português
     */
    'error_codes' => [
        'INVALID_TOKEN' => 'Token de autenticação inválido',
        'RATE_LIMIT_EXCEEDED' => 'Limite de requisições excedido. Aguarde antes de tentar novamente.',
        'RESOURCE_NOT_FOUND' => 'Recurso não encontrado no CRM',
        'VALIDATION_ERROR' => 'Erro de validação nos dados enviados',
        'DUPLICATE_ENTRY' => 'Já existe um registro com esses dados',
        'INVALID_CUSTOMER' => 'Cliente inválido ou não encontrado',
        'INVALID_PRODUCT' => 'Produto inválido ou não encontrado',
        'INSUFFICIENT_PERMISSIONS' => 'Permissões insuficientes para esta operação',
    ],

    // =====================================================
    // MAPEAMENTO DE CAMPOS (IMPORTANTE!)
    // =====================================================

    /**
     * MAPEAR: Compare os campos do Ecletech com os da API
     * Faça um GET para ver os nomes reais dos campos
     */
    'field_mappings' => [
        'cliente' => [
            // Ecletech => GestaoClick
            'nome' => 'name', // ou 'nome', 'customer_name'
            'email' => 'email', // ou 'primary_email', 'email_principal'
            'telefone' => 'phone', // ou 'telefone', 'contact_phone'
            'celular' => 'mobile', // ou 'celular', 'whatsapp'
            'cpf' => 'cpf', // ou 'document', 'tax_id', 'federal_tax_id'
            'cnpj' => 'cnpj', // ou 'company_tax_id', 'ein'
            'tipo_pessoa' => 'person_type', // ou 'customer_type', 'type'
            'endereco' => 'address', // pode ser objeto ou campos separados
            'cep' => 'zip_code', // ou 'cep', 'postal_code'
            'cidade' => 'city', // ou 'cidade'
            'estado' => 'state', // ou 'estado', 'uf'
            'observacoes' => 'notes', // ou 'observations', 'comments'
        ],
        'produto' => [
            'nome' => 'name',
            'codigo' => 'code', // ou 'sku', 'reference'
            'descricao' => 'description',
            'preco' => 'price', // ou 'sale_price', 'list_price'
            'custo' => 'cost', // ou 'cost_price', 'purchase_price'
            'estoque' => 'stock', // ou 'quantity', 'stock_quantity'
            'unidade' => 'unit', // ou 'unit_of_measure', 'uom'
            'ativo' => 'active', // ou 'enabled', 'is_active'
            'grupo' => 'category', // ou 'group', 'product_category'
        ],
        'venda' => [
            'titulo' => 'title', // ou 'name', 'order_name'
            'cliente_id' => 'customer_id', // ou 'client_id'
            'valor_total' => 'total_amount', // ou 'total', 'grand_total'
            'desconto' => 'discount', // ou 'discount_amount'
            'status' => 'status', // ou 'stage', 'order_status'
            'data_venda' => 'order_date', // ou 'date', 'created_at'
            'observacoes' => 'notes',
        ],
        'atividade' => [
            'assunto' => 'subject', // ou 'title', 'name'
            'tipo' => 'type', // ou 'activity_type'
            'descricao' => 'description', // ou 'notes', 'body'
            'data_vencimento' => 'due_date', // ou 'deadline', 'scheduled_at'
            'concluida' => 'done', // ou 'completed', 'is_completed'
            'responsavel' => 'assigned_to', // ou 'owner_id', 'user_id'
        ],
    ],

    // =====================================================
    // DEBUG & LOGS
    // =====================================================

    'debug' => [
        'log_requests' => false, // logar todas requisições
        'log_responses' => false, // logar todas respostas
        'throw_on_error' => true, // lançar exceção em erro
    ],
];
