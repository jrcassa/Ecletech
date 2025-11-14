<?php

namespace App\Controllers\Diagnostico;

use App\Controllers\BaseController;
use App\Core\TokenCsrf;
use App\Models\Csrf\ModelCsrfToken;
use App\Core\Configuracao;

/**
 * Controlador para diagnóstico do sistema
 */
class ControllerDiagnostico extends BaseController
{
    /**
     * Diagnóstico completo do sistema CSRF e sessão
     */
    public function csrf(): void
    {
        $diagnostico = [];

        // 1. Informações de Sessão
        $diagnostico['sessao'] = [
            'session_id' => session_id(),
            'session_status' => $this->getSessionStatusName(session_status()),
            'session_name' => session_name(),
            'session_save_path' => session_save_path(),
            'session_save_path_exists' => is_dir(session_save_path()),
            'session_save_path_writable' => is_writable(session_save_path()),
            'session_cookie_params' => session_get_cookie_params(),
            'session_data' => [
                'has_csrf_token' => isset($_SESSION['csrf_token']),
                'csrf_token_time' => $_SESSION['csrf_token_time'] ?? null
            ]
        ];

        // 2. Informações de PHP
        $diagnostico['php'] = [
            'version' => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
            'current_time' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'session.cookie_httponly' => ini_get('session.cookie_httponly'),
            'session.use_only_cookies' => ini_get('session.use_only_cookies'),
            'session.cookie_samesite' => ini_get('session.cookie_samesite'),
            'session.cookie_secure' => ini_get('session.cookie_secure')
        ];

        // 3. Informações de Cookies
        $diagnostico['cookies'] = [
            'received_cookies' => array_keys($_COOKIE),
            'session_cookie_exists' => isset($_COOKIE[session_name()]),
            'auth_token_exists' => isset($_COOKIE['auth_token'])
        ];

        // 4. Informações de Banco de Dados
        try {
            $model = new ModelCsrfToken();
            $diagnostico['banco_dados'] = [
                'conectado' => true,
                'tokens_ativos' => $model->contarAtivos(),
                'tokens_sessao_atual' => $model->contarPorSessao(session_id())
            ];

            // Verificar se a migration 012 foi executada (coluna colaborador_id)
            try {
                $db = \App\Core\Database::obterConexao();
                $stmt = $db->query("SHOW COLUMNS FROM csrf_tokens LIKE 'colaborador_id'");
                $colunaExiste = $stmt->fetch() !== false;
                $diagnostico['banco_dados']['migration_012_executada'] = $colunaExiste;
            } catch (\Exception $e) {
                $diagnostico['banco_dados']['migration_012_executada'] = 'erro: ' . $e->getMessage();
            }
        } catch (\Exception $e) {
            $diagnostico['banco_dados'] = [
                'conectado' => false,
                'erro' => $e->getMessage()
            ];
        }

        // 5. Configurações CSRF
        $config = Configuracao::obterInstancia();
        $diagnostico['csrf'] = [
            'habilitado' => $config->obter('seguranca.csrf_habilitado', true),
            'expiracao' => $config->obter('seguranca.csrf_expiracao', 3600)
        ];

        // 6. Teste de Geração de Token
        try {
            $csrf = new TokenCsrf();
            $token = $csrf->gerar();
            $diagnostico['teste_token'] = [
                'token_gerado' => substr($token, 0, 10) . '...' . substr($token, -10),
                'token_salvo_sessao' => isset($_SESSION['csrf_token']),
                'tokens_coincidem' => ($_SESSION['csrf_token'] ?? '') === $token
            ];
        } catch (\Exception $e) {
            $diagnostico['teste_token'] = [
                'erro' => $e->getMessage()
            ];
        }

        // 7. Informações de Requisição
        $diagnostico['requisicao'] = [
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'desconhecido',
            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'desconhecido',
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'desconhecido',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'
        ];

        $this->sucesso($diagnostico, 'Diagnóstico completo');
    }

    /**
     * Converte o status da sessão para nome legível
     */
    private function getSessionStatusName(int $status): string
    {
        switch ($status) {
            case PHP_SESSION_DISABLED:
                return 'PHP_SESSION_DISABLED';
            case PHP_SESSION_NONE:
                return 'PHP_SESSION_NONE';
            case PHP_SESSION_ACTIVE:
                return 'PHP_SESSION_ACTIVE';
            default:
                return 'DESCONHECIDO';
        }
    }

    /**
     * Health check geral do sistema
     */
    public function health(): void
    {
        $health = [
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ];

        // Verifica conexão com banco de dados
        try {
            $db = \App\Core\Database::obterConexao();
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $health['database'] = 'disconnected';
            $health['status'] = 'degraded';
        }

        // Verifica sessão
        if (session_status() === PHP_SESSION_ACTIVE) {
            $health['session'] = 'active';
        } else {
            $health['session'] = 'inactive';
            $health['status'] = 'degraded';
        }

        $this->sucesso($health, 'Status do sistema');
    }
}
