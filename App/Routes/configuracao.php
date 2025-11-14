<?php

use App\Controllers\Configuracao\ControllerConfiguracao;

/**
 * Rotas de Configurações
 */
return function ($router) {
    // Rotas protegidas por autenticação e ACL
    $router->grupo([
        'prefix' => '/configuracoes',
        'middleware' => ['auth', 'acl']
    ], function($router) {

        // Configurações de Brute Force
        // Requer permissão: config.visualizar para GET, config.editar para PUT
        $router->get('/brute-force', [ControllerConfiguracao::class, 'obterBruteForce']);
        $router->put('/brute-force', [ControllerConfiguracao::class, 'atualizarBruteForce']);
    });
};
