<?php

use App\Controllers\ControllerRole;
use App\Middleware\IntermediarioAcl;

/**
 * Rotas de roles (funções)
 * Requerem autenticação + permissões específicas via ACL
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'roles',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        // Listar roles - requer permissão de visualização
        $roteador->get('/', [ControllerRole::class, 'listar'])
            ->middleware(IntermediarioAcl::requer('roles.visualizar'));

        // Buscar role por ID - requer permissão de visualização
        $roteador->get('/{id}', [ControllerRole::class, 'buscar'])
            ->middleware(IntermediarioAcl::requer('roles.visualizar'));

        // Obter permissões de uma role - requer permissão de visualização
        $roteador->get('/{id}/permissoes', [ControllerRole::class, 'obterPermissoes'])
            ->middleware(IntermediarioAcl::requer('roles.visualizar'));

        // Atribuir permissões a uma role - requer permissão de edição
        $roteador->post('/{id}/permissoes', [ControllerRole::class, 'atribuirPermissoes'])
            ->middleware(IntermediarioAcl::requer('roles.editar'));
    });
};
