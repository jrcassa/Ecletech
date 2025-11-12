<?php

use App\Controllers\CentroDeCusto\ControllerCentroDeCusto;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de centro de custo (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'centro-de-custo',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /centro-de-custo - Listar centros de custo
        $router->get('/', [ControllerCentroDeCusto::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('centro_de_custo.visualizar'));

        // GET /centro-de-custo/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerCentroDeCusto::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('centro_de_custo.visualizar'));

        // GET /centro-de-custo/{id} - Buscar centro de custo por ID
        $router->get('/{id}', [ControllerCentroDeCusto::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('centro_de_custo.visualizar'));

        // POST /centro-de-custo - Criar novo centro de custo
        $router->post('/', [ControllerCentroDeCusto::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('centro_de_custo.criar'));

        // PUT /centro-de-custo/{id} - Atualizar centro de custo
        $router->put('/{id}', [ControllerCentroDeCusto::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('centro_de_custo.editar'));

        // DELETE /centro-de-custo/{id} - Deletar centro de custo (soft delete)
        $router->delete('/{id}', [ControllerCentroDeCusto::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('centro_de_custo.deletar'));
    });
};
