<?php

namespace App\Middleware;

use App\Core\LimitadorRequisicao;
use App\Helpers\AuxiliarResposta;

/**
 * Middleware para limitar requisições (Rate Limiting)
 */
class IntermediarioLimiteRequisicao
{
    private LimitadorRequisicao $limitador;

    public function __construct()
    {
        $this->limitador = new LimitadorRequisicao();
    }

    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        if (!$this->limitador->estaHabilitado()) {
            return true;
        }

        $identificador = LimitadorRequisicao::obterIdentificador();

        // Verifica se está bloqueado
        if ($this->limitador->estaBloqueado($identificador)) {
            $this->adicionarCabecalhosRateLimit($identificador);
            AuxiliarResposta::muitasRequisicoes();
            return false;
        }

        // Verifica se pode fazer a requisição
        if (!$this->limitador->verificar($identificador)) {
            $this->adicionarCabecalhosRateLimit($identificador);
            AuxiliarResposta::muitasRequisicoes();
            return false;
        }

        // Registra a requisição
        $this->limitador->registrar($identificador);

        // Adiciona cabeçalhos informativos
        $this->adicionarCabecalhosRateLimit($identificador);

        return true;
    }

    /**
     * Adiciona cabeçalhos de rate limit
     */
    private function adicionarCabecalhosRateLimit(string $identificador): void
    {
        $restantes = $this->limitador->obterRequisicoesRestantes($identificador);
        $reset = $this->limitador->obterTempoReset($identificador);

        header("X-RateLimit-Limit: " . 100); // Configurável
        header("X-RateLimit-Remaining: " . $restantes);
        header("X-RateLimit-Reset: " . (time() + $reset));
    }
}
