<?php

/**
 * Configuração do Provider GestãoClick
 */

return [
    // URL base da API
    'api_base_url' => 'https://api.gestaoclick.com/v1',

    // Limites de requisições
    'rate_limit' => 100, // requisições por minuto
    'batch_size' => 100, // registros por requisição

    // Timeout de requisições (em segundos)
    'timeout' => 30,

    // Endpoints da API
    'endpoints' => [
        'cliente' => [
            'listar' => '/customers',
            'criar' => '/customers',
            'atualizar' => '/customers/{id}',
            'buscar' => '/customers/{id}',
            'deletar' => '/customers/{id}'
        ],
        'produto' => [
            'listar' => '/products',
            'criar' => '/products',
            'atualizar' => '/products/{id}',
            'buscar' => '/products/{id}',
            'deletar' => '/products/{id}'
        ],
        'venda' => [
            'listar' => '/deals',
            'criar' => '/deals',
            'atualizar' => '/deals/{id}',
            'buscar' => '/deals/{id}',
            'deletar' => '/deals/{id}'
        ],
        'atividade' => [
            'listar' => '/activities',
            'criar' => '/activities',
            'atualizar' => '/activities/{id}',
            'buscar' => '/activities/{id}',
            'deletar' => '/activities/{id}'
        ]
    ],

    // Mapeamento de nomes de entidades (Ecletech => GestaoClick)
    'mapeamentos' => [
        'cliente' => 'customers',
        'produto' => 'products',
        'venda' => 'deals',
        'atividade' => 'activities'
    ],

    // Mapeamento de status
    'status_venda' => [
        'pendente' => 'pending',
        'aprovado' => 'won',
        'cancelado' => 'lost',
        'em_andamento' => 'in_progress'
    ],

    // Mapeamento inverso (GestaoClick => Ecletech)
    'status_venda_reverso' => [
        'pending' => 'pendente',
        'won' => 'aprovado',
        'lost' => 'cancelado',
        'in_progress' => 'em_andamento'
    ],

    // Campos obrigatórios por entidade
    'campos_obrigatorios' => [
        'cliente' => ['name', 'document'],
        'produto' => ['name', 'price'],
        'venda' => ['customer_id', 'total_value'],
        'atividade' => ['type', 'subject']
    ],

    // Retry em caso de falha
    'retry' => [
        'max_tentativas' => 3,
        'delay_inicial' => 2, // segundos
        'multiplicador' => 2   // backoff exponencial
    ]
];
