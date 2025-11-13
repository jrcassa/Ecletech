<?php

use App\Controllers\S3\ControllerS3Configuracao;
use App\Controllers\S3\ControllerS3Upload;
use App\Controllers\S3\ControllerS3Download;
use App\Controllers\S3\ControllerS3Listagem;
use App\Controllers\S3\ControllerS3Status;
use App\Middleware\MiddlewareAcl;

return function($router) {
    // Grupo de rotas do S3 (requer autenticação)
    $router->grupo([
        'prefixo' => 's3',
        'middleware' => ['auth']
    ], function($router) {

        // ============================================
        // ROTAS DE STATUS E CONFIGURAÇÃO
        // ============================================

        // GET /s3/status - Status geral do S3
        $router->get('/status', [ControllerS3Status::class, 'status'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // POST /s3/testar-conexao - Testa conexão com S3
        $router->post('/testar-conexao', [ControllerS3Status::class, 'testarConexao'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // GET /s3/info - Informações do S3 configurado
        $router->get('/info', [ControllerS3Status::class, 'info'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // POST /s3/habilitar - Habilita serviço S3
        $router->post('/habilitar', [ControllerS3Status::class, 'habilitar'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // POST /s3/desabilitar - Desabilita serviço S3
        $router->post('/desabilitar', [ControllerS3Status::class, 'desabilitar'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // GET /s3/health - Health check completo
        $router->get('/health', [ControllerS3Status::class, 'health'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // ============================================
        // ROTAS DE CONFIGURAÇÃO
        // ============================================

        // GET /s3/config - Lista todas as configurações
        $router->get('/config', [ControllerS3Configuracao::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // GET /s3/config/{chave} - Obtém configuração específica
        $router->get('/config/{chave}', [ControllerS3Configuracao::class, 'obter'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // POST /s3/config/salvar - Salva configuração
        $router->post('/config/salvar', [ControllerS3Configuracao::class, 'salvar'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // POST /s3/config/salvar-lote - Salva múltiplas configurações
        $router->post('/config/salvar-lote', [ControllerS3Configuracao::class, 'salvarLote'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // GET /s3/config/validar - Valida configurações obrigatórias
        $router->get('/config/validar', [ControllerS3Configuracao::class, 'validarConfiguracao'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // POST /s3/config/limpar-cache - Limpa cache de configurações
        $router->post('/config/limpar-cache', [ControllerS3Configuracao::class, 'limparCache'])
            ->middleware(MiddlewareAcl::requer('s3.configurar'));

        // ============================================
        // ROTAS DE UPLOAD
        // ============================================

        // POST /s3/upload - Upload de arquivo
        $router->post('/upload', [ControllerS3Upload::class, 'upload'])
            ->middleware(MiddlewareAcl::requer('s3.upload'));

        // POST /s3/upload/base64 - Upload de arquivo base64
        $router->post('/upload/base64', [ControllerS3Upload::class, 'uploadBase64'])
            ->middleware(MiddlewareAcl::requer('s3.upload'));

        // POST /s3/upload/pasta - Upload de pasta inteira
        $router->post('/upload/pasta', [ControllerS3Upload::class, 'uploadPasta'])
            ->middleware(MiddlewareAcl::requer('s3.upload'));

        // ============================================
        // ROTAS DE ARQUIVOS
        // ============================================

        // GET /s3/arquivos - Lista arquivos
        $router->get('/arquivos', [ControllerS3Listagem::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/arquivos/{id} - Obtém arquivo por ID
        $router->get('/arquivos/{id}', [ControllerS3Download::class, 'obter'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/arquivos/uuid/{uuid} - Obtém arquivo por UUID
        $router->get('/arquivos/uuid/{uuid}', [ControllerS3Download::class, 'obterPorUuid'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // PUT /s3/arquivos/{id} - Atualiza metadados
        $router->put('/arquivos/{id}', [ControllerS3Upload::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('s3.alterar'));

        // DELETE /s3/arquivos/{id} - Deleta arquivo
        $router->delete('/arquivos/{id}', [ControllerS3Upload::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('s3.deletar'));

        // POST /s3/arquivos/{id}/restaurar - Restaura arquivo deletado
        $router->post('/arquivos/{id}/restaurar', [ControllerS3Upload::class, 'restaurar'])
            ->middleware(MiddlewareAcl::requer('s3.alterar'));

        // GET /s3/arquivos/deletados - Lista arquivos deletados
        $router->get('/arquivos/deletados', [ControllerS3Listagem::class, 'arquivosDeletados'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/arquivos/entidade/{tipo}/{id} - Lista arquivos de uma entidade
        $router->get('/arquivos/entidade/{tipo}/{id}', [ControllerS3Listagem::class, 'listarPorEntidade'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // ============================================
        // ROTAS DE DOWNLOAD
        // ============================================

        // GET /s3/download/{id} - Gera URL assinada para download
        $router->get('/download/{id}', [ControllerS3Download::class, 'download'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/download/uuid/{uuid} - Gera URL assinada por UUID
        $router->get('/download/uuid/{uuid}', [ControllerS3Download::class, 'downloadPorUuid'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // POST /s3/download/lote - Gera URLs assinadas em lote
        $router->post('/download/lote', [ControllerS3Download::class, 'downloadLote'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // ============================================
        // ROTAS DE ESTATÍSTICAS E HISTÓRICO
        // ============================================

        // GET /s3/estatisticas - Estatísticas de armazenamento
        $router->get('/estatisticas', [ControllerS3Listagem::class, 'estatisticas'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/historico - Lista histórico de operações
        $router->get('/historico', [ControllerS3Listagem::class, 'listarHistorico'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/historico/estatisticas - Estatísticas de histórico
        $router->get('/historico/estatisticas', [ControllerS3Listagem::class, 'estatisticasHistorico'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/historico/uploads-recentes - Uploads recentes
        $router->get('/historico/uploads-recentes', [ControllerS3Listagem::class, 'uploadsRecentes'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/historico/falhas-recentes - Falhas recentes
        $router->get('/historico/falhas-recentes', [ControllerS3Listagem::class, 'falhasRecentes'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));

        // GET /s3/historico/atividade - Atividade por período
        $router->get('/historico/atividade', [ControllerS3Listagem::class, 'atividade'])
            ->middleware(MiddlewareAcl::requer('s3.acessar'));
    });
};
