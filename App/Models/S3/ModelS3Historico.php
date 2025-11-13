<?php

namespace App\Models\S3;

use App\Core\BancoDados;

/**
 * Model para gerenciar histórico de operações S3
 * Registra auditoria de todas as ações realizadas
 */
class ModelS3Historico
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Registra uma operação no histórico
     */
    public function registrar(array $dados): ?int
    {
        $sql = "INSERT INTO s3_historico (
                    arquivo_id, operacao, status,
                    bucket, caminho_s3, tamanho_bytes,
                    detalhes, erro, tempo_execucao_ms,
                    colaborador_id, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $parametros = [
            $dados['arquivo_id'] ?? null,
            $dados['operacao'],
            $dados['status'],
            $dados['bucket'] ?? null,
            $dados['caminho_s3'] ?? null,
            $dados['tamanho_bytes'] ?? null,
            isset($dados['detalhes']) ? json_encode($dados['detalhes']) : null,
            $dados['erro'] ?? null,
            $dados['tempo_execucao_ms'] ?? null,
            $dados['colaborador_id'] ?? null,
            $dados['ip_address'] ?? null,
            $dados['user_agent'] ?? null
        ];

        $stmt = $this->db->executar($sql, $parametros);

        if ($stmt->rowCount() > 0) {
            return (int) $this->db->obterConexao()->lastInsertId();
        }

        return null;
    }

    /**
     * Busca histórico por arquivo
     */
    public function buscarPorArquivo(int $arquivoId, int $limite = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_historico
             WHERE arquivo_id = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$arquivoId, $limite]
        );
    }

    /**
     * Busca histórico por colaborador
     */
    public function buscarPorColaborador(int $colaboradorId, int $limite = 50): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_historico
             WHERE colaborador_id = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            [$colaboradorId, $limite]
        );
    }

    /**
     * Busca histórico por operação
     */
    public function buscarPorOperacao(
        string $operacao,
        int $limite = 50,
        int $offset = 0
    ): array {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_historico
             WHERE operacao = ?
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            [$operacao, $limite, $offset]
        );
    }

    /**
     * Lista histórico com filtros
     */
    public function listar(array $filtros = [], int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM s3_historico WHERE 1=1";
        $parametros = [];

        if (!empty($filtros['arquivo_id'])) {
            $sql .= " AND arquivo_id = ?";
            $parametros[] = $filtros['arquivo_id'];
        }

        if (!empty($filtros['operacao'])) {
            $sql .= " AND operacao = ?";
            $parametros[] = $filtros['operacao'];
        }

        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $parametros[] = $filtros['status'];
        }

        if (!empty($filtros['colaborador_id'])) {
            $sql .= " AND colaborador_id = ?";
            $parametros[] = $filtros['colaborador_id'];
        }

        if (!empty($filtros['bucket'])) {
            $sql .= " AND bucket = ?";
            $parametros[] = $filtros['bucket'];
        }

        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY criado_em DESC LIMIT ? OFFSET ?";
        $parametros[] = $limite;
        $parametros[] = $offset;

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Conta total de registros com filtros
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM s3_historico WHERE 1=1";
        $parametros = [];

        if (!empty($filtros['arquivo_id'])) {
            $sql .= " AND arquivo_id = ?";
            $parametros[] = $filtros['arquivo_id'];
        }

        if (!empty($filtros['operacao'])) {
            $sql .= " AND operacao = ?";
            $parametros[] = $filtros['operacao'];
        }

        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $parametros[] = $filtros['status'];
        }

        if (!empty($filtros['colaborador_id'])) {
            $sql .= " AND colaborador_id = ?";
            $parametros[] = $filtros['colaborador_id'];
        }

        if (!empty($filtros['bucket'])) {
            $sql .= " AND bucket = ?";
            $parametros[] = $filtros['bucket'];
        }

        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);

        return (int) ($resultado['total'] ?? 0);
    }

    /**
     * Obtém estatísticas de operações
     */
    public function obterEstatisticas(array $filtros = []): array
    {
        $sqlBase = "FROM s3_historico WHERE 1=1";
        $parametros = [];

        if (!empty($filtros['data_inicio'])) {
            $sqlBase .= " AND criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $sqlBase .= " AND criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        // Total por operação
        $porOperacao = $this->db->buscarTodos(
            "SELECT operacao, COUNT(*) as total, status
             {$sqlBase}
             GROUP BY operacao, status
             ORDER BY total DESC",
            $parametros
        );

        // Total por status
        $porStatus = $this->db->buscarTodos(
            "SELECT status, COUNT(*) as total
             {$sqlBase}
             GROUP BY status",
            $parametros
        );

        // Tempo médio de execução
        $tempoMedio = $this->db->buscarUm(
            "SELECT
                AVG(tempo_execucao_ms) as tempo_medio,
                MAX(tempo_execucao_ms) as tempo_maximo,
                MIN(tempo_execucao_ms) as tempo_minimo
             {$sqlBase}
             AND tempo_execucao_ms IS NOT NULL",
            $parametros
        );

        // Usuários mais ativos
        $usuariosMaisAtivos = $this->db->buscarTodos(
            "SELECT
                colaborador_id,
                COUNT(*) as total_operacoes
             {$sqlBase}
             AND colaborador_id IS NOT NULL
             GROUP BY colaborador_id
             ORDER BY total_operacoes DESC
             LIMIT 10",
            $parametros
        );

        return [
            'por_operacao' => $porOperacao,
            'por_status' => $porStatus,
            'tempo_execucao' => [
                'medio_ms' => (float) ($tempoMedio['tempo_medio'] ?? 0),
                'maximo_ms' => (int) ($tempoMedio['tempo_maximo'] ?? 0),
                'minimo_ms' => (int) ($tempoMedio['tempo_minimo'] ?? 0)
            ],
            'usuarios_mais_ativos' => $usuariosMaisAtivos
        ];
    }

    /**
     * Obtém uploads recentes
     */
    public function obterUploadsRecentes(int $limite = 10): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_historico
             WHERE operacao = 'upload' AND status = 'sucesso'
             ORDER BY criado_em DESC
             LIMIT ?",
            [$limite]
        );
    }

    /**
     * Obtém falhas recentes
     */
    public function obterFalhasRecentes(int $limite = 10): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_historico
             WHERE status = 'falha'
             ORDER BY criado_em DESC
             LIMIT ?",
            [$limite]
        );
    }

    /**
     * Limpa histórico antigo
     */
    public function limparHistoricoAntigo(int $diasManter = 90): int
    {
        $stmt = $this->db->executar(
            "DELETE FROM s3_historico
             WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$diasManter]
        );

        return $stmt->rowCount();
    }

    /**
     * Busca logs de erro
     */
    public function buscarErros(int $limite = 50, int $offset = 0): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM s3_historico
             WHERE status = 'falha' AND erro IS NOT NULL
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            [$limite, $offset]
        );
    }

    /**
     * Obtém atividade por período
     */
    public function obterAtividadePorPeriodo(
        string $periodo = 'day',
        int $limite = 30
    ): array {
        $formatoData = match($periodo) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d'
        };

        return $this->db->buscarTodos(
            "SELECT
                DATE_FORMAT(criado_em, ?) as periodo,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
                SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas
             FROM s3_historico
             GROUP BY periodo
             ORDER BY periodo DESC
             LIMIT ?",
            [$formatoData, $limite]
        );
    }
}
