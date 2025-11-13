<?php

use App\Controllers\FrotaAbastecimento\ControllerFrotaAbastecimentoMetrica;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para métricas de abastecimentos
 */
return function($router) {
    // Grupo de rotas de métricas (requer autenticação e permissão de visualizar)
    $router->grupo([
        'prefixo' => 'frota-abastecimento-metricas',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /frota-abastecimento-metricas/dashboard - Dashboard geral de métricas
        $router->get('/dashboard', [ControllerFrotaAbastecimentoMetrica::class, 'dashboard'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-metricas/ranking-consumo - Ranking de consumo
        $router->get('/ranking-consumo', [ControllerFrotaAbastecimentoMetrica::class, 'rankingConsumo'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-metricas/comparativo-periodos - Comparativo entre períodos
        $router->get('/comparativo-periodos', [ControllerFrotaAbastecimentoMetrica::class, 'comparativoPeriodos'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-metricas/abastecimento/{id} - Métricas de um abastecimento específico
        $router->get('/abastecimento/{abastecimento_id}', [ControllerFrotaAbastecimentoMetrica::class, 'obterPorAbastecimento'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-metricas/frota/{id}/historico-consumo - Histórico de consumo de uma frota
        $router->get('/frota/{frota_id}/historico-consumo', [ControllerFrotaAbastecimentoMetrica::class, 'historicoConsumoFrota'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-metricas/frota/{id}/consumo-medio - Consumo médio de uma frota
        $router->get('/frota/{frota_id}/consumo-medio', [ControllerFrotaAbastecimentoMetrica::class, 'consumoMedioFrota'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-metricas/frota/{id}/custo-medio - Custo médio de uma frota
        $router->get('/frota/{frota_id}/custo-medio', [ControllerFrotaAbastecimentoMetrica::class, 'custoMedioFrota'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));
    });
};
