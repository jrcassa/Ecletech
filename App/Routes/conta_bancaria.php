<?php

use App\Controllers\ContaBancaria\ControllerContaBancaria;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de contas bancárias (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'conta-bancaria',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /conta-bancaria - Listar contas bancárias
        $router->get('/', [ControllerContaBancaria::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('conta_bancaria.visualizar'));

        // GET /conta-bancaria/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerContaBancaria::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('conta_bancaria.visualizar'));

        // GET /conta-bancaria/{id} - Buscar conta bancária por ID
        $router->get('/{id}', [ControllerContaBancaria::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('conta_bancaria.visualizar'));

        // POST /conta-bancaria - Criar nova conta bancária
        $router->post('/', [ControllerContaBancaria::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('conta_bancaria.criar'));

        // PUT /conta-bancaria/{id} - Atualizar conta bancária
        $router->put('/{id}', [ControllerContaBancaria::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('conta_bancaria.editar'));

        // DELETE /conta-bancaria/{id} - Deletar conta bancária (soft delete)
        $router->delete('/{id}', [ControllerContaBancaria::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('conta_bancaria.deletar'));
    });
};
