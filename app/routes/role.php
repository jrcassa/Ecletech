<?php

use App\Controllers\ControllerRole;

/**
 * Rotas de roles (funções)
 * Requerem autenticação + permissões específicas via ACL
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'roles',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        $roteador->get('/', [ControllerRole::class, 'listar']);
        $roteador->get('/{id}', [ControllerRole::class, 'buscar']);
        $roteador->get('/{id}/permissoes', [ControllerRole::class, 'obterPermissoes']);
        $roteador->post('/{id}/permissoes', [ControllerRole::class, 'atribuirPermissoes']);
    });
};
