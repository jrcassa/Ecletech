<?php

use App\Controllers\Crm\ControllerCrm;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de integrações CRM
 */
return function($router) {
    // Grupo de rotas CRM
    $router->grupo([
        'prefixo' => 'crm',
        'middleware' => ['auth']
    ], function($router) {

        // ===== Integrações =====

        // GET /crm/integracoes - Listar integrações
        $router->get('/integracoes', [ControllerCrm::class, 'listarIntegracoes'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // GET /crm/integracoes/:id - Obter integração específica
        $router->get('/integracoes/{id}', [ControllerCrm::class, 'obterIntegracao'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // POST /crm/integracoes - Criar integração
        $router->post('/integracoes', [ControllerCrm::class, 'criarIntegracao'])
            ->middleware(['admin'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // PUT /crm/integracoes/:id - Atualizar integração
        $router->put('/integracoes/{id}', [ControllerCrm::class, 'atualizarIntegracao'])
            ->middleware(['admin'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // DELETE /crm/integracoes/:id - Deletar integração
        $router->delete('/integracoes/{id}', [ControllerCrm::class, 'deletarIntegracao'])
            ->middleware(['admin'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // POST /crm/integracoes/:id/testar - Testar conexão
        $router->post('/integracoes/{id}/testar', [ControllerCrm::class, 'testarConexao'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // POST /crm/integracoes/:id/sincronizar - Sincronizar manualmente
        $router->post('/integracoes/{id}/sincronizar', [ControllerCrm::class, 'sincronizarManual'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // POST /crm/testar-conexao - Testar conexão (sem salvar)
        $router->post('/testar-conexao', [ControllerCrm::class, 'testarConexaoTemporaria'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // ===== Estatísticas =====

        // GET /crm/estatisticas - Estatísticas da fila
        $router->get('/estatisticas', [ControllerCrm::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // ===== Logs =====

        // GET /crm/logs - Logs recentes
        $router->get('/logs', [ControllerCrm::class, 'obterLogs'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // GET /crm/logs/:entidade/:id - Logs de um registro específico
        $router->get('/logs/{entidade}/{id}', [ControllerCrm::class, 'obterLogsPorRegistro'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // ===== Fila =====

        // GET /crm/fila - Itens na fila
        $router->get('/fila', [ControllerCrm::class, 'obterFila'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // POST /crm/fila - Enfileirar item manualmente
        $router->post('/fila', [ControllerCrm::class, 'enfileirar'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // ===== Operações CRUD no CRM =====

        // POST /crm/:entidade - Criar no CRM
        $router->post('/{entidade}', [ControllerCrm::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // PUT /crm/:entidade/:externalId - Atualizar no CRM
        $router->put('/{entidade}/{externalId}', [ControllerCrm::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));

        // GET /crm/:entidade/:externalId - Buscar no CRM
        $router->get('/{entidade}/{externalId}', [ControllerCrm::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('crm.visualizar'));

        // DELETE /crm/:entidade/:externalId - Deletar no CRM
        $router->delete('/{entidade}/{externalId}', [ControllerCrm::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('crm.gerenciar'));
    });
};
