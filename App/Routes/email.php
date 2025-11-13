<?php

use App\Controllers\Email\ControllerEmailEnvio;
use App\Controllers\Email\ControllerEmailConexao;
use App\Controllers\Email\ControllerEmailPainel;
use App\Controllers\Email\ControllerEmailConfiguracao;
use App\Controllers\Email\ControllerEmailTracking;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas do Email (requer autenticação)
    $router->grupo([
        'prefixo' => 'email',
        'middleware' => ['auth']
    ], function($router) {

        // ============================================
        // ROTAS DE ENVIO
        // ============================================

        // POST /email/enviar - Envia email
        $router->post('/enviar', [ControllerEmailEnvio::class, 'enviar'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // GET /email/fila - Lista fila
        $router->get('/fila', [ControllerEmailEnvio::class, 'listarFila'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // DELETE /email/fila/{id} - Cancela email
        $router->delete('/fila/{id}', [ControllerEmailEnvio::class, 'cancelarFila'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // GET /email/estatisticas - Estatísticas
        $router->get('/estatisticas', [ControllerEmailEnvio::class, 'estatisticas'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // GET /email/historico - Histórico de emails
        $router->get('/historico', [ControllerEmailEnvio::class, 'historico'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // ============================================
        // ROTAS DE CONEXÃO SMTP
        // ============================================

        // GET /email/status - Status da conexão (alias)
        $router->get('/status', [ControllerEmailConexao::class, 'status'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // GET /email/conexao/status - Status da conexão
        $router->get('/conexao/status', [ControllerEmailConexao::class, 'status'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // POST /email/testar-conexao - Testa conexão SMTP (alias)
        $router->post('/testar-conexao', [ControllerEmailConexao::class, 'testar'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // POST /email/conexao/testar - Testa conexão SMTP
        $router->post('/conexao/testar', [ControllerEmailConexao::class, 'testar'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // GET /email/conexao/info - Informações SMTP
        $router->get('/conexao/info', [ControllerEmailConexao::class, 'info'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // ============================================
        // ROTAS DO PAINEL
        // ============================================

        // GET /email/painel/dashboard - Dashboard
        $router->get('/painel/dashboard', [ControllerEmailPainel::class, 'dashboard'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // GET /email/painel/historico - Histórico de emails (alias)
        $router->get('/painel/historico', [ControllerEmailEnvio::class, 'historico'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // POST /email/painel/processar - Processa fila manualmente
        $router->post('/painel/processar', [ControllerEmailPainel::class, 'processar'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // ============================================
        // ROTAS DE CONFIGURAÇÃO
        // ============================================

        // GET /email/config - Lista configurações
        $router->get('/config', [ControllerEmailConfiguracao::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // GET /email/config/categorias - Lista categorias
        $router->get('/config/categorias', [ControllerEmailConfiguracao::class, 'categorias'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // GET /email/config/{chave} - Obtém configuração
        $router->get('/config/{chave}', [ControllerEmailConfiguracao::class, 'obter'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));

        // POST /email/config/salvar - Salva configuração
        $router->post('/config/salvar', [ControllerEmailConfiguracao::class, 'salvar'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // POST /email/config/sincronizar-entidade - Sincroniza entidade
        $router->post('/config/sincronizar-entidade', [ControllerEmailConfiguracao::class, 'sincronizarEntidade'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // POST /email/config/sincronizar-lote - Sincroniza lote
        $router->post('/config/sincronizar-lote', [ControllerEmailConfiguracao::class, 'sincronizarLote'])
            ->middleware(MiddlewareAcl::requer('email.alterar'));

        // ============================================
        // ROTAS DE TRACKING
        // ============================================

        // GET /email/track/stats/{tracking_code} - Estatísticas de tracking
        $router->get('/track/stats/{tracking_code}', [ControllerEmailTracking::class, 'estatisticas'])
            ->middleware(MiddlewareAcl::requer('email.acessar'));
    });

    // ============================================
    // ROTAS PÚBLICAS DE TRACKING (sem autenticação)
    // ============================================

    // GET /email/track/open/{tracking_code} - Pixel de rastreamento
    $router->get('/email/track/open/{tracking_code}', [ControllerEmailTracking::class, 'rastrearAbertura']);

    // GET /email/track/click/{tracking_code} - Rastreamento de cliques
    $router->get('/email/track/click/{tracking_code}', [ControllerEmailTracking::class, 'rastrearClique']);
};
