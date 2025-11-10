<?php

use App\Core\Roteador;
use App\Middleware\MiddlewareAutenticacao;
use App\Middleware\MiddlewareAdmin;
use App\Middleware\MiddlewareAcl;
use App\Middleware\MiddlewareCors;
use App\Middleware\MiddlewareCsrf;
use App\Middleware\MiddlewareLimiteRequisicao;
use App\Middleware\MiddlewareCabecalhosSeguranca;
use App\Middleware\MiddlewareSanitizadorXss;

/**
 * Configuração de rotas da API
 */

$roteador = new Roteador();

// Registra middlewares globais
$roteador->registrarMiddleware('cors', MiddlewareCors::class);
$roteador->registrarMiddleware('csrf', MiddlewareCsrf::class);
$roteador->registrarMiddleware('auth', MiddlewareAutenticacao::class);
$roteador->registrarMiddleware('admin', MiddlewareAdmin::class);
$roteador->registrarMiddleware('acl', MiddlewareAcl::class);
$roteador->registrarMiddleware('ratelimit', MiddlewareLimiteRequisicao::class);
$roteador->registrarMiddleware('security', MiddlewareCabecalhosSeguranca::class);
$roteador->registrarMiddleware('xss', MiddlewareSanitizadorXss::class);

// Aplica middlewares globais a todas as rotas
$roteador->grupo([
    'middleware' => ['cors', 'security', 'xss', 'ratelimit', 'csrf']
], function($roteador) {

    // Inclui rotas de autenticação
    $rotasAutenticacao = require __DIR__ . '/autenticacao.php';
    $rotasAutenticacao($roteador);

    // Inclui rotas de colaboradores
    $rotasColaborador = require __DIR__ . '/colaborador.php';
    $rotasColaborador($roteador);

    // Inclui rotas de roles
    $rotasRole = require __DIR__ . '/role.php';
    $rotasRole($roteador);

    // Inclui rotas de permissões
    $rotasPermissao = require __DIR__ . '/permissao.php';
    $rotasPermissao($roteador);

    // Inclui rotas de frota
    $rotasFrota = require __DIR__ . '/frota.php';
    $rotasFrota($roteador);

    // Inclui rotas de administradores
    $rotasAdministrador = require __DIR__ . '/administrador.php';
    $rotasAdministrador($roteador);

    // Rota de health check
    $roteador->get('/health', function() {
        return [
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ];
    });

    // Rota raiz da API
    $roteador->get('/', function() {
        return [
            'nome' => 'Ecletech API',
            'versao' => '1.0.0',
            'descricao' => 'API RESTful com PHP',
            'documentacao' => '/docs'
        ];
    });
});

return $roteador;
