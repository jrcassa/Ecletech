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
        // Obter permissões do usuário autenticado - disponível para todos os usuários
        // IMPORTANTE: Esta rota deve vir ANTES de /{id} para não conflitar
        $router->get('/usuario', [ControllerPermissao::class, 'obterPermissoesUsuario']);

        // Listar permissões por módulo - requer permissão de visualização
        $router->get('/modulos/listar', [ControllerPermissao::class, 'listarPorModulo'])
            ->middleware(MiddlewareAcl::requer('permissoes.visualizar'));

        // Listar permissões - requer permissão de visualização
        $router->get('/', [ControllerPermissao::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('permissoes.visualizar'));

        // Buscar permissão por ID - requer permissão de visualização
        $router->get('/{id}', [ControllerPermissao::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('permissoes.visualizar'));

        // Criar permissão - requer permissão de criação
        $router->post('/', [ControllerPermissao::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('permissoes.criar'));

        // Atualizar permissão - requer permissão de edição
        $router->put('/{id}', [ControllerPermissao::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('permissoes.editar'));

        // Deletar permissão - requer permissão de exclusão
        $router->delete('/{id}', [ControllerPermissao::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('permissoes.deletar'));
    });
};
