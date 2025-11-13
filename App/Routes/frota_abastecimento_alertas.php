<?php

use App\Controllers\FrotaAbastecimento\ControllerFrotaAbastecimentoAlerta;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para alertas de abastecimentos
 */
return function($router) {
    // Grupo de rotas de alertas (requer autenticação e permissão de visualizar)
    $router->grupo([
        'prefixo' => 'frota-abastecimento-alertas',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /frota-abastecimento-alertas - Listar todos os alertas com filtros
        $router->get('/', [ControllerFrotaAbastecimentoAlerta::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-alertas/dashboard - Dashboard de alertas
        $router->get('/dashboard', [ControllerFrotaAbastecimentoAlerta::class, 'dashboard'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-alertas/criticos - Alertas críticos não visualizados
        $router->get('/criticos', [ControllerFrotaAbastecimentoAlerta::class, 'criticosNaoVisualizados'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-alertas/estatisticas - Estatísticas de alertas por tipo
        $router->get('/estatisticas', [ControllerFrotaAbastecimentoAlerta::class, 'estatisticasPorTipo'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-alertas/{id} - Buscar alerta por ID
        $router->get('/{id}', [ControllerFrotaAbastecimentoAlerta::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-alertas/abastecimento/{id} - Alertas de um abastecimento
        $router->get('/abastecimento/{abastecimento_id}', [ControllerFrotaAbastecimentoAlerta::class, 'porAbastecimento'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-alertas/frota/{id} - Alertas de uma frota
        $router->get('/frota/{frota_id}', [ControllerFrotaAbastecimentoAlerta::class, 'porFrota'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // PATCH /frota-abastecimento-alertas/{id}/visualizar - Marcar alerta como visualizado
        $router->patch('/{id}/visualizar', [ControllerFrotaAbastecimentoAlerta::class, 'marcarVisualizado'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // PATCH /frota-abastecimento-alertas/visualizar-varios - Marcar múltiplos alertas como visualizados
        $router->patch('/visualizar-varios', [ControllerFrotaAbastecimentoAlerta::class, 'marcarVariosVisualizados'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));
    });
};
