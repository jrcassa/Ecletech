<?php

use App\Controllers\Permissao\ControllerPermissao;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de permissões
 * Requerem autenticação + permissões específicas via ACL
 */

return function($router) {
    $router->grupo([
        'prefixo' => 'permissoes',
        'middleware' => ['auth', 'admin']
    ], function($router) {
        // Listar permissões - requer permissão de visualização
        $router->get('/', [ControllerPermissao::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('permissoes.visualizar'));

        // Buscar permissão por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerPermissao::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('permissoes.visualizar'));

        // Listar permissões por módulo - requer permissão de visualização
        $router->get('/modulos/listar', [ControllerPermissao::class, 'listarPorModulo'])
            ->middleware(MiddlewareAcl::requer('permissoes.visualizar'));
    });
};
