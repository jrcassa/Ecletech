<?php

namespace App\Services\FrotaAbastecimento;

use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoMetrica;

/**
 * Service para calcular métricas de abastecimentos
 */
class ServiceFrotaAbastecimentoMetricas
{
    private ModelFrotaAbastecimento $model;
    private ModelFrotaAbastecimentoMetrica $modelMetrica;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimento();
        $this->modelMetrica = new ModelFrotaAbastecimentoMetrica();
    }

    /**
     * Calcula todas as métricas de um abastecimento
     */
    public function calcularMetricasAbastecimento(int $abastecimento_id): array
    {
        $abastecimento = $this->model->buscarPorId($abastecimento_id);
        if (!$abastecimento) {
            throw new \Exception('Abastecimento não encontrado');
        }

        // Busca último abastecimento da frota
        $ultimoAbastecimento = $this->model->buscarUltimoAbastecimentoFrota($abastecimento['frota_id']);

        $metricas = [];

        // Calcular KM percorrido
        if ($ultimoAbastecimento && $ultimoAbastecimento['id'] != $abastecimento_id) {
            $metricas['km_percorrido'] = $abastecimento['km'] - $ultimoAbastecimento['km'];
        } else {
            $metricas['km_percorrido'] = null;
        }

        // Calcular consumo (km/l)
        if ($metricas['km_percorrido'] && $abastecimento['litros'] > 0) {
            $metricas['consumo_km_por_litro'] = $metricas['km_percorrido'] / $abastecimento['litros'];
        } else {
            $metricas['consumo_km_por_litro'] = null;
        }

        // Calcular custo por km
        if ($metricas['km_percorrido'] && $abastecimento['valor'] > 0) {
            $metricas['custo_por_km'] = $abastecimento['valor'] / $metricas['km_percorrido'];
        } else {
            $metricas['custo_por_km'] = null;
        }

        // Calcular custo por litro
        if ($abastecimento['litros'] > 0) {
            $metricas['custo_por_litro'] = $abastecimento['valor'] / $abastecimento['litros'];
        } else {
            $metricas['custo_por_litro'] = null;
        }

        // Calcular dias desde último
        if ($ultimoAbastecimento && $ultimoAbastecimento['data_abastecimento']) {
            $dataUltimo = new \DateTime($ultimoAbastecimento['data_abastecimento']);
            $dataAtual = new \DateTime($abastecimento['data_abastecimento']);
            $metricas['dias_desde_ultimo'] = $dataAtual->diff($dataUltimo)->days;
        } else {
            $metricas['dias_desde_ultimo'] = null;
        }

        // Calcular média km por dia
        if ($metricas['km_percorrido'] && $metricas['dias_desde_ultimo'] > 0) {
            $metricas['media_km_por_dia'] = $metricas['km_percorrido'] / $metricas['dias_desde_ultimo'];
        } else {
            $metricas['media_km_por_dia'] = null;
        }

        // Salvar ou atualizar métricas
        $metricas['abastecimento_id'] = $abastecimento_id;

        $metricaExistente = $this->modelMetrica->buscarPorAbastecimentoId($abastecimento_id);
        if ($metricaExistente) {
            $this->modelMetrica->atualizar($abastecimento_id, $metricas);
        } else {
            $this->modelMetrica->criar($metricas);
        }

        return $metricas;
    }

    /**
     * Obtém dashboard de métricas gerais
     */
    public function obterDashboardMetricas(array $filtros = []): array
    {
        // Estatísticas básicas
        $estatisticas = $this->model->obterEstatisticas($filtros);

        // Busca abastecimentos do período
        $abastecimentos = $this->model->listar($filtros);

        $metricasAbastecimentos = [];
        foreach ($abastecimentos as $abast) {
            $metrica = $this->modelMetrica->buscarPorAbastecimento($abast['id']);
            if ($metrica) {
                $metricasAbastecimentos[] = $metrica;
            }
        }

        // Calcula médias de consumo
        $consumos = array_filter(array_column($metricasAbastecimentos, 'consumo_km_por_litro'));
        $consumoMedio = !empty($consumos) ? array_sum($consumos) / count($consumos) : 0;
        $melhorConsumo = !empty($consumos) ? max($consumos) : 0;
        $piorConsumo = !empty($consumos) ? min($consumos) : 0;

        // Calcula totais de KM
        $totalKmPercorrido = array_sum(array_column($metricasAbastecimentos, 'km_percorrido'));

        return [
            'estatisticas_gerais' => $estatisticas,
            'total_km_percorrido' => $totalKmPercorrido,
            'consumo_medio' => $consumoMedio,
            'melhor_consumo' => $melhorConsumo,
            'pior_consumo' => $piorConsumo,
            'total_metricas' => count($metricasAbastecimentos)
        ];
    }

    /**
     * Obtém ranking de consumo
     */
    public function obterRankingConsumo(array $filtros = []): array
    {
        $tipo = $filtros['tipo'] ?? 'frota';

        // Busca abastecimentos do período
        $abastecimentos = $this->model->listar($filtros);

        // Agrupa por tipo
        $grupos = [];

        foreach ($abastecimentos as $abast) {
            $metrica = $this->modelMetrica->buscarPorAbastecimento($abast['id']);

            if (!$metrica || !$metrica['consumo_km_por_litro']) {
                continue;
            }

            $chave = $tipo === 'frota' ? $abast['frota_id'] : $abast['colaborador_id'];

            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [
                    'id' => $chave,
                    'nome' => $tipo === 'frota' ? ($abast['frota_placa'] ?? 'N/A') : ($abast['motorista_nome'] ?? 'N/A'),
                    'consumos' => [],
                    'total_abastecimentos' => 0
                ];
            }

            $grupos[$chave]['consumos'][] = $metrica['consumo_km_por_litro'];
            $grupos[$chave]['total_abastecimentos']++;
        }

        // Calcula médias
        foreach ($grupos as &$grupo) {
            $grupo['consumo_medio'] = array_sum($grupo['consumos']) / count($grupo['consumos']);
            unset($grupo['consumos']);
        }

        // Ordena por consumo médio (melhor primeiro)
        usort($grupos, fn($a, $b) => $b['consumo_medio'] <=> $a['consumo_medio']);

        return [
            'tipo' => $tipo,
            'melhores' => array_slice($grupos, 0, 10),
            'piores' => array_slice(array_reverse($grupos), 0, 10)
        ];
    }

    /**
     * Compara dois períodos
     */
    public function compararPeriodos(array $periodo1, array $periodo2, ?int $frota_id = null): array
    {
        $filtros1 = [
            'data_inicio' => $periodo1['inicio'],
            'data_fim' => $periodo1['fim']
        ];

        $filtros2 = [
            'data_inicio' => $periodo2['inicio'],
            'data_fim' => $periodo2['fim']
        ];

        if ($frota_id) {
            $filtros1['frota_id'] = $frota_id;
            $filtros2['frota_id'] = $frota_id;
        }

        // Dashboard de cada período
        $dashboard1 = $this->obterDashboardMetricas($filtros1);
        $dashboard2 = $this->obterDashboardMetricas($filtros2);

        // Calcula variações
        $variacoes = [];

        if ($dashboard1['consumo_medio'] > 0) {
            $variacoes['consumo_medio'] = (($dashboard2['consumo_medio'] - $dashboard1['consumo_medio']) / $dashboard1['consumo_medio']) * 100;
        }

        $estatisticas1 = $dashboard1['estatisticas_gerais'];
        $estatisticas2 = $dashboard2['estatisticas_gerais'];

        if ($estatisticas1['total_valor'] > 0) {
            $variacoes['total_valor'] = (($estatisticas2['total_valor'] - $estatisticas1['total_valor']) / $estatisticas1['total_valor']) * 100;
        }

        if ($estatisticas1['total_litros'] > 0) {
            $variacoes['total_litros'] = (($estatisticas2['total_litros'] - $estatisticas1['total_litros']) / $estatisticas1['total_litros']) * 100;
        }

        $variacoes['total_abastecimentos'] = $estatisticas2['total_abastecimentos'] - $estatisticas1['total_abastecimentos'];
        $variacoes['economia'] = $estatisticas1['total_valor'] - $estatisticas2['total_valor'];

        return [
            'periodo1' => [
                'inicio' => $periodo1['inicio'],
                'fim' => $periodo1['fim'],
                'dados' => $dashboard1
            ],
            'periodo2' => [
                'inicio' => $periodo2['inicio'],
                'fim' => $periodo2['fim'],
                'dados' => $dashboard2
            ],
            'variacoes' => $variacoes
        ];
    }
}
