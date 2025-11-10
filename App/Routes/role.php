<?php

use App\Controllers\Role\ControllerRole;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de roles (funções)
 * Requerem autenticação + permissões específicas via ACL
 */

return function($router) {
    $router->grupo([
        'prefixo' => 'roles',
        'middleware' => ['auth', 'admin']
    ], function($router) {
        // Listar roles - requer permissão de visualização
        $router->get('/', [ControllerRole::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('roles.visualizar'));

        // Buscar role por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerRole::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('roles.visualizar'));

        // Obter permissões de uma role - requer permissão de visualização
        $router->get('/{id}/permissoes', [ControllerRole::class, 'obterPermissoes'])
            ->middleware(MiddlewareAcl::requer('roles.visualizar'));

        // Atribuir permissões a uma role - requer permissão de edição
        $router->post('/{id}/permissoes', [ControllerRole::class, 'atribuirPermissoes'])
            ->middleware(MiddlewareAcl::requer('roles.editar'));
    });
};
