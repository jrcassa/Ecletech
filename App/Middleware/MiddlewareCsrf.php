<?php

namespace App\Middleware;

use App\Core\TokenCsrf;
use App\Helpers\AuxiliarResposta;

/**
 * Middleware para validação de token CSRF
 */
class MiddlewareCsrf
{
    private TokenCsrf $csrf;

    /**
     * Rotas excluídas da validação CSRF (regex exato)
     * Estas rotas não requerem token CSRF pois são acessadas antes da autenticação
     * Cada padrão deve fazer match exato com a rota (use ^ para início e $ para fim)
     */
    private array $rotasExcluidas = [
        '^/auth/login$',
        '^/auth/csrf-token$',
        '^/register$',
        '^/verify-email$',
        '^/forgot-password$',
        '^/reset-password$',
        '^/diagnostico/.*$'  // Rotas de diagnóstico (remover em produção)
    ];

    public function __construct()
    {
        $this->csrf = new TokenCsrf();
    }

    /**
     * Verifica se a rota atual está excluída da validação CSRF
     * Usa regex para match exato, prevenindo bypass de segurança
     */
    private function rotaExcluida(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Remove query string para obter apenas o path
        $path = parse_url($requestUri, PHP_URL_PATH);

        // Remove tudo até /public_html/api (inclusive) usando regex
        // Funciona independente do que vier antes:
        // - /ecletech_v2/public_html/api/auth/login → /auth/login
        // - /qualquer/coisa/public_html/api/auth/login → /auth/login
        // - /public_html/api/auth/login → /auth/login
        $path = preg_replace('#^.*?/public_html/api#', '', $path);

        // Se ainda não começar com /, adiciona (para casos onde não há /public_html/api)
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Normaliza path removendo trailing slash (exceto para root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Log para debug
        error_log("MiddlewareCsrf: URI original: " . $requestUri);
        error_log("MiddlewareCsrf: Path normalizado: " . $path);

        // Verifica cada padrão de rota excluída usando regex exato
        foreach ($this->rotasExcluidas as $padraoExcluido) {
            if (preg_match('#' . $padraoExcluido . '#', $path)) {
                error_log("MiddlewareCsrf: Rota excluída da validação CSRF: " . $path);
                return true;
            }
        }

        return false;
    }

    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        // CSRF apenas para métodos que modificam dados
        if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }

        if (!$this->csrf->estaHabilitado()) {
            return true;
        }

        // Verifica se a rota está excluída da validação CSRF
        if ($this->rotaExcluida()) {
            return true;
        }

        $token = TokenCsrf::extrairDaRequisicao();

        if (!$token) {
            error_log("MiddlewareCsrf: Token não fornecido. URI: " . ($_SERVER['REQUEST_URI'] ?? 'desconhecido'));
            error_log("MiddlewareCsrf: Session ID: " . session_id());
            error_log("MiddlewareCsrf: Headers: " . json_encode(getallheaders()));
            AuxiliarResposta::proibido('Token CSRF inválido ou expirado');
            return false;
        }

        if (!$this->csrf->validar($token)) {
            error_log("MiddlewareCsrf: Token inválido. URI: " . ($_SERVER['REQUEST_URI'] ?? 'desconhecido'));
            error_log("MiddlewareCsrf: Token fornecido: " . substr($token, 0, 10) . '...' . substr($token, -10));
            error_log("MiddlewareCsrf: Session ID: " . session_id());
            error_log("MiddlewareCsrf: Session tem token?: " . (isset($_SESSION['csrf_token']) ? 'SIM' : 'NÃO'));
            if (isset($_SESSION['csrf_token'])) {
                error_log("MiddlewareCsrf: Token da sessão: " . substr($_SESSION['csrf_token'], 0, 10) . '...' . substr($_SESSION['csrf_token'], -10));
                error_log("MiddlewareCsrf: Tempo do token: " . ($_SESSION['csrf_token_time'] ?? 'não definido'));
                error_log("MiddlewareCsrf: Tempo atual: " . time());
                error_log("MiddlewareCsrf: Diferença: " . (time() - ($_SESSION['csrf_token_time'] ?? 0)) . " segundos");
            }
            AuxiliarResposta::proibido('Token CSRF inválido ou expirado');
            return false;
        }

        // Token válido: gera um novo token para a próxima requisição (uso único)
        $novoToken = $this->csrf->gerar();

        // Envia o novo token no header da resposta para que o frontend atualize automaticamente
        header('X-New-CSRF-Token: ' . $novoToken);

        return true;
    }
}
