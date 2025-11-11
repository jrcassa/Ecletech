<?php

use App\Controllers\Estado\ControllerEstado;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de estados
 */
return function($router) {
    // Grupo de rotas de estados (requer autenticação e permissões)
    $router->grupo([
        'prefixo' => 'estados',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /estados - Listar estados
        $router->get('/', [ControllerEstado::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('estado.visualizar'));

        // GET /estados/estatisticas - Obter estatísticas de estados
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id} para não conflitar
        $router->get('/estatisticas', [ControllerEstado::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('estado.visualizar'));

        // GET /estados/{id} - Buscar estado por ID
        $router->get('/{id}', [ControllerEstado::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('estado.visualizar'));

        // POST /estados - Criar novo estado
        $router->post('/', [ControllerEstado::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('estado.criar'));

        // PUT /estados/{id} - Atualizar estado
        $router->put('/{id}', [ControllerEstado::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('estado.editar'));

        // DELETE /estados/{id} - Deletar estado (soft delete)
        $router->delete('/{id}', [ControllerEstado::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('estado.deletar'));
    });
};
