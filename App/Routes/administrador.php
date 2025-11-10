<?php

use App\Controllers\Administrador\ControllerAdministrador;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de administradores
 * Todas as rotas requerem autenticação + permissões específicas (ACL)
 *
 * Permissões necessárias:
 * - administradores.visualizar: Listar e buscar administradores
 * - administradores.criar: Criar novos administradores
 * - administradores.editar: Atualizar administradores existentes
 * - administradores.deletar: Deletar administradores
 */

return function($router) {
    $router->grupo([
        'prefixo' => 'administradores',
        'middleware' => ['auth', 'admin']
    ], function($router) {
        // Verifica permissões do usuário atual
        // Não requer permissão específica, apenas autenticação
        $router->get('/permissoes', [ControllerAdministrador::class, 'verificarPermissoes']);

        // Listar administradores - requer permissão de visualização
        $router->get('/', [ControllerAdministrador::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('administradores.visualizar'));

        // Buscar administrador por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerAdministrador::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('administradores.visualizar'));

        // Criar administrador - requer permissão de criação
        $router->post('/', [ControllerAdministrador::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('administradores.criar'));

        // Atualizar administrador - requer permissão de edição
        $router->put('/{id}', [ControllerAdministrador::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('administradores.editar'));

        // Deletar administrador - requer permissão de exclusão
        $router->delete('/{id}', [ControllerAdministrador::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('administradores.deletar'));
    });
};
