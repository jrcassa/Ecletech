<?php

use App\Controllers\Colaborador\ControllerColaborador;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de colaboradores
 * Requerem autenticação + permissões específicas (ACL)
 */

return function($router) {
    $router->grupo([
        'prefixo' => 'colaboradores',
        'middleware' => ['auth', 'admin']
    ], function($router) {
        // Verificar permissões do usuário atual
        $router->get('/permissoes', [ControllerColaborador::class, 'verificarPermissoes']);

        // Listar colaboradores - requer permissão de visualização
        $router->get('/', [ControllerColaborador::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.visualizar'));

        // Buscar colaborador por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerColaborador::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.visualizar'));

        // Criar colaborador - requer permissão de criação
        $router->post('/', [ControllerColaborador::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.criar'));

        // Atualizar colaborador - requer permissão de edição
        $router->put('/{id}', [ControllerColaborador::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.editar'));

        // Deletar colaborador - requer permissão de exclusão
        $router->delete('/{id}', [ControllerColaborador::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.deletar'));
    });
};
