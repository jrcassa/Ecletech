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
        '^/reset-password$'
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

        // Remove o prefixo /public_html/api se existir
        $path = preg_replace('#^/public_html/api#', '', $path);

        // Normaliza path removendo trailing slash (exceto para root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Verifica cada padrão de rota excluída usando regex exato
        foreach ($this->rotasExcluidas as $padraoExcluido) {
            if (preg_match('#' . $padraoExcluido . '#', $path)) {
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

        // Token válido: gera um novo token para a próxima requisição (uso único)
        $novoToken = $this->csrf->gerar();

        // Envia o novo token no header da resposta para que o frontend atualize automaticamente
        header('X-New-CSRF-Token: ' . $novoToken);

        return true;
    }
}
