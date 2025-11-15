<?php

/**
 * Configuração do Provider GestãoClick
 * Baseado na API oficial: https://api.beteltecnologia.com
 */

return [
    // URL base da API (conforme Postman collection)
    'api_base_url' => 'https://api.beteltecnologia.com',

    // Limites de requisições
    'rate_limit' => 100, // requisições por minuto
    'batch_size' => 100, // registros por requisição

    // Timeout de requisições (em segundos)
    'timeout' => 30,

    // Endpoints da API (conforme documentação real)
    'endpoints' => [
        'cliente' => [
            'listar' => '/clientes',
            'criar' => '/clientes',
            'atualizar' => '/clientes/{id}',
            'buscar' => '/clientes/{id}',
            'deletar' => '/clientes/{id}'
        ],
        'produto' => [
            'listar' => '/produtos',
            'criar' => '/produtos',
            'atualizar' => '/produtos/{id}',
            'buscar' => '/produtos/{id}',
            'deletar' => '/produtos/{id}'
        ],
        'venda' => [
            'listar' => '/vendas',
            'criar' => '/vendas',
            'atualizar' => '/vendas/{id}',
            'buscar' => '/vendas/{id}',
            'deletar' => '/vendas/{id}'
        ],
        // Atividades não estão disponíveis na API, usar orçamentos como alternativa
        'atividade' => [
            'listar' => '/orcamentos',
            'criar' => '/orcamentos',
            'atualizar' => '/orcamentos/{id}',
            'buscar' => '/orcamentos/{id}',
            'deletar' => '/orcamentos/{id}'
        ]
    ],

    // Paginação (conforme API real)
    'paginacao' => [
        'page_param' => 'pagina',      // API usa "pagina" não "page"
        'limit_param' => 'total_registros_pagina',
        'ordenacao_param' => 'ordenacao',
        'direcao_param' => 'direcao'
    ],

    // Mapeamento de nomes de entidades (Ecletech => GestaoClick)
    'mapeamentos' => [
        'cliente' => 'clientes',
        'produto' => 'produtos',
        'venda' => 'vendas',
        'atividade' => 'orcamentos'
    ],

    // Situação (status ativo/inativo)
    'situacao' => [
        'ativo' => '1',
        'inativo' => '0'
    ],

    // Campos obrigatórios por entidade
    'campos_obrigatorios' => [
        'cliente' => ['tipo_pessoa', 'nome', 'ativo'],
        'produto' => ['nome', 'tipo_produto', 'situacao', 'unidade_venda'],
        'venda' => ['cliente_id', 'vendedor_id', 'data', 'produtos'],
        'atividade' => ['cliente_id', 'vendedor_id', 'data', 'produtos']
    ],

    // Retry em caso de falha
    'retry' => [
        'max_tentativas' => 3,
        'delay_inicial' => 2, // segundos
        'multiplicador' => 2   // backoff exponencial
    ]
];
