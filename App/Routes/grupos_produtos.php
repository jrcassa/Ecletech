<?php

use App\Controllers\GruposProdutos\ControllerGruposProdutos;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de grupos de produtos (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'grupos-produtos',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /grupos-produtos - Listar grupos de produtos
        $router->get('/', [ControllerGruposProdutos::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('grupos_produtos.visualizar'));

        // GET /grupos-produtos/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerGruposProdutos::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('grupos_produtos.visualizar'));

        // GET /grupos-produtos/{id} - Buscar grupo de produtos por ID
        $router->get('/{id}', [ControllerGruposProdutos::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('grupos_produtos.visualizar'));

        // POST /grupos-produtos - Criar novo grupo de produtos
        $router->post('/', [ControllerGruposProdutos::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('grupos_produtos.criar'));

        // PUT /grupos-produtos/{id} - Atualizar grupo de produtos
        $router->put('/{id}', [ControllerGruposProdutos::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('grupos_produtos.editar'));

        // DELETE /grupos-produtos/{id} - Deletar grupo de produtos (soft delete)
        $router->delete('/{id}', [ControllerGruposProdutos::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('grupos_produtos.deletar'));
    });
};
