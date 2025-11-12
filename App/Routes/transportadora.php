<?php

use App\Controllers\Transportadora\ControllerTransportadora;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de transportadoras (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'transportadora',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /transportadora - Listar transportadoras
        $router->get('/', [ControllerTransportadora::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('transportadora.visualizar'));

        // GET /transportadora/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerTransportadora::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('transportadora.visualizar'));

        // GET /transportadora/{id} - Buscar transportadora por ID
        $router->get('/{id}', [ControllerTransportadora::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('transportadora.visualizar'));

        // POST /transportadora - Criar nova transportadora
        $router->post('/', [ControllerTransportadora::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('transportadora.criar'));

        // PUT /transportadora/{id} - Atualizar transportadora
        $router->put('/{id}', [ControllerTransportadora::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('transportadora.editar'));

        // DELETE /transportadora/{id} - Deletar transportadora (soft delete)
        $router->delete('/{id}', [ControllerTransportadora::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('transportadora.deletar'));
    });
};
