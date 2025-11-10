<?php

use App\Controllers\ControllerAdministrador;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de administradores
 * Requerem autenticação + permissões específicas (ACL)
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'administradores',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        // Listar administradores - requer permissão de visualização
        $roteador->get('/', [ControllerAdministrador::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('admins.visualizar'));

        // Buscar administrador por ID - requer permissão de visualização
        $roteador->get('/{id}', [ControllerAdministrador::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('admins.visualizar'));

        // Criar administrador - requer permissão de criação
        $roteador->post('/', [ControllerAdministrador::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('admins.criar'));

        // Atualizar administrador - requer permissão de edição
        $roteador->put('/{id}', [ControllerAdministrador::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('admins.editar'));

        // Deletar administrador - requer permissão de exclusão
        $roteador->delete('/{id}', [ControllerAdministrador::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('admins.deletar'));
    });
};
