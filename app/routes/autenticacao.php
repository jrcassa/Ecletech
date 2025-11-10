<?php

use App\Controllers\ControllerAutenticacao;

/**
 * Rotas de autenticação
 */

return function($roteador) {
    // Rotas públicas (sem autenticação)
    $roteador->grupo(['prefixo' => 'auth'], function($roteador) {
        $roteador->post('/login', [ControllerAutenticacao::class, 'login']);
        $roteador->post('/refresh', [ControllerAutenticacao::class, 'refresh']);
        $roteador->get('/csrf-token', [ControllerAutenticacao::class, 'obterTokenCsrf']);
    });

    // Rotas protegidas (requerem autenticação)
    $roteador->grupo([
        'prefixo' => 'auth',
        'middleware' => ['auth']
    ], function($roteador) {
        $roteador->post('/logout', [ControllerAutenticacao::class, 'logout']);
        $roteador->get('/me', [ControllerAutenticacao::class, 'obterUsuarioAutenticado']);
        $roteador->post('/alterar-senha', [ControllerAutenticacao::class, 'alterarSenha']);
    });
};
