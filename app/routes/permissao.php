<?php

use App\Controllers\ControllerPermissao;
use App\Middleware\IntermediarioAcl;

/**
 * Rotas de permissões
 * Requerem autenticação + permissões específicas via ACL
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'permissoes',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        // Listar permissões - requer permissão de visualização
        $roteador->get('/', [ControllerPermissao::class, 'listar'])
            ->middleware(IntermediarioAcl::requer('permissoes.visualizar'));

        // Buscar permissão por ID - requer permissão de visualização
        $roteador->get('/{id}', [ControllerPermissao::class, 'buscar'])
            ->middleware(IntermediarioAcl::requer('permissoes.visualizar'));

        // Listar permissões por módulo - requer permissão de visualização
        $roteador->get('/modulos/listar', [ControllerPermissao::class, 'listarPorModulo'])
            ->middleware(IntermediarioAcl::requer('permissoes.visualizar'));
    });
};
