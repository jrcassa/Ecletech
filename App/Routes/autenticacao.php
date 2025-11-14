<?php

use App\Controllers\Autenticacao\ControllerAutenticacao;

/**
 * Rotas de autenticação
 */

return function($router) {
    // Rotas públicas (sem autenticação)
    $router->grupo(['prefixo' => 'auth'], function($router) {
        $router->post('/login', [ControllerAutenticacao::class, 'login']);
        $router->post('/refresh', [ControllerAutenticacao::class, 'refresh']);
        $router->get('/csrf-token', [ControllerAutenticacao::class, 'obterTokenCsrf']);
    });

    // Rotas protegidas (requerem autenticação)
    $router->grupo([
        'prefixo' => 'auth',
        'middleware' => ['auth']
    ], function($router) {
        $router->post('/logout', [ControllerAutenticacao::class, 'logout']);
        $router->get('/me', [ControllerAutenticacao::class, 'me']);
        $router->put('/perfil', [ControllerAutenticacao::class, 'atualizarPerfil']);
        $router->post('/alterar-senha', [ControllerAutenticacao::class, 'alterarSenha']);
    });
};
