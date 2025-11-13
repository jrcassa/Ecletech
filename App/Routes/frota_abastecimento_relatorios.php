<?php

use App\Controllers\FrotaAbastecimento\ControllerFrotaAbastecimentoRelatorio;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para relatórios automáticos de abastecimentos
 */
return function($router) {
    // Grupo de rotas de relatórios (requer autenticação)
    $router->grupo([
        'prefixo' => 'frota-abastecimento-relatorios',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // ========== CONFIGURAÇÕES ==========

        // GET /frota-abastecimento-relatorios/minhas-configuracoes - Configurações do usuário logado
        $router->get('/minhas-configuracoes', [ControllerFrotaAbastecimentoRelatorio::class, 'minhasConfiguracoes'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.receber_relatorio'));

        // POST /frota-abastecimento-relatorios/configurar - Criar/atualizar configuração
        $router->post('/configurar', [ControllerFrotaAbastecimentoRelatorio::class, 'configurar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.receber_relatorio'));

        // PATCH /frota-abastecimento-relatorios/configuracao/{id}/desativar - Desativar configuração
        $router->patch('/configuracao/{id}/desativar', [ControllerFrotaAbastecimentoRelatorio::class, 'desativarConfiguracao'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.receber_relatorio'));

        // ========== HISTÓRICO ==========

        // GET /frota-abastecimento-relatorios/historico - Histórico de envios
        $router->get('/historico', [ControllerFrotaAbastecimentoRelatorio::class, 'historico'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.receber_relatorio'));

        // GET /frota-abastecimento-relatorios/log/{id} - Buscar log específico
        $router->get('/log/{id}', [ControllerFrotaAbastecimentoRelatorio::class, 'buscarLog'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.receber_relatorio'));

        // ========== GERAÇÃO MANUAL ==========

        // POST /frota-abastecimento-relatorios/gerar-manual - Gera relatório sem enviar
        $router->post('/gerar-manual', [ControllerFrotaAbastecimentoRelatorio::class, 'gerarManual'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // POST /frota-abastecimento-relatorios/enviar-manual - Gera e envia relatório via WhatsApp
        $router->post('/enviar-manual', [ControllerFrotaAbastecimentoRelatorio::class, 'enviarManual'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.receber_relatorio'));

        // ========== SNAPSHOTS (Admin) ==========

        // GET /frota-abastecimento-relatorios/snapshots - Lista snapshots disponíveis
        $router->get('/snapshots', [ControllerFrotaAbastecimentoRelatorio::class, 'listarSnapshots'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento-relatorios/snapshot/{id} - Buscar snapshot específico
        $router->get('/snapshot/{id}', [ControllerFrotaAbastecimentoRelatorio::class, 'buscarSnapshot'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // POST /frota-abastecimento-relatorios/recalcular-snapshot - Força recálculo (Admin)
        $router->post('/recalcular-snapshot', [ControllerFrotaAbastecimentoRelatorio::class, 'recalcularSnapshot'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));
    });
};
