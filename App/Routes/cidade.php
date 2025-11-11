<?php

use App\Controllers\Cidade\ControllerCidade;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de cidades
 */
return function($router) {
    // Grupo de rotas de cidades (requer autenticação e permissões)
    $router->grupo([
        'prefixo' => 'cidades',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /cidades - Listar cidades
        $router->get('/', [ControllerCidade::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('cidade.visualizar'));

        // GET /cidades/estatisticas - Obter estatísticas de cidades
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id} para não conflitar
        $router->get('/estatisticas', [ControllerCidade::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('cidade.visualizar'));

        // GET /cidades/{id} - Buscar cidade por ID
        $router->get('/{id}', [ControllerCidade::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('cidade.visualizar'));

        // POST /cidades - Criar nova cidade
        $router->post('/', [ControllerCidade::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('cidade.criar'));

        // PUT /cidades/{id} - Atualizar cidade
        $router->put('/{id}', [ControllerCidade::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('cidade.editar'));

        // DELETE /cidades/{id} - Deletar cidade (soft delete)
        $router->delete('/{id}', [ControllerCidade::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('cidade.deletar'));
    });
};
