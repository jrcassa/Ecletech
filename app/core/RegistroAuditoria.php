<?php

namespace App\Core;

/**
 * Classe para registro de auditoria
 */
class RegistroAuditoria
{
    private BancoDados $db;
    private Configuracao $config;
    private bool $habilitada;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->config = Configuracao::obterInstancia();
        $this->habilitada = $this->config->obter('auditoria.habilitada', true);
    }

    /**
     * Registra uma ação de auditoria
     */
    public function registrar(
        string $acao,
        string $tabela,
        ?int $registroId = null,
        ?array $dadosAntigos = null,
        ?array $dadosNovos = null,
        ?int $usuarioId = null
    ): void {
        if (!$this->habilitada) {
            return;
        }

        try {
            $this->db->inserir('auditoria', [
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'dados_antigos' => $dadosAntigos ? json_encode($dadosAntigos) : null,
                'dados_novos' => $dadosNovos ? json_encode($dadosNovos) : null,
                'ip' => $this->obterIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Não lança exceção para não interromper o fluxo principal
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }

    /**
     * Registra uma criação
     */
    public function registrarCriacao(string $tabela, int $registroId, array $dados, ?int $usuarioId = null): void
    {
        $this->registrar('criar', $tabela, $registroId, null, $dados, $usuarioId);
    }

    /**
     * Registra uma atualização
     */
    public function registrarAtualizacao(
        string $tabela,
        int $registroId,
        array $dadosAntigos,
        array $dadosNovos,
        ?int $usuarioId = null
    ): void {
        $this->registrar('atualizar', $tabela, $registroId, $dadosAntigos, $dadosNovos, $usuarioId);
    }

    /**
     * Registra uma exclusão
     */
    public function registrarExclusao(string $tabela, int $registroId, array $dados, ?int $usuarioId = null): void
    {
        $this->registrar('deletar', $tabela, $registroId, $dados, null, $usuarioId);
    }

    /**
     * Registra um login
     */
    public function registrarLogin(int $usuarioId, bool $sucesso = true): void
    {
        if (!$this->habilitada) {
            return;
        }

        try {
            $this->db->inserir('auditoria_login', [
                'usuario_id' => $usuarioId,
                'sucesso' => $sucesso ? 1 : 0,
                'ip' => $this->obterIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
        }
    }

    /**
     * Registra um logout
     */
    public function registrarLogout(int $usuarioId): void
    {
        if (!$this->habilitada) {
            return;
        }

        try {
            $this->db->inserir('auditoria_login', [
                'usuario_id' => $usuarioId,
                'acao' => 'logout',
                'ip' => $this->obterIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao registrar logout: " . $e->getMessage());
        }
    }

    /**
     * Registra uma requisição
     */
    public function registrarRequisicao(): void
    {
        if (!$this->habilitada || !$this->config->obter('auditoria.registrar_requisicoes', true)) {
            return;
        }

        try {
            $this->db->inserir('auditoria_requisicoes', [
                'metodo' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'],
                'ip' => $this->obterIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'payload' => file_get_contents('php://input'),
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao registrar requisição: " . $e->getMessage());
        }
    }

    /**
     * Busca o histórico de auditoria
     */
    public function buscarHistorico(array $filtros = []): array
    {
        $sql = "SELECT * FROM auditoria WHERE 1=1";
        $parametros = [];

        if (isset($filtros['usuario_id'])) {
            $sql .= " AND usuario_id = ?";
            $parametros[] = $filtros['usuario_id'];
        }

        if (isset($filtros['acao'])) {
            $sql .= " AND acao = ?";
            $parametros[] = $filtros['acao'];
        }

        if (isset($filtros['tabela'])) {
            $sql .= " AND tabela = ?";
            $parametros[] = $filtros['tabela'];
        }

        if (isset($filtros['registro_id'])) {
            $sql .= " AND registro_id = ?";
            $parametros[] = $filtros['registro_id'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY criado_em DESC";

        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $parametros[] = (int) $filtros['limite'];

            if (isset($filtros['offset'])) {
                $sql .= " OFFSET ?";
                $parametros[] = (int) $filtros['offset'];
            }
        }

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Busca histórico de login
     */
    public function buscarHistoricoLogin(int $usuarioId, int $limite = 10): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM auditoria_login WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT ?",
            [$usuarioId, $limite]
        );
    }

    /**
     * Limpa registros antigos de auditoria
     */
    public function limparAntigos(int $dias = 90): int
    {
        $dataLimite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));

        return $this->db->deletar('auditoria', 'criado_em < ?', [$dataLimite]);
    }

    /**
     * Obtém o IP do cliente
     */
    private function obterIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
              $_SERVER['HTTP_X_REAL_IP'] ??
              $_SERVER['REMOTE_ADDR'] ??
              'unknown';

        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return $ip;
    }

    /**
     * Habilita a auditoria
     */
    public function habilitar(): void
    {
        $this->habilitada = true;
    }

    /**
     * Desabilita a auditoria
     */
    public function desabilitar(): void
    {
        $this->habilitada = false;
    }

    /**
     * Verifica se a auditoria está habilitada
     */
    public function estaHabilitada(): bool
    {
        return $this->habilitada;
    }
}
