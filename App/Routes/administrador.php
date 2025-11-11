<?php

use App\Controllers\Administrador\ControllerAdministrador;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de administradores
 * Todas as rotas requerem autenticação + permissões específicas (ACL)
 *
 * Permissões necessárias:
 * - colaboradores.visualizar: Listar e buscar administradores
 * - colaboradores.criar: Criar novos administradores
 * - colaboradores.editar: Atualizar administradores existentes
 * - colaboradores.deletar: Deletar administradores
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
            ->middleware(MiddlewareAcl::requer('colaboradores.visualizar'));

        // Buscar administrador por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerAdministrador::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.visualizar'));

        // Criar administrador - requer permissão de criação
        $router->post('/', [ControllerAdministrador::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.criar'));

        // Atualizar administrador - requer permissão de edição
        $router->put('/{id}', [ControllerAdministrador::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.editar'));

        // Deletar administrador - requer permissão de exclusão
        $router->delete('/{id}', [ControllerAdministrador::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.deletar'));
    });
};
