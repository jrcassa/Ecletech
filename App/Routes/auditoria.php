<?php

use App\Controllers\Auditoria\ControllerAuditoria;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de auditoria
 * Requerem autenticação + permissões específicas (ACL)
 */

return function($router) {
    $router->grupo([
        'prefixo' => 'auditoria',
        'middleware' => ['auth', 'admin']
    ], function($router) {
        // Listar registros de auditoria - requer permissão de visualização
        $router->get('/', [ControllerAuditoria::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Buscar registro de auditoria por ID
        $router->get('/{id}', [ControllerAuditoria::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Buscar registros de auditoria por usuário
        $router->get('/usuario/{usuarioId}', [ControllerAuditoria::class, 'buscarPorUsuario'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Buscar registros de auditoria por tabela
        $router->get('/tabela/{tabela}', [ControllerAuditoria::class, 'buscarPorTabela'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Buscar histórico de um registro específico
        $router->get('/registro/{tabela}/{registroId}', [ControllerAuditoria::class, 'buscarPorRegistro'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Listar histórico de login
        $router->get('/login', [ControllerAuditoria::class, 'listarLogin'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Obter estatísticas de auditoria
        $router->get('/estatisticas', [ControllerAuditoria::class, 'estatisticas'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // Limpar registros antigos - requer permissão de gerenciamento
        $router->post('/limpar', [ControllerAuditoria::class, 'limpar'])
            ->middleware(MiddlewareAcl::requer('auditoria.gerenciar'));
    });
};
