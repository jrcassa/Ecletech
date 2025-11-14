<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar logs de relatórios
 */
class ModelFrotaAbastecimentoRelatorioLog
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca log por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_relatorios_logs WHERE id = ?",
            [$id]
        );
    }

    /**
     * Lista logs com filtros
     */
    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT
                l.*,
                c.nome as destinatario_nome
            FROM frotas_abastecimentos_relatorios_logs l
            INNER JOIN colaboradores c ON c.id = l.destinatario_id
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['tipo_relatorio'])) {
            $sql .= " AND l.tipo_relatorio = ?";
            $parametros[] = $filtros['tipo_relatorio'];
        }

        if (isset($filtros['status_envio'])) {
            $sql .= " AND l.status_envio = ?";
            $parametros[] = $filtros['status_envio'];
        }

        if (isset($filtros['destinatario_id'])) {
            $sql .= " AND l.destinatario_id = ?";
            $parametros[] = $filtros['destinatario_id'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND l.criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND l.criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        $sql .= " ORDER BY l.criado_em DESC";

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
     * Conta logs com filtros
     */
    public function contar(array $filtros = []): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM frotas_abastecimentos_relatorios_logs l
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['tipo_relatorio'])) {
            $sql .= " AND l.tipo_relatorio = ?";
            $parametros[] = $filtros['tipo_relatorio'];
        }

        if (isset($filtros['status_envio'])) {
            $sql .= " AND l.status_envio = ?";
            $parametros[] = $filtros['status_envio'];
        }

        if (isset($filtros['destinatario_id'])) {
            $sql .= " AND l.destinatario_id = ?";
            $parametros[] = $filtros['destinatario_id'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND l.criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND l.criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Busca logs com erro para retentar
     */
    public function buscarParaRetentar(): array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_logs
            WHERE status_envio = 'erro'
            AND tentativas < 3
            ORDER BY criado_em ASC
            LIMIT 20
        ";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Cria log
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_relatorios_logs (
                tipo_relatorio, periodo_inicio, periodo_fim, destinatario_id,
                telefone, formato, mensagem, dados_relatorio, status_envio,
                tamanho_mensagem, tempo_processamento, criado_em, processado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, NOW(), ?)
        ";

        $mensagem = $dados['mensagem'];
        $parametros = [
            $dados['tipo_relatorio'],
            $dados['periodo_inicio'],
            $dados['periodo_fim'],
            $dados['destinatario_id'],
            $dados['telefone'],
            $dados['formato'],
            $mensagem,
            json_encode($dados['dados_relatorio']),
            strlen($mensagem),
            $dados['tempo_processamento'] ?? null,
            $dados['processado_em'] ?? date('Y-m-d H:i:s')
        ];

        $this->db->executar($sql, $parametros);
        return (int) $this->db->obterConexao()->lastInsertId();
    }

    /**
     * Marca log como enviado
     */
    public function marcarEnviado(int $id): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_relatorios_logs SET
                status_envio = 'enviado',
                enviado_em = NOW()
            WHERE id = ?
        ";

        $this->db->executar($sql, [$id]);
        return true;
    }

    /**
     * Marca log com erro
     */
    public function marcarErro(int $id, string $erro): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_relatorios_logs SET
                status_envio = 'erro',
                erro_mensagem = ?,
                tentativas = tentativas + 1
            WHERE id = ?
        ";

        $this->db->executar($sql, [$erro, $id]);
        return true;
    }

    /**
     * Obtém estatísticas
     */
    public function obterEstatisticas(array $filtros = []): array
    {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status_envio = 'enviado' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status_envio = 'erro' THEN 1 ELSE 0 END) as erros,
                SUM(CASE WHEN status_envio = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                ROUND(AVG(tempo_processamento), 2) as tempo_medio_processamento,
                ROUND(AVG(tamanho_mensagem), 2) as tamanho_medio_mensagem
            FROM frotas_abastecimentos_relatorios_logs
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['tipo_relatorio'])) {
            $sql .= " AND tipo_relatorio = ?";
            $parametros[] = $filtros['tipo_relatorio'];
        }

        if (isset($filtros['data_inicio'])) {
            $sql .= " AND criado_em >= ?";
            $parametros[] = $filtros['data_inicio'];
        }

        if (isset($filtros['data_fim'])) {
            $sql .= " AND criado_em <= ?";
            $parametros[] = $filtros['data_fim'];
        }

        return $this->db->buscarUm($sql, $parametros) ?: [];
    }

    /**
     * Obtém último envio de um colaborador
     */
    public function obterUltimoEnvio(int $colaborador_id, string $tipo_relatorio): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_logs
            WHERE destinatario_id = ?
            AND tipo_relatorio = ?
            AND status_envio = 'enviado'
            ORDER BY enviado_em DESC
            LIMIT 1
        ";

        return $this->db->buscarUm($sql, [$colaborador_id, $tipo_relatorio]);
    }

    /**
     * Deleta log
     */
    public function deletar(int $id): bool
    {
        $sql = "DELETE FROM frotas_abastecimentos_relatorios_logs WHERE id = ?";
        $this->db->executar($sql, [$id]);
        return true;
    }
}
