<?php

namespace App\Middleware;

use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;

/**
 * Middleware para autenticação
 */
class IntermediarioAutenticacao
{
    private Autenticacao $auth;

    public function __construct()
    {
        $this->auth = new Autenticacao();
    }

    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        if (!$this->auth->estaAutenticado()) {
            AuxiliarResposta::naoAutorizado('Autenticação necessária');
            return false;
        }

        return true;
    }

    /**
     * Obtém o usuário autenticado
     */
    public function obterUsuario(): ?array
    {
        return $this->auth->obterUsuarioAutenticado();
    }
}
