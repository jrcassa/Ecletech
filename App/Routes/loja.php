<?php

use App\Controllers\Loja\ControllerLoja;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento das informações da loja
 * IMPORTANTE: Apenas 1 registro (Singleton)
 */
return function($router) {
    // Grupo de rotas da loja
    $router->grupo([
        'prefixo' => 'loja',
        'middleware' => ['auth']
    ], function($router) {

        // GET /loja - Obter informações da loja (único registro)
        // Qualquer usuário autenticado pode visualizar
        $router->get('/', [ControllerLoja::class, 'obter'])
            ->middleware(MiddlewareAcl::requer('loja.visualizar'));

        // PUT /loja - Atualizar informações da loja (único registro)
        // Apenas administradores podem editar
        $router->put('/', [ControllerLoja::class, 'atualizar'])
            ->middleware(['admin'])
            ->middleware(MiddlewareAcl::requer('loja.editar'));
    });
};
