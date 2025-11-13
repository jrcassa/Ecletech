<?php

use App\Controllers\FrotaAbastecimento\ControllerFrotaAbastecimento;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de abastecimentos da frota
 */
return function($router) {
    // Grupo de rotas de abastecimento (requer autenticação)
    $router->grupo([
        'prefixo' => 'frota-abastecimento',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // ========== ADMIN/GESTOR: Gerenciar Ordens ==========

        // GET /frota-abastecimento - Listar abastecimentos
        // Admin vê todos, Motorista vê apenas seus
        $router->get('/', [ControllerFrotaAbastecimento::class, 'listar'])
            ->middleware(MiddlewareAcl::requerUm([
                'frota_abastecimento.visualizar',
                'frota_abastecimento.abastecer'
            ]));

        // GET /frota-abastecimento/estatisticas - Estatísticas gerais
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id}
        $router->get('/estatisticas', [ControllerFrotaAbastecimento::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.visualizar'));

        // GET /frota-abastecimento/meus-pendentes - Ordens pendentes do motorista
        $router->get('/meus-pendentes', [ControllerFrotaAbastecimento::class, 'meusPendentes'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.abastecer'));

        // GET /frota-abastecimento/meu-historico - Histórico do motorista
        $router->get('/meu-historico', [ControllerFrotaAbastecimento::class, 'meuHistorico'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.abastecer'));

        // GET /frota-abastecimento/{id} - Buscar abastecimento por ID
        $router->get('/{id}', [ControllerFrotaAbastecimento::class, 'buscar'])
            ->middleware(MiddlewareAcl::requerUm([
                'frota_abastecimento.visualizar',
                'frota_abastecimento.abastecer'
            ]));

        // POST /frota-abastecimento - Criar ordem de abastecimento
        $router->post('/', [ControllerFrotaAbastecimento::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.criar'));

        // PUT /frota-abastecimento/{id} - Atualizar ordem (apenas aguardando)
        $router->put('/{id}', [ControllerFrotaAbastecimento::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.editar'));

        // PATCH /frota-abastecimento/{id}/finalizar - Finalizar abastecimento (Motorista)
        $router->patch('/{id}/finalizar', [ControllerFrotaAbastecimento::class, 'finalizar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.abastecer'));

        // PATCH /frota-abastecimento/{id}/cancelar - Cancelar ordem
        $router->patch('/{id}/cancelar', [ControllerFrotaAbastecimento::class, 'cancelar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.cancelar'));

        // DELETE /frota-abastecimento/{id} - Deletar abastecimento (soft delete)
        $router->delete('/{id}', [ControllerFrotaAbastecimento::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('frota_abastecimento.deletar'));

        // ========== COMPROVANTES (S3) ==========

        // POST /frota-abastecimento/{id}/comprovante - Anexar comprovante
        $router->post('/{id}/comprovante', [ControllerFrotaAbastecimento::class, 'anexarComprovante'])
            ->middleware(MiddlewareAcl::requerUm([
                'frota_abastecimento.editar',
                'frota_abastecimento.abastecer'
            ]));

        // GET /frota-abastecimento/{id}/comprovante - Obter comprovantes
        $router->get('/{id}/comprovante', [ControllerFrotaAbastecimento::class, 'obterComprovantes'])
            ->middleware(MiddlewareAcl::requerUm([
                'frota_abastecimento.visualizar',
                'frota_abastecimento.abastecer'
            ]));
    });
};
