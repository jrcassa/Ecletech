<?php

use App\Controllers\Login\ControllerLoginAttempt;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas para gerenciamento de tentativas de login e proteção contra brute force
 */
return function($router) {
    // Grupo de rotas para gestão de login attempts (requer autenticação e permissões admin)
    $router->grupo([
        'prefixo' => 'login-attempts',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /login-attempts - Listar todas as tentativas de login
        $router->get('/', [ControllerLoginAttempt::class, 'listarTentativas'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // GET /login-attempts/estatisticas - Obter estatísticas gerais
        $router->get('/estatisticas', [ControllerLoginAttempt::class, 'obterEstatisticas'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));
    });

    // Grupo de rotas para gestão de bloqueios
    $router->grupo([
        'prefixo' => 'login-bloqueios',
        'middleware' => ['auth', 'admin']
    ], function($router) {

        // GET /login-bloqueios - Listar todos os bloqueios ativos
        $router->get('/', [ControllerLoginAttempt::class, 'listarBloqueios'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // GET /login-bloqueios/verificar - Verificar status de bloqueio
        $router->get('/verificar', [ControllerLoginAttempt::class, 'verificarBloqueio'])
            ->middleware(MiddlewareAcl::requer('auditoria.visualizar'));

        // POST /login-bloqueios - Criar bloqueio manual
        $router->post('/', [ControllerLoginAttempt::class, 'criarBloqueio'])
            ->middleware(MiddlewareAcl::requer('config.editar'));

        // DELETE /login-bloqueios/email - Desbloquear email
        $router->delete('/email', [ControllerLoginAttempt::class, 'desbloquearEmail'])
            ->middleware(MiddlewareAcl::requer('config.editar'));

        // DELETE /login-bloqueios/ip - Desbloquear IP
        $router->delete('/ip', [ControllerLoginAttempt::class, 'desbloquearIp'])
            ->middleware(MiddlewareAcl::requer('config.editar'));

        // DELETE /login-bloqueios/{id} - Desbloquear por ID
        $router->delete('/{id}', [ControllerLoginAttempt::class, 'desbloquearPorId'])
            ->middleware(MiddlewareAcl::requer('config.editar'));
    });
};
