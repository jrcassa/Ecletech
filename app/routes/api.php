<?php

use App\Core\Roteador;
use App\Middleware\IntermediarioAutenticacao;
use App\Middleware\IntermediarioAdmin;
use App\Middleware\IntermediarioAcl;
use App\Middleware\IntermediarioCors;
use App\Middleware\IntermediarioCsrf;
use App\Middleware\IntermediarioLimiteRequisicao;
use App\Middleware\IntermediarioCabecalhosSeguranca;
use App\Middleware\IntermediarioSanitizadorXss;

/**
 * Configuração de rotas da API
 */

$roteador = new Roteador();

// Registra middlewares globais
$roteador->registrarMiddleware('cors', IntermediarioCors::class);
$roteador->registrarMiddleware('csrf', IntermediarioCsrf::class);
$roteador->registrarMiddleware('auth', IntermediarioAutenticacao::class);
$roteador->registrarMiddleware('admin', IntermediarioAdmin::class);
$roteador->registrarMiddleware('acl', IntermediarioAcl::class);
$roteador->registrarMiddleware('ratelimit', IntermediarioLimiteRequisicao::class);
$roteador->registrarMiddleware('security', IntermediarioCabecalhosSeguranca::class);
$roteador->registrarMiddleware('xss', IntermediarioSanitizadorXss::class);

// Aplica middlewares globais a todas as rotas
$roteador->grupo([
    'middleware' => ['cors', 'security', 'xss', 'ratelimit']
], function($roteador) {

    // Inclui rotas de autenticação
    $rotasAutenticacao = require __DIR__ . '/autenticacao.php';
    $rotasAutenticacao($roteador);

    // Inclui rotas de administradores
    $rotasAdministrador = require __DIR__ . '/administrador.php';
    $rotasAdministrador($roteador);

    // Inclui rotas de roles
    $rotasRole = require __DIR__ . '/role.php';
    $rotasRole($roteador);

    // Inclui rotas de permissões
    $rotasPermissao = require __DIR__ . '/permissao.php';
    $rotasPermissao($roteador);

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
