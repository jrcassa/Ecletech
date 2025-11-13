<?php

use App\Controllers\Whatsapp\ControllerWhatsappEnvio;
use App\Controllers\Whatsapp\ControllerWhatsappConexao;
use App\Controllers\Whatsapp\ControllerWhatsappPainel;
use App\Controllers\Whatsapp\ControllerWhatsappWebhook;
use App\Controllers\Whatsapp\ControllerWhatsappConfiguracao;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas do WhatsApp (requer autenticação)
    $router->grupo([
        'prefixo' => 'whatsapp',
        'middleware' => ['auth']
    ], function($router) {

        // ============================================
        // ROTAS DE ENVIO
        // ============================================

        // POST /whatsapp/enviar - Envia mensagem
        $router->post('/enviar', [ControllerWhatsappEnvio::class, 'enviar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // GET /whatsapp/fila - Lista fila
        $router->get('/fila', [ControllerWhatsappEnvio::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // GET /whatsapp/fila/{id} - Busca mensagem
        $router->get('/fila/{id}', [ControllerWhatsappEnvio::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // DELETE /whatsapp/fila/{id} - Cancela mensagem
        $router->delete('/fila/{id}', [ControllerWhatsappEnvio::class, 'cancelar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // GET /whatsapp/estatisticas - Estatísticas
        $router->get('/estatisticas', [ControllerWhatsappEnvio::class, 'estatisticas'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // ============================================
        // ROTAS DE CONEXÃO
        // ============================================

        // GET /whatsapp/conexao/status - Verifica status
        $router->get('/conexao/status', [ControllerWhatsappConexao::class, 'status'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // POST /whatsapp/conexao/criar - Cria instância
        $router->post('/conexao/criar', [ControllerWhatsappConexao::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // POST /whatsapp/conexao/desconectar - Desconecta
        $router->post('/conexao/desconectar', [ControllerWhatsappConexao::class, 'desconectar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // GET /whatsapp/conexao/qrcode - Obtém QR code
        $router->get('/conexao/qrcode', [ControllerWhatsappConexao::class, 'qrcode'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // ============================================
        // ROTAS DO PAINEL
        // ============================================

        // GET /whatsapp/painel/dashboard - Dashboard
        $router->get('/painel/dashboard', [ControllerWhatsappPainel::class, 'dashboard'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // GET /whatsapp/painel/historico - Histórico
        $router->get('/painel/historico', [ControllerWhatsappPainel::class, 'historico'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // POST /whatsapp/painel/processar - Processa fila
        $router->post('/painel/processar', [ControllerWhatsappPainel::class, 'processar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // ============================================
        // ROTAS DE CONFIGURAÇÃO
        // ============================================

        // GET /whatsapp/config - Lista configurações
        $router->get('/config', [ControllerWhatsappConfiguracao::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // GET /whatsapp/config/{chave} - Obtém configuração
        $router->get('/config/{chave}', [ControllerWhatsappConfiguracao::class, 'obter'])
            ->middleware(MiddlewareAcl::requer('whatsapp.acessar'));

        // POST /whatsapp/config/salvar - Salva configuração
        $router->post('/config/salvar', [ControllerWhatsappConfiguracao::class, 'salvar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // POST /whatsapp/config/resetar/{chave} - Reseta configuração
        $router->post('/config/resetar/{chave}', [ControllerWhatsappConfiguracao::class, 'resetar'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // POST /whatsapp/config/sincronizar-entidade - Sincroniza entidade
        $router->post('/config/sincronizar-entidade', [ControllerWhatsappConfiguracao::class, 'sincronizarEntidade'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));

        // POST /whatsapp/config/sincronizar-lote - Sincroniza lote
        $router->post('/config/sincronizar-lote', [ControllerWhatsappConfiguracao::class, 'sincronizarLote'])
            ->middleware(MiddlewareAcl::requer('whatsapp.alterar'));
    });

    // ============================================
    // WEBHOOK (SEM AUTENTICAÇÃO)
    // ============================================

    // POST /whatsapp/webhook - Recebe webhook
    $router->post('/whatsapp/webhook', [ControllerWhatsappWebhook::class, 'receber']);

    // GET /whatsapp/webhook - Validação do webhook
    $router->get('/whatsapp/webhook', [ControllerWhatsappWebhook::class, 'validarWebhook']);
};
