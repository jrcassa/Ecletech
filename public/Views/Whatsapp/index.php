<?php
session_start();

require_once dirname(__DIR__, 3) . "/vendor/autoload.php";
require_once dirname(__DIR__, 3) . "/autoload.php";

use App\Core\Autenticacao;
use App\Core\BancoDados;

// Verifica autenticação
$auth = new Autenticacao();
if (!$auth->estaAutenticado()) {
    header('Location: /login');
    exit;
}

// Verifica ACL para WhatsApp
$db = BancoDados::obterInstancia();
$usuarioId = $auth->obterUsuarioId();

// Verifica permissão de acesso ao módulo WhatsApp
$stmt = $db->executar(
    "SELECT COUNT(*) as total FROM permissoes_usuario
     WHERE usuario_id = ? AND modulo = 'whatsapp' AND acessar = 1",
    [$usuarioId]
);
$permissao = $stmt->fetch();

if (!$permissao || $permissao['total'] == 0) {
    // Verifica se é admin (nível 5 ou 0)
    $stmtUser = $db->executar("SELECT nivel FROM administrador WHERE id = ?", [$usuarioId]);
    $user = $stmtUser->fetch();

    if (!$user || !in_array($user['nivel'], ['0', '5'])) {
        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Acesso Negado</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='bg-light'>
            <div class='container mt-5'>
                <div class='alert alert-danger'>
                    <h4 class='alert-heading'><i class='fas fa-exclamation-triangle'></i> Acesso Negado</h4>
                    <p>Você não tem permissão para acessar o módulo WhatsApp.</p>
                    <hr>
                    <p class='mb-0'>Entre em contato com o administrador do sistema.</p>
                </div>
                <a href='/' class='btn btn-primary'>Voltar ao Início</a>
            </div>
        </body>
        </html>";
        exit;
    }
}

// Carrega o HTML
require __DIR__ . '/whatsapp.html';
