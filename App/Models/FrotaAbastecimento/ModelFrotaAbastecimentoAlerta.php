<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar alertas de abastecimentos
 */
class ModelFrotaAbastecimentoAlerta
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca alerta por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_alertas WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca alertas de um abastecimento
     */
    public function buscarPorAbastecimento(int $abastecimento_id): array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_alertas
            WHERE abastecimento_id = ?
            ORDER BY severidade DESC, criado_em DESC
        ";

        return $this->db->buscarTodos($sql, [$abastecimento_id]);
    }

    /**
     * Busca alertas de uma frota
     */
    public function buscarPorFrota(int $frota_id, int $limite = 20): array
    {
        $sql = "
            SELECT
                a.*,
                fa.data_abastecimento,
                fa.km,
                c.nome as motorista_nome
            FROM frotas_abastecimentos_alertas a
            INNER JOIN frotas_abastecimentos fa ON fa.id = a.abastecimento_id
            INNER JOIN colaboradores c ON c.id = fa.colaborador_id
            WHERE fa.frota_id = ?
            ORDER BY a.severidade DESC, a.criado_em DESC
            LIMIT ?
        ";

        return $this->db->buscarTodos($sql, [$frota_id, $limite]);
    }

    /**
     * Lista alertas com filtros
     */
    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT
                a.*,
                fa.frota_id,
                f.placa as frota_placa,
                fa.colaborador_id,
                c.nome as motorista_nome
            FROM frotas_abastecimentos_alertas a
            INNER JOIN frotas_abastecimentos fa ON fa.id = a.abastecimento_id
            INNER JOIN frotas f ON f.id = fa.frota_id
            INNER JOIN colaboradores c ON c.id = fa.colaborador_id
            WHERE 1=1
        ";
        $parametros = [];

        // Filtro por tipo
        if (isset($filtros['tipo_alerta'])) {
            $sql .= " AND a.tipo_alerta = ?";
            $parametros[] = $filtros['tipo_alerta'];
        }

        // Filtro por severidade
        if (isset($filtros['severidade'])) {
            $sql .= " AND a.severidade = ?";
            $parametros[] = $filtros['severidade'];
        }

        // Filtro por visualizado
        if (isset($filtros['visualizado'])) {
            $sql .= " AND a.visualizado = ?";
            $parametros[] = $filtros['visualizado'];
        }

        // Filtro por frota
        if (isset($filtros['frota_id'])) {
            $sql .= " AND fa.frota_id = ?";
            $parametros[] = $filtros['frota_id'];
        }

        $sql .= " ORDER BY a.severidade DESC, a.criado_em DESC";

        // Paginação
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
     * Conta alertas
     */
    public function contar(array $filtros = []): int
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM frotas_abastecimentos_alertas a
            INNER JOIN frotas_abastecimentos fa ON fa.id = a.abastecimento_id
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['tipo_alerta'])) {
            $sql .= " AND a.tipo_alerta = ?";
            $parametros[] = $filtros['tipo_alerta'];
        }

        if (isset($filtros['severidade'])) {
            $sql .= " AND a.severidade = ?";
            $parametros[] = $filtros['severidade'];
        }

        if (isset($filtros['visualizado'])) {
            $sql .= " AND a.visualizado = ?";
            $parametros[] = $filtros['visualizado'];
        }

        if (isset($filtros['frota_id'])) {
            $sql .= " AND fa.frota_id = ?";
            $parametros[] = $filtros['frota_id'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Busca alertas críticos não visualizados
     */
    public function buscarCriticosNaoVisualizados(): array
    {
        $sql = "
            SELECT
                a.*,
                fa.frota_id,
                f.placa as frota_placa,
                fa.colaborador_id,
                c.nome as motorista_nome
            FROM frotas_abastecimentos_alertas a
            INNER JOIN frotas_abastecimentos fa ON fa.id = a.abastecimento_id
            INNER JOIN frotas f ON f.id = fa.frota_id
            INNER JOIN colaboradores c ON c.id = fa.colaborador_id
            WHERE a.severidade IN ('alta', 'critica')
            AND a.visualizado = FALSE
            ORDER BY a.severidade DESC, a.criado_em DESC
        ";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Cria novo alerta
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_alertas (
                abastecimento_id, tipo_alerta, severidade, titulo,
                descricao, valor_esperado, valor_real, criado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $parametros = [
            $dados['abastecimento_id'],
            $dados['tipo_alerta'],
            $dados['severidade'],
            $dados['titulo'],
            $dados['descricao'],
            $dados['valor_esperado'] ?? null,
            $dados['valor_real'] ?? null
        ];

        return $this->db->executar($sql, $parametros);
    }

    /**
     * Marca alerta como visualizado
     */
    public function marcarVisualizado(int $id, int $colaborador_id): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_alertas SET
                visualizado = TRUE,
                visualizado_por = ?,
                visualizado_em = NOW()
            WHERE id = ?
        ";

        $this->db->executar($sql, [$colaborador_id, $id]);
        return true;
    }

    /**
     * Obtém contadores por severidade
     */
    public function contarPorSeveridade(): array
    {
        $sql = "
            SELECT
                severidade,
                COUNT(*) as total,
                SUM(CASE WHEN visualizado = FALSE THEN 1 ELSE 0 END) as nao_visualizado
            FROM frotas_abastecimentos_alertas
            GROUP BY severidade
        ";

        return $this->db->buscarTodos($sql);
    }

    /**
     * Obtém estatísticas de alertas por tipo
     */
    public function obterEstatisticasPorTipo(array $filtros = []): array
    {
        $sql = "
            SELECT
                a.tipo_alerta,
                COUNT(*) as total,
                SUM(CASE WHEN a.visualizado = FALSE THEN 1 ELSE 0 END) as nao_visualizado,
                SUM(CASE WHEN a.severidade = 'critica' THEN 1 ELSE 0 END) as criticos,
                SUM(CASE WHEN a.severidade = 'alta' THEN 1 ELSE 0 END) as altos
            FROM frotas_abastecimentos_alertas a
            INNER JOIN frotas_abastecimentos fa ON fa.id = a.abastecimento_id
            WHERE 1=1
        ";
        $parametros = [];

        if (isset($filtros['data_inicio']) && isset($filtros['data_fim'])) {
            $sql .= " AND fa.data_abastecimento BETWEEN ? AND ?";
            $parametros[] = $filtros['data_inicio'];
            $parametros[] = $filtros['data_fim'];
        }

        $sql .= " GROUP BY a.tipo_alerta ORDER BY total DESC";

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Obtém dashboard de alertas
     */
    public function obterDashboard(): array
    {
        $sql = "
            SELECT
                COUNT(*) as total_alertas,
                SUM(CASE WHEN visualizado = FALSE THEN 1 ELSE 0 END) as nao_visualizado,
                SUM(CASE WHEN severidade = 'critica' THEN 1 ELSE 0 END) as criticos,
                SUM(CASE WHEN severidade = 'alta' THEN 1 ELSE 0 END) as altos,
                SUM(CASE WHEN severidade = 'media' THEN 1 ELSE 0 END) as medios,
                SUM(CASE WHEN severidade = 'baixa' THEN 1 ELSE 0 END) as baixos
            FROM frotas_abastecimentos_alertas
        ";

        return $this->db->buscarUm($sql) ?: [];
    }
}
