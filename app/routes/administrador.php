<?php

use App\Controllers\ControllerAdministrador;

/**
 * Rotas de administradores
 * Requerem autenticação + permissão admin
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'administradores',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        $roteador->get('/', [ControllerAdministrador::class, 'listar']);
        $roteador->get('/{id}', [ControllerAdministrador::class, 'buscar']);
        $roteador->post('/', [ControllerAdministrador::class, 'criar']);
        $roteador->put('/{id}', [ControllerAdministrador::class, 'atualizar']);
        $roteador->delete('/{id}', [ControllerAdministrador::class, 'deletar']);
    });
};
