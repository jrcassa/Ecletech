<?php

use App\Controllers\Recebimento\ControllerRecebimento;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de recebimento (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'recebimento',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /recebimento - Listar recebimentos
        $router->get('/', [ControllerRecebimento::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('recebimento.visualizar'));

        // GET /recebimento/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerRecebimento::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('recebimento.visualizar'));

        // GET /recebimento/{id} - Buscar recebimento por ID
        $router->get('/{id}', [ControllerRecebimento::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('recebimento.visualizar'));

        // POST /recebimento - Criar novo recebimento
        $router->post('/', [ControllerRecebimento::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('recebimento.criar'));

        // PUT /recebimento/{id} - Atualizar recebimento
        $router->put('/{id}', [ControllerRecebimento::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('recebimento.editar'));

        // DELETE /recebimento/{id} - Deletar recebimento (soft delete)
        $router->delete('/{id}', [ControllerRecebimento::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('recebimento.deletar'));

        // POST /recebimento/{id}/baixar - Baixar/Liquidar recebimento
        $router->post('/{id}/baixar', [ControllerRecebimento::class, 'baixar'])
            ->middleware(MiddlewareAcl::requer('recebimento.baixar'));
    });
};
