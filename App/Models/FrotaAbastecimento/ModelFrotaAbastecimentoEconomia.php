<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar economia de abastecimentos
 */
class ModelFrotaAbastecimentoEconomia
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca economia por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_economia WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca economia por frota e período
     */
    public function buscarPorFrotaPeriodo(int $frota_id, string $periodo_inicio, string $periodo_fim): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_economia
            WHERE frota_id = ?
            AND periodo_inicio = ?
            AND periodo_fim = ?
        ";

        return $this->db->buscarUm($sql, [$frota_id, $periodo_inicio, $periodo_fim]);
    }

    /**
     * Busca economia geral de um período
     */
    public function buscarPorPeriodo(string $periodo_inicio, string $periodo_fim): array
    {
        $sql = "
            SELECT
                e.*,
                f.placa as frota_placa,
                f.modelo as frota_modelo,
                f.marca as frota_marca
            FROM frotas_abastecimentos_economia e
            INNER JOIN frotas f ON f.id = e.frota_id
            WHERE e.periodo_inicio = ?
            AND e.periodo_fim = ?
            ORDER BY e.economia_vs_periodo_anterior DESC
        ";

        return $this->db->buscarTodos($sql, [$periodo_inicio, $periodo_fim]);
    }

    /**
     * Cria registro de economia
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_economia (
                frota_id, periodo_inicio, periodo_fim, total_abastecimentos,
                total_litros, total_valor, total_km_percorrido, consumo_medio,
                custo_medio_por_km, custo_medio_por_litro, economia_vs_periodo_anterior,
                calculado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $parametros = [
            $dados['frota_id'],
            $dados['periodo_inicio'],
            $dados['periodo_fim'],
            $dados['total_abastecimentos'] ?? 0,
            $dados['total_litros'] ?? 0,
            $dados['total_valor'] ?? 0,
            $dados['total_km_percorrido'] ?? 0,
            $dados['consumo_medio'] ?? 0,
            $dados['custo_medio_por_km'] ?? 0,
            $dados['custo_medio_por_litro'] ?? 0,
            $dados['economia_vs_periodo_anterior'] ?? null
        ];

        $this->db->executar($sql, $parametros);
        return (int) $this->db->obterConexao()->lastInsertId();
    }

    /**
     * Atualiza registro de economia
     */
    public function atualizar(int $id, array $dados): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_economia SET
                total_abastecimentos = ?,
                total_litros = ?,
                total_valor = ?,
                total_km_percorrido = ?,
                consumo_medio = ?,
                custo_medio_por_km = ?,
                custo_medio_por_litro = ?,
                economia_vs_periodo_anterior = ?,
                calculado_em = NOW()
            WHERE id = ?
        ";

        $parametros = [
            $dados['total_abastecimentos'] ?? 0,
            $dados['total_litros'] ?? 0,
            $dados['total_valor'] ?? 0,
            $dados['total_km_percorrido'] ?? 0,
            $dados['consumo_medio'] ?? 0,
            $dados['custo_medio_por_km'] ?? 0,
            $dados['custo_medio_por_litro'] ?? 0,
            $dados['economia_vs_periodo_anterior'] ?? null,
            $id
        ];

        $this->db->executar($sql, $parametros);
        return true;
    }

    /**
     * Obtém ranking de economia
     */
    public function obterRankingEconomia(string $periodo_inicio, string $periodo_fim, int $limite = 10): array
    {
        $sql = "
            SELECT
                e.*,
                f.placa as frota_placa,
                f.modelo as frota_modelo,
                f.marca as frota_marca
            FROM frotas_abastecimentos_economia e
            INNER JOIN frotas f ON f.id = e.frota_id
            WHERE e.periodo_inicio = ?
            AND e.periodo_fim = ?
            AND e.economia_vs_periodo_anterior IS NOT NULL
            ORDER BY e.economia_vs_periodo_anterior DESC
            LIMIT ?
        ";

        return $this->db->buscarTodos($sql, [$periodo_inicio, $periodo_fim, $limite]);
    }

    /**
     * Verifica se existe registro
     */
    public function existe(int $frota_id, string $periodo_inicio, string $periodo_fim): bool
    {
        $registro = $this->buscarPorFrotaPeriodo($frota_id, $periodo_inicio, $periodo_fim);
        return $registro !== null;
    }
}
