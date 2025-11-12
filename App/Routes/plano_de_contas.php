<?php

use App\Controllers\PlanoDeContas\ControllerPlanoDeContas;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de plano de contas (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'plano-de-contas',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /plano-de-contas - Listar contas
        $router->get('/', [ControllerPlanoDeContas::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.visualizar'));

        // GET /plano-de-contas/principais - Listar contas principais (rota específica antes do {id})
        $router->get('/principais', [ControllerPlanoDeContas::class, 'listarPrincipais'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.visualizar'));

        // GET /plano-de-contas/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerPlanoDeContas::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.visualizar'));

        // GET /plano-de-contas/arvore - Árvore hierárquica (rota específica antes do {id})
        $router->get('/arvore', [ControllerPlanoDeContas::class, 'obterArvore'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.visualizar'));

        // GET /plano-de-contas/{id} - Buscar conta por ID
        $router->get('/{id}', [ControllerPlanoDeContas::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.visualizar'));

        // GET /plano-de-contas/{id}/filhas - Listar contas filhas
        $router->get('/{id}/filhas', [ControllerPlanoDeContas::class, 'listarFilhas'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.visualizar'));

        // POST /plano-de-contas - Criar nova conta
        $router->post('/', [ControllerPlanoDeContas::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.criar'));

        // PUT /plano-de-contas/{id} - Atualizar conta
        $router->put('/{id}', [ControllerPlanoDeContas::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.editar'));

        // DELETE /plano-de-contas/{id} - Deletar conta (soft delete)
        $router->delete('/{id}', [ControllerPlanoDeContas::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('plano_de_contas.deletar'));
    });
};
