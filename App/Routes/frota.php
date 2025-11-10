<?php

use App\Controllers\Frota\ControllerFrota;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento da frota de veículos
 */
return function($router) {
    // Grupo de rotas da frota (requer autenticação e permissões)
    $router->grupo([
        'prefixo' => 'frota',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /frota - Listar veículos da frota
        $router->get('/', [ControllerFrota::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('frota.visualizar'));

        // GET /frota/estatisticas - Obter estatísticas da frota
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id} para não conflitar
        $router->get('/estatisticas', [ControllerFrota::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('frota.visualizar'));

        // GET /frota/{id} - Buscar veículo por ID
        $router->get('/{id}', [ControllerFrota::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('frota.visualizar'));

        // POST /frota - Criar novo veículo
        $router->post('/', [ControllerFrota::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('frota.criar'));

        // PUT /frota/{id} - Atualizar veículo
        $router->put('/{id}', [ControllerFrota::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('frota.editar'));

        // DELETE /frota/{id} - Deletar veículo (soft delete)
        $router->delete('/{id}', [ControllerFrota::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('frota.deletar'));

        // PATCH /frota/{id}/quilometragem - Atualizar quilometragem
        $router->patch('/{id}/quilometragem', [ControllerFrota::class, 'atualizarQuilometragem'])
            ->middleware(MiddlewareAcl::requer('frota.editar'));

        // PATCH /frota/{id}/status - Atualizar status
        $router->patch('/{id}/status', [ControllerFrota::class, 'atualizarStatus'])
            ->middleware(MiddlewareAcl::requer('frota.editar'));
    });
};
