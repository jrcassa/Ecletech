<?php

namespace App\Controllers\FrotaAbastecimento;

use App\Controllers\BaseController;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoMetrica;
use App\Services\FrotaAbastecimento\ServiceFrotaAbastecimentoMetricas;

/**
 * Controller para consulta de métricas de abastecimentos
 */
class ControllerFrotaAbastecimentoMetrica extends BaseController
{
    private ModelFrotaAbastecimentoMetrica $model;
    private ServiceFrotaAbastecimentoMetricas $service;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimentoMetrica();
        $this->service = new ServiceFrotaAbastecimentoMetricas();
    }

    /**
     * Obtém métricas de um abastecimento específico
     */
    public function obterPorAbastecimento(string $abastecimento_id): void
    {
        try {
            if (!$this->validarId($abastecimento_id)) { return; }

            $metrica = $this->model->buscarPorAbastecimento((int) $abastecimento_id);

            if (!$metrica) {
                $this->naoEncontrado('Métricas não encontradas para este abastecimento');
                return;
            }

            $this->sucesso($metrica, 'Métricas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém histórico de consumo de uma frota
     */
    public function historicoConsumoFrota(string $frota_id): void
    {
        try {
            if (!$this->validarId($frota_id)) { return; }

            $limite = (int) ($_GET['limite'] ?? 12);

            $historico = $this->model->obterHistoricoConsumo((int) $frota_id, $limite);

            $this->sucesso($historico, 'Histórico de consumo obtido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém consumo médio de uma frota
     */
    public function consumoMedioFrota(string $frota_id): void
    {
        try {
            if (!$this->validarId($frota_id)) { return; }

            $periodo = null;
            if (isset($_GET['data_inicio']) && isset($_GET['data_fim'])) {
                $periodo = [
                    'inicio' => $_GET['data_inicio'],
                    'fim' => $_GET['data_fim']
                ];
            }

            $consumoMedio = $this->model->calcularMediaConsumoFrota((int) $frota_id, $periodo);

            $this->sucesso([
                'frota_id' => (int) $frota_id,
                'consumo_medio_km_por_litro' => $consumoMedio,
                'periodo' => $periodo
            ], 'Consumo médio calculado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém custo médio de uma frota
     */
    public function custoMedioFrota(string $frota_id): void
    {
        try {
            if (!$this->validarId($frota_id)) { return; }

            $periodo = null;
            if (isset($_GET['data_inicio']) && isset($_GET['data_fim'])) {
                $periodo = [
                    'inicio' => $_GET['data_inicio'],
                    'fim' => $_GET['data_fim']
                ];
            }

            $custoMedio = $this->model->calcularMediaCustoPorKm((int) $frota_id, $periodo);

            $this->sucesso([
                'frota_id' => (int) $frota_id,
                'custo_medio_por_km' => $custoMedio,
                'periodo' => $periodo
            ], 'Custo médio calculado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém dashboard de métricas gerais
     */
    public function dashboard(): void
    {
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null,
                'frota_id' => $_GET['frota_id'] ?? null,
                'colaborador_id' => $_GET['colaborador_id'] ?? null
            ];

            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            $dashboard = $this->service->obterDashboardMetricas($filtros);

            $this->sucesso($dashboard, 'Dashboard obtido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém ranking de consumo (melhores e piores)
     */
    public function rankingConsumo(): void
    {
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null,
                'tipo' => $_GET['tipo'] ?? 'frota' // frota ou motorista
            ];

            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            $ranking = $this->service->obterRankingConsumo($filtros);

            $this->sucesso($ranking, 'Ranking obtido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém análise comparativa de períodos
     */
    public function comparativoPeriodos(): void
    {
        try {
            $periodo1 = [
                'inicio' => $_GET['periodo1_inicio'] ?? null,
                'fim' => $_GET['periodo1_fim'] ?? null
            ];

            $periodo2 = [
                'inicio' => $_GET['periodo2_inicio'] ?? null,
                'fim' => $_GET['periodo2_fim'] ?? null
            ];

            if (!$periodo1['inicio'] || !$periodo1['fim'] || !$periodo2['inicio'] || !$periodo2['fim']) {
                $this->erro('Períodos incompletos. Informe periodo1_inicio, periodo1_fim, periodo2_inicio e periodo2_fim');
                return;
            }

            $frota_id = isset($_GET['frota_id']) ? (int) $_GET['frota_id'] : null;

            $comparativo = $this->service->compararPeriodos($periodo1, $periodo2, $frota_id);

            $this->sucesso($comparativo, 'Comparativo gerado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
