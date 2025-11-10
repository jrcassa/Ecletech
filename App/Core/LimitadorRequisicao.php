<?php

namespace App\Core;

use App\Models\RateLimit\ModelRateLimit;

/**
 * Classe para limitar requisições (Rate Limiting)
 */
class LimitadorRequisicao
{
    private Configuracao $config;
    private ModelRateLimit $model;
    private int $maxRequisicoes;
    private int $janelaTempo;

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
        $this->model = new ModelRateLimit();
        $this->maxRequisicoes = $this->config->obter('rate_limit.max_requisicoes', 100);
        $this->janelaTempo = $this->config->obter('rate_limit.janela_tempo', 60);
    }

    /**
     * Verifica se a requisição está dentro do limite
     */
    public function verificar(string $identificador): bool
    {
        $chave = $this->gerarChave($identificador);

        // Verifica se está bloqueado
        if ($this->model->estaBloqueado($chave)) {
            return false;
        }

        // Remove requisições antigas
        $this->model->limparRequisicoesAntigas($chave, $this->janelaTempo);

        // Conta requisições na janela de tempo
        $totalRequisicoes = $this->model->contarRequisicoes($chave, $this->janelaTempo);

        // Verifica se excedeu o limite
        if ($totalRequisicoes >= $this->maxRequisicoes) {
            return false;
        }

        return true;
    }

    /**
     * Registra uma requisição
     */
    public function registrar(string $identificador): void
    {
        $agora = time();
        $chave = $this->gerarChave($identificador);

        $this->model->registrarRequisicao($chave, $agora);
    }

    /**
     * Bloqueia temporariamente um identificador
     */
    public function bloquear(string $identificador, int $tempo = 900): void
    {
        $chave = $this->gerarChave($identificador);
        $this->model->bloquear($chave, $tempo);
    }

    /**
     * Desbloqueia um identificador
     */
    public function desbloquear(string $identificador): void
    {
        $chave = $this->gerarChave($identificador);
        $this->model->desbloquear($chave);
    }

    /**
     * Verifica se um identificador está bloqueado
     */
    public function estaBloqueado(string $identificador): bool
    {
        $chave = $this->gerarChave($identificador);
        return $this->model->estaBloqueado($chave);
    }

    /**
     * Obtém o número de requisições restantes
     */
    public function obterRequisicoesRestantes(string $identificador): int
    {
        $chave = $this->gerarChave($identificador);

        // Remove requisições antigas
        $this->model->limparRequisicoesAntigas($chave, $this->janelaTempo);

        // Conta requisições usadas
        $usadas = $this->model->contarRequisicoes($chave, $this->janelaTempo);

        return max(0, $this->maxRequisicoes - $usadas);
    }

    /**
     * Obtém o tempo até o reset
     */
    public function obterTempoReset(string $identificador): int
    {
        $chave = $this->gerarChave($identificador);

        $requisicoes = $this->model->obterRequisicoes($chave, $this->janelaTempo);

        if (empty($requisicoes)) {
            return 0;
        }

        $primeiraRequisicao = min(array_column($requisicoes, 'timestamp'));
        $tempoReset = $primeiraRequisicao + $this->janelaTempo;

        return max(0, $tempoReset - time());
    }

    /**
     * Gera uma chave única para o identificador
     */
    private function gerarChave(string $identificador): string
    {
        return hash('sha256', $identificador);
    }

    /**
     * Limpa todos os registros antigos (manutenção)
     */
    public function limpar(int $dias = 7): int
    {
        return $this->model->limparTodosRegistrosAntigos($dias);
    }

    /**
     * Obtém o identificador do cliente (IP ou usuário)
     */
    public static function obterIdentificador(): string
    {
        // Tenta obter o IP real do cliente
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
              $_SERVER['HTTP_X_REAL_IP'] ??
              $_SERVER['REMOTE_ADDR'] ??
              'unknown';

        // Se houver múltiplos IPs, pega o primeiro
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return $ip;
    }

    /**
     * Verifica se o rate limiting está habilitado
     */
    public function estaHabilitado(): bool
    {
        return $this->config->obter('rate_limit.habilitado', true);
    }
}
