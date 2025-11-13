<?php

use App\Controllers\Servico\ControllerServico;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de serviços
 */
return function($router) {
    // Grupo de rotas de serviços (requer autenticação)
    $router->grupo([
        'prefixo' => 'servicos',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // ========== ROTAS DE LEITURA ==========

        // GET /servicos - Listar serviços
        $router->get('/', [ControllerServico::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('servicos.visualizar'));

        // GET /servicos/ativos - Listar serviços ativos (para selects)
        $router->get('/ativos', [ControllerServico::class, 'listarAtivos'])
            ->middleware(MiddlewareAcl::requer('servicos.visualizar'));

        // GET /servicos/estatisticas - Estatísticas gerais
        $router->get('/estatisticas', [ControllerServico::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('servicos.visualizar'));

        // GET /servicos/{id} - Buscar serviço por ID
        $router->get('/{id}', [ControllerServico::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('servicos.visualizar'));

        // ========== ROTAS DE ESCRITA ==========

        // POST /servicos - Criar serviço
        $router->post('/', [ControllerServico::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('servicos.criar'));

        // POST /servicos/importar - Importar ou atualizar serviço de sistema externo
        $router->post('/importar', [ControllerServico::class, 'importar'])
            ->middleware(MiddlewareAcl::requer('servicos.criar'));

        // PUT /servicos/{id} - Atualizar serviço
        $router->put('/{id}', [ControllerServico::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('servicos.editar'));

        // PATCH /servicos/{id}/status - Ativar/Desativar serviço
        $router->patch('/{id}/status', [ControllerServico::class, 'alterarStatus'])
            ->middleware(MiddlewareAcl::requer('servicos.editar'));

        // DELETE /servicos/{id} - Deletar serviço (soft delete)
        $router->delete('/{id}', [ControllerServico::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('servicos.deletar'));

        // PATCH /servicos/{id}/restaurar - Restaurar serviço deletado
        $router->patch('/{id}/restaurar', [ControllerServico::class, 'restaurar'])
            ->middleware(MiddlewareAcl::requer('servicos.editar'));
    });
};
