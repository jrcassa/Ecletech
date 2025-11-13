<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar métricas de abastecimentos
 */
class ModelFrotaAbastecimentoMetrica
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca métrica por ID do abastecimento
     */
    public function buscarPorAbastecimentoId(int $abastecimento_id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_metricas WHERE abastecimento_id = ?",
            [$abastecimento_id]
        );
    }

    /**
     * Alias para buscarPorAbastecimentoId
     */
    public function buscarPorAbastecimento(int $abastecimento_id): ?array
    {
        return $this->buscarPorAbastecimentoId($abastecimento_id);
    }

    /**
     * Cria registro de métrica
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_metricas (
                abastecimento_id, km_percorrido, consumo_km_por_litro,
                custo_por_km, custo_por_litro, dias_desde_ultimo,
                media_km_por_dia, calculado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $parametros = [
            $dados['abastecimento_id'],
            $dados['km_percorrido'] ?? null,
            $dados['consumo_km_por_litro'] ?? null,
            $dados['custo_por_km'] ?? null,
            $dados['custo_por_litro'] ?? null,
            $dados['dias_desde_ultimo'] ?? null,
            $dados['media_km_por_dia'] ?? null
        ];

        $this->db->executar($sql, $parametros);
        return (int) $this->db->obterConexao()->lastInsertId();
    }

    /**
     * Atualiza métrica existente
     */
    public function atualizar(int $abastecimento_id, array $dados): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_metricas SET
                km_percorrido = ?,
                consumo_km_por_litro = ?,
                custo_por_km = ?,
                custo_por_litro = ?,
                dias_desde_ultimo = ?,
                media_km_por_dia = ?,
                calculado_em = NOW()
            WHERE abastecimento_id = ?
        ";

        $parametros = [
            $dados['km_percorrido'] ?? null,
            $dados['consumo_km_por_litro'] ?? null,
            $dados['custo_por_km'] ?? null,
            $dados['custo_por_litro'] ?? null,
            $dados['dias_desde_ultimo'] ?? null,
            $dados['media_km_por_dia'] ?? null,
            $abastecimento_id
        ];

        $this->db->executar($sql, $parametros);
        return true;
    }

    /**
     * Calcula média de consumo por frota
     */
    public function calcularMediaConsumoFrota(int $frota_id, ?array $periodo = null): ?float
    {
        $sql = "
            SELECT AVG(m.consumo_km_por_litro) as media
            FROM frotas_abastecimentos_metricas m
            INNER JOIN frotas_abastecimentos fa ON fa.id = m.abastecimento_id
            WHERE fa.frota_id = ?
            AND fa.status = 'abastecido'
            AND fa.deletado_em IS NULL
            AND m.consumo_km_por_litro IS NOT NULL
        ";

        $parametros = [$frota_id];

        if ($periodo) {
            $sql .= " AND fa.data_abastecimento BETWEEN ? AND ?";
            $parametros[] = $periodo['inicio'];
            $parametros[] = $periodo['fim'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado ? (float) $resultado['media'] : null;
    }

    /**
     * Calcula média de custo por km de uma frota
     */
    public function calcularMediaCustoPorKm(int $frota_id, ?array $periodo = null): ?float
    {
        $sql = "
            SELECT AVG(m.custo_por_km) as media
            FROM frotas_abastecimentos_metricas m
            INNER JOIN frotas_abastecimentos fa ON fa.id = m.abastecimento_id
            WHERE fa.frota_id = ?
            AND fa.status = 'abastecido'
            AND fa.deletado_em IS NULL
            AND m.custo_por_km IS NOT NULL
        ";

        $parametros = [$frota_id];

        if ($periodo) {
            $sql .= " AND fa.data_abastecimento BETWEEN ? AND ?";
            $parametros[] = $periodo['inicio'];
            $parametros[] = $periodo['fim'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado ? (float) $resultado['media'] : null;
    }

    /**
     * Obtém histórico de consumo de uma frota
     */
    public function obterHistoricoConsumo(int $frota_id, int $limite = 12): array
    {
        $sql = "
            SELECT
                m.*,
                fa.data_abastecimento,
                fa.km,
                fa.litros,
                fa.valor
            FROM frotas_abastecimentos_metricas m
            INNER JOIN frotas_abastecimentos fa ON fa.id = m.abastecimento_id
            WHERE fa.frota_id = ?
            AND fa.status = 'abastecido'
            AND fa.deletado_em IS NULL
            ORDER BY fa.data_abastecimento DESC
            LIMIT ?
        ";

        return $this->db->buscarTodos($sql, [$frota_id, $limite]);
    }
}
