<?php

use App\Controllers\SituacaoVenda\ControllerSituacaoVenda;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de situações de vendas
 */
return function($router) {
    // Grupo de rotas de situações de vendas (requer autenticação e permissões)
    $router->grupo([
        'prefixo' => 'situacoes-vendas',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /situacoes-vendas - Listar situações de vendas
        $router->get('/', [ControllerSituacaoVenda::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('situacao_venda.visualizar'));

        // GET /situacoes-vendas/estatisticas - Obter estatísticas de situações de vendas
        // IMPORTANTE: Esta rota deve vir ANTES das rotas com {id} para não conflitar
        $router->get('/estatisticas', [ControllerSituacaoVenda::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('situacao_venda.visualizar'));

        // GET /situacoes-vendas/{id} - Buscar situação de venda por ID
        $router->get('/{id}', [ControllerSituacaoVenda::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('situacao_venda.visualizar'));

        // POST /situacoes-vendas - Criar nova situação de venda
        $router->post('/', [ControllerSituacaoVenda::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('situacao_venda.criar'));

        // PUT /situacoes-vendas/{id} - Atualizar situação de venda
        $router->put('/{id}', [ControllerSituacaoVenda::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('situacao_venda.editar'));

        // DELETE /situacoes-vendas/{id} - Deletar situação de venda (soft delete)
        $router->delete('/{id}', [ControllerSituacaoVenda::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('situacao_venda.deletar'));
    });
};
