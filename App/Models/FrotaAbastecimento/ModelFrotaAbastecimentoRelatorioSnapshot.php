<?php

namespace App\Models\FrotaAbastecimento;

use App\Core\BancoDados;

/**
 * Model para gerenciar snapshots de relatórios
 */
class ModelFrotaAbastecimentoRelatorioSnapshot
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca snapshot por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM frotas_abastecimentos_relatorios_snapshots WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca snapshot por período
     */
    public function buscarPorPeriodo(string $tipo_periodo, string $periodo_inicio, string $periodo_fim): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_snapshots
            WHERE tipo_periodo = ?
            AND periodo_inicio = ?
            AND periodo_fim = ?
        ";

        return $this->db->buscarUm($sql, [$tipo_periodo, $periodo_inicio, $periodo_fim]);
    }

    /**
     * Busca snapshot por mês
     */
    public function buscarPorMes(int $ano, int $mes): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_snapshots
            WHERE tipo_periodo = 'mensal'
            AND ano = ?
            AND mes = ?
        ";

        return $this->db->buscarUm($sql, [$ano, $mes]);
    }

    /**
     * Busca snapshot por semana
     */
    public function buscarPorSemana(int $ano, int $semana): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_snapshots
            WHERE tipo_periodo = 'semanal'
            AND ano = ?
            AND semana = ?
        ";

        return $this->db->buscarUm($sql, [$ano, $semana]);
    }

    /**
     * Busca último snapshot
     */
    public function buscarUltimo(string $tipo_periodo): ?array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_snapshots
            WHERE tipo_periodo = ?
            ORDER BY calculado_em DESC
            LIMIT 1
        ";

        return $this->db->buscarUm($sql, [$tipo_periodo]);
    }

    /**
     * Busca histórico de snapshots
     */
    public function buscarHistorico(string $tipo_periodo, int $limite = 12): array
    {
        $sql = "
            SELECT * FROM frotas_abastecimentos_relatorios_snapshots
            WHERE tipo_periodo = ?
            ORDER BY ano DESC, " . ($tipo_periodo === 'mensal' ? 'mes' : 'semana') . " DESC
            LIMIT ?
        ";

        return $this->db->buscarTodos($sql, [$tipo_periodo, $limite]);
    }

    /**
     * Cria snapshot
     */
    public function criar(array $dados): int
    {
        $sql = "
            INSERT INTO frotas_abastecimentos_relatorios_snapshots (
                tipo_periodo, periodo_inicio, periodo_fim, ano, mes, semana,
                total_abastecimentos, total_litros, total_valor, total_km_percorrido,
                consumo_medio_geral, custo_medio_por_km, custo_medio_por_litro,
                variacao_consumo_vs_anterior, variacao_custo_vs_anterior, economia_vs_anterior,
                total_alertas, alertas_criticos, alertas_altos,
                dados_por_frota, dados_por_motorista, dados_por_combustivel,
                ranking_consumo, ranking_economia, tempo_calculo, calculado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $parametros = [
            $dados['tipo_periodo'],
            $dados['periodo_inicio'],
            $dados['periodo_fim'],
            $dados['ano'],
            $dados['mes'] ?? null,
            $dados['semana'] ?? null,
            $dados['total_abastecimentos'] ?? 0,
            $dados['total_litros'] ?? 0,
            $dados['total_valor'] ?? 0,
            $dados['total_km_percorrido'] ?? 0,
            $dados['consumo_medio_geral'] ?? 0,
            $dados['custo_medio_por_km'] ?? 0,
            $dados['custo_medio_por_litro'] ?? 0,
            $dados['variacao_consumo_vs_anterior'] ?? null,
            $dados['variacao_custo_vs_anterior'] ?? null,
            $dados['economia_vs_anterior'] ?? null,
            $dados['total_alertas'] ?? 0,
            $dados['alertas_criticos'] ?? 0,
            $dados['alertas_altos'] ?? 0,
            isset($dados['dados_por_frota']) ? json_encode($dados['dados_por_frota']) : null,
            isset($dados['dados_por_motorista']) ? json_encode($dados['dados_por_motorista']) : null,
            isset($dados['dados_por_combustivel']) ? json_encode($dados['dados_por_combustivel']) : null,
            isset($dados['ranking_consumo']) ? json_encode($dados['ranking_consumo']) : null,
            isset($dados['ranking_economia']) ? json_encode($dados['ranking_economia']) : null,
            $dados['tempo_calculo'] ?? null
        ];

        return $this->db->executar($sql, $parametros);
    }

    /**
     * Atualiza snapshot
     */
    public function atualizar(int $id, array $dados): bool
    {
        $sql = "
            UPDATE frotas_abastecimentos_relatorios_snapshots SET
                total_abastecimentos = ?,
                total_litros = ?,
                total_valor = ?,
                total_km_percorrido = ?,
                consumo_medio_geral = ?,
                custo_medio_por_km = ?,
                custo_medio_por_litro = ?,
                variacao_consumo_vs_anterior = ?,
                variacao_custo_vs_anterior = ?,
                economia_vs_anterior = ?,
                total_alertas = ?,
                alertas_criticos = ?,
                alertas_altos = ?,
                dados_por_frota = ?,
                dados_por_motorista = ?,
                dados_por_combustivel = ?,
                ranking_consumo = ?,
                ranking_economia = ?,
                tempo_calculo = ?,
                calculado_em = NOW()
            WHERE id = ?
        ";

        $parametros = [
            $dados['total_abastecimentos'] ?? 0,
            $dados['total_litros'] ?? 0,
            $dados['total_valor'] ?? 0,
            $dados['total_km_percorrido'] ?? 0,
            $dados['consumo_medio_geral'] ?? 0,
            $dados['custo_medio_por_km'] ?? 0,
            $dados['custo_medio_por_litro'] ?? 0,
            $dados['variacao_consumo_vs_anterior'] ?? null,
            $dados['variacao_custo_vs_anterior'] ?? null,
            $dados['economia_vs_anterior'] ?? null,
            $dados['total_alertas'] ?? 0,
            $dados['alertas_criticos'] ?? 0,
            $dados['alertas_altos'] ?? 0,
            isset($dados['dados_por_frota']) ? json_encode($dados['dados_por_frota']) : null,
            isset($dados['dados_por_motorista']) ? json_encode($dados['dados_por_motorista']) : null,
            isset($dados['dados_por_combustivel']) ? json_encode($dados['dados_por_combustivel']) : null,
            isset($dados['ranking_consumo']) ? json_encode($dados['ranking_consumo']) : null,
            isset($dados['ranking_economia']) ? json_encode($dados['ranking_economia']) : null,
            $dados['tempo_calculo'] ?? null,
            $id
        ];

        $this->db->executar($sql, $parametros);
        return true;
    }

    /**
     * Verifica se snapshot existe
     */
    public function existe(string $tipo_periodo, string $periodo_inicio, string $periodo_fim): bool
    {
        $snapshot = $this->buscarPorPeriodo($tipo_periodo, $periodo_inicio, $periodo_fim);
        return $snapshot !== null;
    }

    /**
     * Deleta snapshots antigos
     */
    public function deletarAntigos(int $meses = 24): int
    {
        $sql = "
            DELETE FROM frotas_abastecimentos_relatorios_snapshots
            WHERE calculado_em < DATE_SUB(NOW(), INTERVAL ? MONTH)
        ";

        return $this->db->executar($sql, [$meses]);
    }
}
