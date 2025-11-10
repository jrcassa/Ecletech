<?php

namespace App\Core;

/**
 * Classe para limitar requisições (Rate Limiting)
 */
class LimitadorRequisicao
{
    private Configuracao $config;
    private int $maxRequisicoes;
    private int $janelaTempo;
    private array $armazenamento = [];

    public function __construct()
    {
        $this->config = Configuracao::obterInstancia();
        $this->maxRequisicoes = $this->config->obter('rate_limit.max_requisicoes', 100);
        $this->janelaTempo = $this->config->obter('rate_limit.janela_tempo', 60);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Carrega o armazenamento da sessão
        if (isset($_SESSION['rate_limit'])) {
            $this->armazenamento = $_SESSION['rate_limit'];
        }
    }

    /**
     * Verifica se a requisição está dentro do limite
     */
    public function verificar(string $identificador): bool
    {
        $agora = time();
        $chave = $this->gerarChave($identificador);

        // Inicializa o registro se não existir
        if (!isset($this->armazenamento[$chave])) {
            $this->armazenamento[$chave] = [
                'requisicoes' => [],
                'bloqueado_ate' => 0
            ];
        }

        // Verifica se está bloqueado
        if ($this->armazenamento[$chave]['bloqueado_ate'] > $agora) {
            return false;
        }

        // Remove requisições antigas
        $this->limparRequisicoesAntigas($chave, $agora);

        // Verifica se excedeu o limite
        if (count($this->armazenamento[$chave]['requisicoes']) >= $this->maxRequisicoes) {
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

        if (!isset($this->armazenamento[$chave])) {
            $this->armazenamento[$chave] = [
                'requisicoes' => [],
                'bloqueado_ate' => 0
            ];
        }

        $this->armazenamento[$chave]['requisicoes'][] = $agora;

        // Salva na sessão
        $_SESSION['rate_limit'] = $this->armazenamento;
    }

    /**
     * Bloqueia temporariamente um identificador
     */
    public function bloquear(string $identificador, int $tempo = 900): void
    {
        $chave = $this->gerarChave($identificador);

        if (!isset($this->armazenamento[$chave])) {
            $this->armazenamento[$chave] = [
                'requisicoes' => [],
                'bloqueado_ate' => 0
            ];
        }

        $this->armazenamento[$chave]['bloqueado_ate'] = time() + $tempo;
        $_SESSION['rate_limit'] = $this->armazenamento;
    }

    /**
     * Desbloqueia um identificador
     */
    public function desbloquear(string $identificador): void
    {
        $chave = $this->gerarChave($identificador);

        if (isset($this->armazenamento[$chave])) {
            $this->armazenamento[$chave]['bloqueado_ate'] = 0;
            $_SESSION['rate_limit'] = $this->armazenamento;
        }
    }

    /**
     * Verifica se um identificador está bloqueado
     */
    public function estaBloqueado(string $identificador): bool
    {
        $chave = $this->gerarChave($identificador);

        if (!isset($this->armazenamento[$chave])) {
            return false;
        }

        return $this->armazenamento[$chave]['bloqueado_ate'] > time();
    }

    /**
     * Obtém o número de requisições restantes
     */
    public function obterRequisicoesRestantes(string $identificador): int
    {
        $chave = $this->gerarChave($identificador);

        if (!isset($this->armazenamento[$chave])) {
            return $this->maxRequisicoes;
        }

        $this->limparRequisicoesAntigas($chave, time());

        $usadas = count($this->armazenamento[$chave]['requisicoes']);
        return max(0, $this->maxRequisicoes - $usadas);
    }

    /**
     * Obtém o tempo até o reset
     */
    public function obterTempoReset(string $identificador): int
    {
        $chave = $this->gerarChave($identificador);

        if (!isset($this->armazenamento[$chave]) || empty($this->armazenamento[$chave]['requisicoes'])) {
            return 0;
        }

        $primeiraRequisicao = min($this->armazenamento[$chave]['requisicoes']);
        $tempoReset = $primeiraRequisicao + $this->janelaTempo;

        return max(0, $tempoReset - time());
    }

    /**
     * Limpa requisições antigas
     */
    private function limparRequisicoesAntigas(string $chave, int $agora): void
    {
        if (!isset($this->armazenamento[$chave]['requisicoes'])) {
            return;
        }

        $limiteInferior = $agora - $this->janelaTempo;

        $this->armazenamento[$chave]['requisicoes'] = array_filter(
            $this->armazenamento[$chave]['requisicoes'],
            fn($tempo) => $tempo > $limiteInferior
        );

        $_SESSION['rate_limit'] = $this->armazenamento;
    }

    /**
     * Gera uma chave única para o identificador
     */
    private function gerarChave(string $identificador): string
    {
        return hash('sha256', $identificador);
    }

    /**
     * Limpa todos os registros
     */
    public function limpar(): void
    {
        $this->armazenamento = [];
        $_SESSION['rate_limit'] = [];
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
