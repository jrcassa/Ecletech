<?php

use App\Controllers\TipoEndereco\ControllerTipoEndereco;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de tipos de endereços
 */
return function($router) {
    // Grupo de rotas de tipos de endereços (requer autenticação e permissões)
    $router->grupo([
        'prefixo' => 'tipos-enderecos',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /tipos-enderecos - Listar tipos de endereços
        $router->get('/', [ControllerTipoEndereco::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('tipo_endereco.visualizar'));

        // GET /tipos-enderecos/estatisticas - Obter estatísticas de tipos de endereços
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id} para não conflitar
        $router->get('/estatisticas', [ControllerTipoEndereco::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('tipo_endereco.visualizar'));

        // GET /tipos-enderecos/{id} - Buscar tipo de endereço por ID
        $router->get('/{id}', [ControllerTipoEndereco::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('tipo_endereco.visualizar'));

        // POST /tipos-enderecos - Criar novo tipo de endereço
        $router->post('/', [ControllerTipoEndereco::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('tipo_endereco.criar'));

        // PUT /tipos-enderecos/{id} - Atualizar tipo de endereço
        $router->put('/{id}', [ControllerTipoEndereco::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('tipo_endereco.editar'));

        // DELETE /tipos-enderecos/{id} - Deletar tipo de endereço (soft delete)
        $router->delete('/{id}', [ControllerTipoEndereco::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('tipo_endereco.deletar'));
    });
};
