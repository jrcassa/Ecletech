<?php

use App\Controllers\Configuracao\ControllerConfiguracao;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de Configurações
 */
return function ($router) {
    // Rotas protegidas por autenticação e ACL
    $router->grupo([
        'prefixo' => 'configuracoes',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // Configurações de Brute Force
        // GET /configuracoes/brute-force - Requer permissão: config.visualizar
        $router->get('/brute-force', [ControllerConfiguracao::class, 'obterBruteForce'])
            ->middleware(MiddlewareAcl::requer('config.visualizar'));

        // PUT /configuracoes/brute-force - Requer permissão: config.editar
        $router->put('/brute-force', [ControllerConfiguracao::class, 'atualizarBruteForce'])
            ->middleware(MiddlewareAcl::requer('config.editar'));
    });
};
