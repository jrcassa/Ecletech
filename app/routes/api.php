<?php

use App\Core\Roteador;
use App\Controllers\ControladorAutenticacao;
use App\Controllers\ControladorAdministrador;
use App\Controllers\ControladorPermissao;
use App\Controllers\ControladorRole;
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

    // Rotas públicas (sem autenticação)
    $roteador->grupo(['prefixo' => 'auth'], function($roteador) {
        $roteador->post('/login', [ControladorAutenticacao::class, 'login']);
        $roteador->post('/refresh', [ControladorAutenticacao::class, 'refresh']);
        $roteador->get('/csrf-token', [ControladorAutenticacao::class, 'obterTokenCsrf']);
    });

    // Rotas protegidas (requerem autenticação)
    $roteador->grupo([
        'prefixo' => 'auth',
        'middleware' => ['auth']
    ], function($roteador) {
        $roteador->post('/logout', [ControladorAutenticacao::class, 'logout']);
        $roteador->get('/me', [ControladorAutenticacao::class, 'obterUsuarioAutenticado']);
        $roteador->post('/alterar-senha', [ControladorAutenticacao::class, 'alterarSenha']);
    });

    // Rotas de administradores (requerem autenticação + permissão admin)
    // Mantendo middleware 'admin' para garantir nível de admin
    $roteador->grupo([
        'prefixo' => 'administradores',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        $roteador->get('/', [ControladorAdministrador::class, 'listar']);
        $roteador->get('/{id}', [ControladorAdministrador::class, 'buscar']);
        $roteador->post('/', [ControladorAdministrador::class, 'criar']);
        $roteador->put('/{id}', [ControladorAdministrador::class, 'atualizar']);
        $roteador->delete('/{id}', [ControladorAdministrador::class, 'deletar']);
    });

    // Rotas de roles (funções) - requerem autenticação + permissões específicas via ACL
    $roteador->grupo([
        'prefixo' => 'roles',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        $roteador->get('/', [ControladorRole::class, 'listar']);
        $roteador->get('/{id}', [ControladorRole::class, 'buscar']);
        $roteador->get('/{id}/permissoes', [ControladorRole::class, 'obterPermissoes']);
        $roteador->post('/{id}/permissoes', [ControladorRole::class, 'atribuirPermissoes']);
    });

    // Rotas de permissões - requerem autenticação + permissões específicas via ACL
    $roteador->grupo([
        'prefixo' => 'permissoes',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        $roteador->get('/', [ControladorPermissao::class, 'listar']);
        $roteador->get('/{id}', [ControladorPermissao::class, 'buscar']);
        $roteador->get('/modulos/listar', [ControladorPermissao::class, 'listarPorModulo']);
    });

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
