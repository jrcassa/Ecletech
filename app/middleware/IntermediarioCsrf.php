<?php

namespace App\Middleware;

use App\Core\TokenCsrf;
use App\Helpers\AuxiliarResposta;

/**
 * Middleware para validação de token CSRF
 */
class IntermediarioCsrf
{
    private TokenCsrf $csrf;

    public function __construct()
    {
        $this->csrf = new TokenCsrf();
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

        $token = TokenCsrf::extrairDaRequisicao();

        if (!$token || !$this->csrf->validar($token)) {
            AuxiliarResposta::proibido('Token CSRF inválido ou expirado');
            return false;
        }

        return true;
    }
}
