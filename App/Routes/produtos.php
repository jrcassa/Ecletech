<?php

use App\Controllers\Produtos\ControllerProdutos;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de produtos (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'produtos',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /produtos - Listar produtos
        $router->get('/', [ControllerProdutos::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('produtos.visualizar'));

        // GET /produtos/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerProdutos::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('produtos.visualizar'));

        // GET /produtos/{id} - Buscar produto por ID
        $router->get('/{id}', [ControllerProdutos::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('produtos.visualizar'));

        // POST /produtos - Criar novo produto
        $router->post('/', [ControllerProdutos::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('produtos.criar'));

        // PUT /produtos/{id} - Atualizar produto
        $router->put('/{id}', [ControllerProdutos::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('produtos.editar'));

        // DELETE /produtos/{id} - Deletar produto (soft delete)
        $router->delete('/{id}', [ControllerProdutos::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('produtos.deletar'));
    });
};
