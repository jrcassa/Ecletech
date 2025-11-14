<?php

use App\Controllers\Venda\ControllerVenda;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de vendas (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'venda',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /venda - Listar vendas
        $router->get('/', [ControllerVenda::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('venda.listar'));

        // GET /venda/{id} - Buscar venda por ID
        $router->get('/{id}', [ControllerVenda::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('venda.visualizar'));

        // POST /venda - Criar nova venda
        $router->post('/', [ControllerVenda::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('venda.criar'));

        // PUT /venda/{id} - Atualizar venda
        $router->put('/{id}', [ControllerVenda::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('venda.atualizar'));

        // DELETE /venda/{id} - Deletar venda (soft delete)
        $router->delete('/{id}', [ControllerVenda::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('venda.deletar'));

        // POST /venda/{id}/situacao-financeira - Atualizar situação financeira
        $router->post('/{id}/situacao-financeira', [ControllerVenda::class, 'atualizarSituacaoFinanceira'])
            ->middleware(MiddlewareAcl::requer('venda.atualizar'));
    });
};
