<?php

use App\Controllers\FormaDePagamento\ControllerFormaDePagamento;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de forma de pagamento (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'forma-de-pagamento',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /forma-de-pagamento - Listar formas de pagamento
        $router->get('/', [ControllerFormaDePagamento::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('forma_de_pagamento.visualizar'));

        // GET /forma-de-pagamento/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerFormaDePagamento::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('forma_de_pagamento.visualizar'));

        // GET /forma-de-pagamento/{id} - Buscar forma de pagamento por ID
        $router->get('/{id}', [ControllerFormaDePagamento::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('forma_de_pagamento.visualizar'));

        // POST /forma-de-pagamento - Criar nova forma de pagamento
        $router->post('/', [ControllerFormaDePagamento::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('forma_de_pagamento.criar'));

        // PUT /forma-de-pagamento/{id} - Atualizar forma de pagamento
        $router->put('/{id}', [ControllerFormaDePagamento::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('forma_de_pagamento.editar'));

        // DELETE /forma-de-pagamento/{id} - Deletar forma de pagamento (soft delete)
        $router->delete('/{id}', [ControllerFormaDePagamento::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('forma_de_pagamento.deletar'));
    });
};
