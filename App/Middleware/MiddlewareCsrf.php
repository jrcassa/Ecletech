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
     * Rotas excluídas da validação CSRF
     * Estas rotas não requerem token CSRF pois são acessadas antes da autenticação
     */
    private array $rotasExcluidas = [
        '/auth/login',
        '/auth/csrf-token',
        '/register',
        '/verify-email',
        '/forgot-password',
        '/reset-password'
    ];

    public function __construct()
    {
        $this->csrf = new TokenCsrf();
    }

    /**
     * Verifica se a rota atual está excluída da validação CSRF
     */
    private function rotaExcluida(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Remove query string
        $path = parse_url($requestUri, PHP_URL_PATH);

        // Remove o prefixo /public_html/api se existir
        $path = preg_replace('#^/public_html/api#', '', $path);

        foreach ($this->rotasExcluidas as $rotaExcluida) {
            if (str_contains($path, $rotaExcluida)) {
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

        if (!$token || !$this->csrf->validar($token)) {
            AuxiliarResposta::proibido('Token CSRF inválido ou expirado');
            return false;
        }

        return true;
    }
}
