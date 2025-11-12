<?php

use App\Controllers\Cliente\ControllerCliente;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de clientes (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'cliente',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /cliente - Listar clientes
        $router->get('/', [ControllerCliente::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('cliente.visualizar'));

        // GET /cliente/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerCliente::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('cliente.visualizar'));

        // GET /cliente/{id} - Buscar cliente por ID
        $router->get('/{id}', [ControllerCliente::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('cliente.visualizar'));

        // POST /cliente - Criar novo cliente
        $router->post('/', [ControllerCliente::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('cliente.criar'));

        // PUT /cliente/{id} - Atualizar cliente
        $router->put('/{id}', [ControllerCliente::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('cliente.editar'));

        // DELETE /cliente/{id} - Deletar cliente (soft delete)
        $router->delete('/{id}', [ControllerCliente::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('cliente.deletar'));
    });
};
