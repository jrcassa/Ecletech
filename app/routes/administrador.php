<?php

use App\Controllers\ControllerAdministrador;
use App\Middleware\IntermediarioAcl;

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
            ->middleware(IntermediarioAcl::requer('admins.visualizar'));

        // Buscar administrador por ID - requer permissão de visualização
        $roteador->get('/{id}', [ControllerAdministrador::class, 'buscar'])
            ->middleware(IntermediarioAcl::requer('admins.visualizar'));

        // Criar administrador - requer permissão de criação
        $roteador->post('/', [ControllerAdministrador::class, 'criar'])
            ->middleware(IntermediarioAcl::requer('admins.criar'));

        // Atualizar administrador - requer permissão de edição
        $roteador->put('/{id}', [ControllerAdministrador::class, 'atualizar'])
            ->middleware(IntermediarioAcl::requer('admins.editar'));

        // Deletar administrador - requer permissão de exclusão
        $roteador->delete('/{id}', [ControllerAdministrador::class, 'deletar'])
            ->middleware(IntermediarioAcl::requer('admins.deletar'));
    });
};
