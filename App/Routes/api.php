<?php

use App\Core\Router;
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

$router = new Router();

// Registra middlewares globais
$router->registrarMiddleware('cors', MiddlewareCors::class);
$router->registrarMiddleware('csrf', MiddlewareCsrf::class);
$router->registrarMiddleware('auth', MiddlewareAutenticacao::class);
$router->registrarMiddleware('admin', MiddlewareAdmin::class);
$router->registrarMiddleware('acl', MiddlewareAcl::class);
$router->registrarMiddleware('ratelimit', MiddlewareLimiteRequisicao::class);
$router->registrarMiddleware('security', MiddlewareCabecalhosSeguranca::class);
$router->registrarMiddleware('xss', MiddlewareSanitizadorXss::class);

// Aplica middlewares globais a todas as rotas
$router->grupo([
    'middleware' => ['cors', 'security', 'xss', 'ratelimit', 'csrf']
], function($router) {

    // Inclui rotas de autenticação
    $rotasAutenticacao = require __DIR__ . '/autenticacao.php';
    $rotasAutenticacao($router);

    // Inclui rotas de colaboradores
    $rotasColaborador = require __DIR__ . '/colaborador.php';
    $rotasColaborador($router);

    // Inclui rotas de roles
    $rotasRole = require __DIR__ . '/role.php';
    $rotasRole($router);

    // Inclui rotas de permissões
    $rotasPermissao = require __DIR__ . '/permissao.php';
    $rotasPermissao($router);

    // Inclui rotas de níveis
    $rotasNivel = require __DIR__ . '/nivel.php';
    $rotasNivel($router);

    // Inclui rotas de frota
    $rotasFrota = require __DIR__ . '/frota.php';
    $rotasFrota($router);

    // Inclui rotas de fornecedores
    $rotasFornecedor = require __DIR__ . '/fornecedor.php';
    $rotasFornecedor($router);

    // Inclui rotas de clientes
    $rotasCliente = require __DIR__ . '/cliente.php';
    $rotasCliente($router);

    // Inclui rotas de transportadoras
    $rotasTransportadora = require __DIR__ . '/transportadora.php';
    $rotasTransportadora($router);

    // Inclui rotas de grupos de produtos
    $rotasGruposProdutos = require __DIR__ . '/grupos_produtos.php';
    $rotasGruposProdutos($router);

    // Inclui rotas de loja
    $rotasLoja = require __DIR__ . '/loja.php';
    $rotasLoja($router);

    // Inclui rotas de cidades
    $rotasCidade = require __DIR__ . '/cidade.php';
    $rotasCidade($router);

    // Inclui rotas de situações de vendas
    $rotasSituacaoVenda = require __DIR__ . '/situacao_venda.php';
    $rotasSituacaoVenda($router);

    // Inclui rotas de tipos de endereços
    $rotasTipoEndereco = require __DIR__ . '/tipo_endereco.php';
    $rotasTipoEndereco($router);

    // Inclui rotas de tipos de contatos
    $rotasTipoContato = require __DIR__ . '/tipo_contato.php';
    $rotasTipoContato($router);

    // Inclui rotas de login attempts (proteção brute force)
    $rotasLoginAttempts = require __DIR__ . '/login_attempts.php';
    $rotasLoginAttempts($router);

    // Inclui rotas de auditoria
    $rotasAuditoria = require __DIR__ . '/auditoria.php';
    $rotasAuditoria($router);

    // Rota /me (requer autenticação)
    $router->grupo(['middleware' => ['auth']], function($router) {
        $router->get('/me', [\App\Controllers\Autenticacao\ControllerAutenticacao::class, 'obterUsuarioAutenticado']);
    });

    // Rota de health check
    $router->get('/health', function() {
        return [
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ];
    });

    // Rota raiz da API
    $router->get('/', function() {
        return [
            'nome' => 'Ecletech API',
            'versao' => '1.0.0',
            'descricao' => 'API RESTful com PHP',
            'documentacao' => '/docs'
        ];
    });
});

return $router;
