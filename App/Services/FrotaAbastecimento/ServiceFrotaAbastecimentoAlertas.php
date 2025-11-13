<?php

namespace App\Services\FrotaAbastecimento;

use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoMetrica;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoAlerta;

/**
 * Service para detectar alertas em abastecimentos
 */
class ServiceFrotaAbastecimentoAlertas
{
    private ModelFrotaAbastecimento $model;
    private ModelFrotaAbastecimentoMetrica $modelMetrica;
    private ModelFrotaAbastecimentoAlerta $modelAlerta;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimento();
        $this->modelMetrica = new ModelFrotaAbastecimentoMetrica();
        $this->modelAlerta = new ModelFrotaAbastecimentoAlerta();
    }

    /**
     * Detecta todos os alertas de um abastecimento
     */
    public function detectarAlertasAbastecimento(int $abastecimento_id): array
    {
        $alertas = [];

        // Detectar cada tipo de alerta
        $alertas = array_merge($alertas, $this->detectarConsumoAnormal($abastecimento_id));
        $alertas = array_merge($alertas, $this->detectarPrecoElevado($abastecimento_id));
        $alertas = array_merge($alertas, $this->detectarIntervaloLongo($abastecimento_id));

        return $alertas;
    }

    /**
     * Detecta consumo anormal
     */
    private function detectarConsumoAnormal(int $abastecimento_id): array
    {
        $alertas = [];

        $metrica = $this->modelMetrica->buscarPorAbastecimentoId($abastecimento_id);
        if (!$metrica || !$metrica['consumo_km_por_litro']) {
            return $alertas;
        }

        $abastecimento = $this->model->buscarPorId($abastecimento_id);
        $mediaFrota = $this->modelMetrica->calcularMediaConsumoFrota($abastecimento['frota_id']);

        if (!$mediaFrota) {
            return $alertas;
        }

        $consumoAtual = $metrica['consumo_km_por_litro'];
        $variacao = (($consumoAtual - $mediaFrota) / $mediaFrota) * 100;

        // Consumo muito baixo (> 30% abaixo da média)
        if ($variacao < -30) {
            $alertas[] = $this->modelAlerta->criar([
                'abastecimento_id' => $abastecimento_id,
                'tipo_alerta' => 'consumo_muito_baixo',
                'severidade' => 'alta',
                'titulo' => 'Consumo anormalmente baixo detectado',
                'descricao' => sprintf(
                    'Veículo está com consumo de %.2f km/l (média: %.2f km/l). Variação: %.1f%%',
                    $consumoAtual,
                    $mediaFrota,
                    $variacao
                ),
                'valor_esperado' => number_format($mediaFrota, 2) . ' km/l',
                'valor_real' => number_format($consumoAtual, 2) . ' km/l'
            ]);
        }

        // Consumo muito alto (> 30% acima da média) - pode ser erro de lançamento
        if ($variacao > 30) {
            $alertas[] = $this->modelAlerta->criar([
                'abastecimento_id' => $abastecimento_id,
                'tipo_alerta' => 'consumo_muito_alto',
                'severidade' => 'media',
                'titulo' => 'Consumo acima do esperado',
                'descricao' => sprintf(
                    'Veículo está com consumo de %.2f km/l (média: %.2f km/l). Variação: %.1f%%',
                    $consumoAtual,
                    $mediaFrota,
                    $variacao
                ),
                'valor_esperado' => number_format($mediaFrota, 2) . ' km/l',
                'valor_real' => number_format($consumoAtual, 2) . ' km/l'
            ]);
        }

        return $alertas;
    }

    /**
     * Detecta preço elevado
     */
    private function detectarPrecoElevado(int $abastecimento_id): array
    {
        $alertas = [];

        $metrica = $this->modelMetrica->buscarPorAbastecimentoId($abastecimento_id);
        if (!$metrica || !$metrica['custo_por_litro']) {
            return $alertas;
        }

        // Aqui poderia comparar com média de preços da região/histórico
        // Por simplicidade, vamos apenas registrar se passar de um valor fixo
        $precoLimite = 7.00; // R$ por litro

        if ($metrica['custo_por_litro'] > $precoLimite) {
            $alertas[] = $this->modelAlerta->criar([
                'abastecimento_id' => $abastecimento_id,
                'tipo_alerta' => 'preco_acima_media',
                'severidade' => 'baixa',
                'titulo' => 'Preço por litro acima do limite',
                'descricao' => sprintf(
                    'Preço de R$ %.2f/litro está acima do limite de R$ %.2f/litro',
                    $metrica['custo_por_litro'],
                    $precoLimite
                ),
                'valor_esperado' => 'R$ ' . number_format($precoLimite, 2, ',', '.'),
                'valor_real' => 'R$ ' . number_format($metrica['custo_por_litro'], 2, ',', '.')
            ]);
        }

        return $alertas;
    }

    /**
     * Detecta intervalo longo entre abastecimentos
     */
    private function detectarIntervaloLongo(int $abastecimento_id): array
    {
        $alertas = [];

        $metrica = $this->modelMetrica->buscarPorAbastecimentoId($abastecimento_id);
        if (!$metrica || !$metrica['dias_desde_ultimo']) {
            return $alertas;
        }

        if ($metrica['dias_desde_ultimo'] > 60) {
            $alertas[] = $this->modelAlerta->criar([
                'abastecimento_id' => $abastecimento_id,
                'tipo_alerta' => 'intervalo_muito_longo',
                'severidade' => 'baixa',
                'titulo' => 'Intervalo longo entre abastecimentos',
                'descricao' => sprintf(
                    '%d dias desde o último abastecimento. Verifique se veículo está sendo utilizado.',
                    $metrica['dias_desde_ultimo']
                ),
                'valor_esperado' => 'Até 60 dias',
                'valor_real' => $metrica['dias_desde_ultimo'] . ' dias'
            ]);
        }

        return $alertas;
    }
}
