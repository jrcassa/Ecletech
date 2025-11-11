<?php

use App\Controllers\Nivel\ControllerNivel;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de níveis
 * Requerem autenticação + permissões específicas via ACL
 */

return function($router) {
    $router->grupo([
        'prefixo' => 'niveis',
        'middleware' => ['auth', 'admin']
    ], function($router) {
        // Listar níveis - requer permissão de visualização
        $router->get('/', [ControllerNivel::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('niveis.visualizar'));

        // Buscar nível por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerNivel::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('niveis.visualizar'));

        // Criar nível - requer permissão de criação
        $router->post('/', [ControllerNivel::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('niveis.criar'));

        // Atualizar nível - requer permissão de edição
        $router->put('/{id}', [ControllerNivel::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('niveis.editar'));

        // Deletar nível - requer permissão de exclusão
        $router->delete('/{id}', [ControllerNivel::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('niveis.deletar'));
    });
};
