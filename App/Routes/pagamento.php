<?php

use App\Controllers\Pagamento\ControllerPagamento;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas de pagamento (requer autenticação e admin)
    $router->grupo([
        'prefixo' => 'pagamento',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /pagamento - Listar pagamentos
        $router->get('/', [ControllerPagamento::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('pagamento.visualizar'));

        // GET /pagamento/estatisticas - Estatísticas (rota específica antes do {id})
        $router->get('/estatisticas', [ControllerPagamento::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('pagamento.visualizar'));

        // GET /pagamento/{id} - Buscar pagamento por ID
        $router->get('/{id}', [ControllerPagamento::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('pagamento.visualizar'));

        // POST /pagamento - Criar novo pagamento
        $router->post('/', [ControllerPagamento::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('pagamento.criar'));

        // PUT /pagamento/{id} - Atualizar pagamento
        $router->put('/{id}', [ControllerPagamento::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('pagamento.editar'));

        // DELETE /pagamento/{id} - Deletar pagamento (soft delete)
        $router->delete('/{id}', [ControllerPagamento::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('pagamento.deletar'));

        // POST /pagamento/{id}/liquidar - Liquidar pagamento
        $router->post('/{id}/liquidar', [ControllerPagamento::class, 'liquidar'])
            ->middleware(MiddlewareAcl::requer('pagamento.liquidar'));
    });
};
