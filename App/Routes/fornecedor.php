<?php

use App\Controllers\Fornecedor\ControllerFornecedor;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de fornecedores (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'fornecedor',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /fornecedor - Listar fornecedores
        $router->get('/', [ControllerFornecedor::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('fornecedor.visualizar'));

        // GET /fornecedor/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerFornecedor::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('fornecedor.visualizar'));

        // GET /fornecedor/{id} - Buscar fornecedor por ID
        $router->get('/{id}', [ControllerFornecedor::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('fornecedor.visualizar'));

        // POST /fornecedor - Criar novo fornecedor
        $router->post('/', [ControllerFornecedor::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('fornecedor.criar'));

        // PUT /fornecedor/{id} - Atualizar fornecedor
        $router->put('/{id}', [ControllerFornecedor::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('fornecedor.editar'));

        // DELETE /fornecedor/{id} - Deletar fornecedor (soft delete)
        $router->delete('/{id}', [ControllerFornecedor::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('fornecedor.deletar'));
    });
};
