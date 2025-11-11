<?php

use App\Controllers\TipoContato\ControllerTipoContato;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de tipos de contatos
 */
return function($router) {
    // Grupo de rotas de tipos de contatos (requer autenticação e permissões)
    $router->grupo([
        'prefixo' => 'tipos-contatos',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /tipos-contatos - Listar tipos de contatos
        $router->get('/', [ControllerTipoContato::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('tipo_contato.visualizar'));

        // GET /tipos-contatos/estatisticas - Obter estatísticas de tipos de contatos
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id} para não conflitar
        $router->get('/estatisticas', [ControllerTipoContato::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('tipo_contato.visualizar'));

        // GET /tipos-contatos/{id} - Buscar tipo de contato por ID
        $router->get('/{id}', [ControllerTipoContato::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('tipo_contato.visualizar'));

        // POST /tipos-contatos - Criar novo tipo de contato
        $router->post('/', [ControllerTipoContato::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('tipo_contato.criar'));

        // PUT /tipos-contatos/{id} - Atualizar tipo de contato
        $router->put('/{id}', [ControllerTipoContato::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('tipo_contato.editar'));

        // DELETE /tipos-contatos/{id} - Deletar tipo de contato (soft delete)
        $router->delete('/{id}', [ControllerTipoContato::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('tipo_contato.deletar'));
    });
};
