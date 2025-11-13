<?php

namespace App\Core;

use App\Helpers\AuxiliarRede;

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
        ?int $colaboradorId = null
    ): void {
        if (!$this->habilitada) {
            return;
        }

        try {
            $this->db->inserir('auditoria', [
                'colaborador_id' => $colaboradorId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'dados_antigos' => $dadosAntigos ? json_encode($dadosAntigos) : null,
                'dados_novos' => $dadosNovos ? json_encode($dadosNovos) : null,
                'ip' => AuxiliarRede::obterIp(),
                'user_agent' => AuxiliarRede::obterUserAgent(),
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
    public function registrarCriacao(string $tabela, int $registroId, array $dados, ?int $colaboradorId = null): void
    {
        $this->registrar('criar', $tabela, $registroId, null, $dados, $colaboradorId);
    }

    /**
     * Registra uma atualização
     */
    public function registrarAtualizacao(
        string $tabela,
        int $registroId,
        array $dadosAntigos,
        array $dadosNovos,
        ?int $colaboradorId = null
    ): void {
        $this->registrar('atualizar', $tabela, $registroId, $dadosAntigos, $dadosNovos, $colaboradorId);
    }

    /**
     * Registra uma exclusão
     */
    public function registrarExclusao(string $tabela, int $registroId, array $dados, ?int $colaboradorId = null): void
    {
        $this->registrar('deletar', $tabela, $registroId, $dados, null, $colaboradorId);
    }

    /**
     * Registra um login
     */
    public function registrarLogin(int $colaboradorId, bool $sucesso = true): void
    {
        if (!$this->habilitada) {
            return;
        }

        try {
            $this->db->inserir('auditoria_login', [
                'colaborador_id' => $colaboradorId,
                'sucesso' => $sucesso ? 1 : 0,
                'ip' => AuxiliarRede::obterIp(),
                'user_agent' => AuxiliarRede::obterUserAgent(),
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
        }
    }

    /**
     * Registra um logout
     */
    public function registrarLogout(int $colaboradorId): void
    {
        if (!$this->habilitada) {
            return;
        }

        try {
            $this->db->inserir('auditoria_login', [
                'colaborador_id' => $colaboradorId,
                'acao' => 'logout',
                'ip' => AuxiliarRede::obterIp(),
                'user_agent' => AuxiliarRede::obterUserAgent(),
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
            // Obtém o colaborador autenticado (se houver)
            $colaboradorId = null;
            try {
                $autenticacao = new Autenticacao();
                $usuario = $autenticacao->obterUsuarioAutenticado();
                if ($usuario && isset($usuario['id'])) {
                    $colaboradorId = $usuario['id'];
                }
            } catch (\Exception $e) {
                // Ignora erro ao obter usuário autenticado
                // Requisições não autenticadas terão colaborador_id NULL
            }

            $this->db->inserir('auditoria_requisicoes', [
                'colaborador_id' => $colaboradorId,
                'metodo' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'],
                'ip' => AuxiliarRede::obterIp(),
                'user_agent' => AuxiliarRede::obterUserAgent(),
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
        $sql = "SELECT a.*, c.nome as colaborador_nome
                FROM auditoria a
                LEFT JOIN colaboradores c ON a.colaborador_id = c.id
                WHERE 1=1";
        $parametros = [];

        if (isset($filtros['colaborador_id'])) {
            $sql .= " AND a.colaborador_id = ?";
            $parametros[] = $filtros['colaborador_id'];
        }

        if (isset($filtros['acao'])) {
            $sql .= " AND a.acao = ?";
            $parametros[] = $filtros['acao'];
        }

        if (isset($filtros['tabela'])) {
            $sql .= " AND a.tabela = ?";
            $parametros[] = $filtros['tabela'];
        }

        if (isset($filtros['registro_id'])) {
            $sql .= " AND a.registro_id = ?";
            $parametros[] = $filtros['registro_id'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND a.criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND a.criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY a.criado_em DESC";

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
    public function buscarHistoricoLogin(int $colaboradorId, int $limite = 10): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM auditoria_login WHERE colaborador_id = ? ORDER BY criado_em DESC LIMIT ?",
            [$colaboradorId, $limite]
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
