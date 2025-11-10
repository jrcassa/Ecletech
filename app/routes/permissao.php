<?php

use App\Controllers\ControllerPermissao;

/**
 * Rotas de permissões
 * Requerem autenticação + permissões específicas via ACL
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'permissoes',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        $roteador->get('/', [ControllerPermissao::class, 'listar']);
        $roteador->get('/{id}', [ControllerPermissao::class, 'buscar']);
        $roteador->get('/modulos/listar', [ControllerPermissao::class, 'listarPorModulo']);
    });
};
