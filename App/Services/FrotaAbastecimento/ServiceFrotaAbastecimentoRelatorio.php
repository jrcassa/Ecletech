<?php

namespace App\Services\FrotaAbastecimento;

use App\Models\FrotaAbastecimento\ModelFrotaAbastecimento;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioConfiguracao;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioLog;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoRelatorioSnapshot;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoMetrica;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoAlerta;
use App\Models\Colaborador\ModelColaborador;
use App\Services\Whatsapp\ServiceWhatsapp;

/**
 * Service para gerenciar geração e envio de relatórios automáticos
 */
class ServiceFrotaAbastecimentoRelatorio
{
    private ModelFrotaAbastecimento $modelAbastecimento;
    private ModelFrotaAbastecimentoRelatorioConfiguracao $modelConfiguracao;
    private ModelFrotaAbastecimentoRelatorioLog $modelLog;
    private ModelFrotaAbastecimentoRelatorioSnapshot $modelSnapshot;
    private ModelFrotaAbastecimentoMetrica $modelMetrica;
    private ModelFrotaAbastecimentoAlerta $modelAlerta;
    private ModelColaborador $modelColaborador;
    private ServiceWhatsapp $serviceWhatsapp;
    private ServiceFrotaAbastecimentoRelatorioBuilder $builder;

    public function __construct()
    {
        $this->modelAbastecimento = new ModelFrotaAbastecimento();
        $this->modelConfiguracao = new ModelFrotaAbastecimentoRelatorioConfiguracao();
        $this->modelLog = new ModelFrotaAbastecimentoRelatorioLog();
        $this->modelSnapshot = new ModelFrotaAbastecimentoRelatorioSnapshot();
        $this->modelMetrica = new ModelFrotaAbastecimentoMetrica();
        $this->modelAlerta = new ModelFrotaAbastecimentoAlerta();
        $this->modelColaborador = new ModelColaborador();
        $this->serviceWhatsapp = new ServiceWhatsapp();
        $this->builder = new ServiceFrotaAbastecimentoRelatorioBuilder();
    }

    /**
     * Calcula snapshot de um período
     */
    public function calcularSnapshot(string $tipo_periodo, string $periodo_inicio, string $periodo_fim): array
    {
        $inicioCalculo = microtime(true);

        // Busca abastecimentos do período (ajusta data fim para incluir o dia completo)
        $dataFimCompleta = date('Y-m-d 23:59:59', strtotime($periodo_fim));

        $filtros = [
            'data_inicio' => $periodo_inicio,
            'data_fim' => $dataFimCompleta,
            'status' => 'abastecido'
        ];

        $abastecimentos = $this->modelAbastecimento->listar($filtros);

        if (empty($abastecimentos)) {
            return [
                'tipo_periodo' => $tipo_periodo,
                'periodo_inicio' => $periodo_inicio,
                'periodo_fim' => $periodo_fim,
                'total_abastecimentos' => 0,
                'total_litros' => 0,
                'total_valor' => 0,
                'total_km_percorrido' => 0,
                'consumo_medio_geral' => 0,
                'custo_medio_por_km' => 0,
                'custo_medio_por_litro' => 0
            ];
        }

        // Agregações gerais
        $totalAbastecimentos = count($abastecimentos);
        $totalLitros = array_sum(array_column($abastecimentos, 'litros'));
        $totalValor = array_sum(array_column($abastecimentos, 'valor'));

        // Busca métricas do período
        $metricas = [];
        foreach ($abastecimentos as $abastecimento) {
            $metrica = $this->modelMetrica->buscarPorAbastecimento($abastecimento['id']);
            if ($metrica) {
                $metricas[] = $metrica;
            }
        }

        $totalKmPercorrido = array_sum(array_column($metricas, 'km_percorrido'));

        // Calcula médias
        $consumoMedioGeral = $totalKmPercorrido > 0 ? $totalKmPercorrido / $totalLitros : 0;
        $custoMedioPorKm = $totalKmPercorrido > 0 ? $totalValor / $totalKmPercorrido : 0;
        $custoMedioPorLitro = $totalLitros > 0 ? $totalValor / $totalLitros : 0;

        // Busca alertas do período
        $alertasFiltros = [
            'data_inicio' => $periodo_inicio,
            'data_fim' => $periodo_fim
        ];
        $alertas = $this->modelAlerta->listar($alertasFiltros);
        $totalAlertas = count($alertas);
        $alertasCriticos = count(array_filter($alertas, fn($a) => $a['severidade'] === 'critica'));
        $alertasAltos = count(array_filter($alertas, fn($a) => $a['severidade'] === 'alta'));

        // Dados por frota
        $dadosPorFrota = $this->agregarPorFrota($abastecimentos, $metricas);

        // Dados por motorista
        $dadosPorMotorista = $this->agregarPorMotorista($abastecimentos, $metricas);

        // Dados por combustível
        $dadosPorCombustivel = $this->agregarPorCombustivel($abastecimentos);

        // Rankings
        $rankingConsumo = $this->calcularRankingConsumo($dadosPorFrota);
        $rankingEconomia = $this->calcularRankingEconomia($dadosPorMotorista);

        // Busca snapshot anterior para comparação
        $snapshotAnterior = $this->buscarSnapshotAnterior($tipo_periodo, $periodo_inicio);
        $variacaoConsumo = null;
        $variacaoCusto = null;
        $economiaVsAnterior = null;

        if ($snapshotAnterior) {
            if ($snapshotAnterior['consumo_medio_geral'] > 0) {
                $variacaoConsumo = (($consumoMedioGeral - $snapshotAnterior['consumo_medio_geral']) / $snapshotAnterior['consumo_medio_geral']) * 100;
            }

            if ($snapshotAnterior['custo_medio_por_km'] > 0) {
                $variacaoCusto = (($custoMedioPorKm - $snapshotAnterior['custo_medio_por_km']) / $snapshotAnterior['custo_medio_por_km']) * 100;
            }

            $economiaVsAnterior = $snapshotAnterior['total_valor'] - $totalValor;
        }

        // Calcula ano, mês e semana
        $data = new \DateTime($periodo_inicio);
        $ano = (int) $data->format('Y');
        $mes = $tipo_periodo === 'mensal' ? (int) $data->format('n') : null;
        $semana = $tipo_periodo === 'semanal' ? (int) $data->format('W') : null;

        $tempoCalculo = round(microtime(true) - $inicioCalculo, 2);

        return [
            'tipo_periodo' => $tipo_periodo,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fim' => $periodo_fim,
            'ano' => $ano,
            'mes' => $mes,
            'semana' => $semana,
            'total_abastecimentos' => $totalAbastecimentos,
            'total_litros' => $totalLitros,
            'total_valor' => $totalValor,
            'total_km_percorrido' => $totalKmPercorrido,
            'consumo_medio_geral' => $consumoMedioGeral,
            'custo_medio_por_km' => $custoMedioPorKm,
            'custo_medio_por_litro' => $custoMedioPorLitro,
            'variacao_consumo_vs_anterior' => $variacaoConsumo,
            'variacao_custo_vs_anterior' => $variacaoCusto,
            'economia_vs_anterior' => $economiaVsAnterior,
            'total_alertas' => $totalAlertas,
            'alertas_criticos' => $alertasCriticos,
            'alertas_altos' => $alertasAltos,
            'dados_por_frota' => $dadosPorFrota,
            'dados_por_motorista' => $dadosPorMotorista,
            'dados_por_combustivel' => $dadosPorCombustivel,
            'ranking_consumo' => $rankingConsumo,
            'ranking_economia' => $rankingEconomia,
            'tempo_calculo' => $tempoCalculo
        ];
    }

    /**
     * Salva ou atualiza snapshot
     */
    public function recalcularSnapshot(string $tipo_periodo, string $periodo_inicio, string $periodo_fim): int
    {
        $dados = $this->calcularSnapshot($tipo_periodo, $periodo_inicio, $periodo_fim);

        // Verifica se já existe snapshot
        $snapshotExistente = $this->modelSnapshot->buscarPorPeriodo($tipo_periodo, $periodo_inicio, $periodo_fim);

        if ($snapshotExistente) {
            $this->modelSnapshot->atualizar($snapshotExistente['id'], $dados);
            return $snapshotExistente['id'];
        } else {
            return $this->modelSnapshot->criar($dados);
        }
    }

    /**
     * Gera relatório manual (sem salvar)
     */
    public function gerarRelatorioManual(string $tipo_relatorio, string $periodo_inicio, string $periodo_fim, string $formato = 'detalhado'): array
    {
        // Calcula dados
        $dados = $this->calcularSnapshot($tipo_relatorio, $periodo_inicio, $periodo_fim);

        // Constrói mensagem
        $mensagem = $this->builder->construir($dados, $formato);

        return [
            'dados' => $dados,
            'mensagem' => $mensagem,
            'formato' => $formato
        ];
    }

    /**
     * Envia relatório manual via WhatsApp
     */
    public function enviarRelatorioManual(int $destinatario_id, string $tipo_relatorio, string $periodo_inicio, string $periodo_fim, string $formato = 'detalhado'): int
    {
        // Busca colaborador
        $colaborador = $this->modelColaborador->buscarPorId($destinatario_id);
        if (!$colaborador || !$colaborador['celular']) {
            throw new \Exception('Colaborador não encontrado ou sem celular cadastrado');
        }

        // Gera relatório
        $relatorio = $this->gerarRelatorioManual($tipo_relatorio, $periodo_inicio, $periodo_fim, $formato);

        // Cria log
        $logId = $this->modelLog->criar([
            'tipo_relatorio' => $tipo_relatorio,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fim' => $periodo_fim,
            'destinatario_id' => $destinatario_id,
            'telefone' => $colaborador['celular'],
            'formato' => $formato,
            'mensagem' => $relatorio['mensagem'],
            'dados_relatorio' => $relatorio['dados'],
            'status_envio' => 'pendente',
            'tamanho_mensagem' => strlen($relatorio['mensagem']),
            'tempo_processamento' => $relatorio['dados']['tempo_calculo'] ?? null
        ]);

        // Envia via WhatsApp (modo direto para ter controle imediato do resultado)
        try {
            $resultado = $this->serviceWhatsapp->enviarMensagem([
                'destinatario' => [
                    'tipo' => 'colaborador',
                    'id' => $destinatario_id
                ],
                'tipo' => 'text',
                'mensagem' => $relatorio['mensagem'],
                'prioridade' => 'normal',
                'modo_envio' => 'direto', // Força envio direto para garantir resultado imediato
                'metadata' => [
                    'modulo' => 'frota_abastecimento_relatorio',
                    'tipo_relatorio' => $tipo_relatorio,
                    'periodo_inicio' => $periodo_inicio,
                    'periodo_fim' => $periodo_fim,
                    'formato' => $formato
                ]
            ]);

            // Se enviou com sucesso (modo direto confirma envio real)
            if ($resultado['sucesso']) {
                $this->modelLog->marcarEnviado($logId);
            } else {
                $this->modelLog->marcarErro($logId, $resultado['erro'] ?? 'Erro desconhecido');
            }
        } catch (\Exception $e) {
            $this->modelLog->marcarErro($logId, $e->getMessage());
            throw $e;
        }

        return $logId;
    }

    /**
     * Processa envios automáticos agendados
     */
    public function processarEnviosAutomaticos(string $tipo_relatorio, string $dia): int
    {
        // Busca configurações ativas para envio
        $configuracoes = $this->modelConfiguracao->buscarParaEnvio($tipo_relatorio, $dia);

        if (empty($configuracoes)) {
            return 0;
        }

        // Determina período baseado no tipo
        $periodo = $this->determinarPeriodo($tipo_relatorio);

        $totalEnviados = 0;

        foreach ($configuracoes as $config) {
            try {
                // Verifica se snapshot existe, senão cria
                $snapshot = $this->modelSnapshot->buscarPorPeriodo($tipo_relatorio, $periodo['inicio'], $periodo['fim']);
                if (!$snapshot) {
                    $this->recalcularSnapshot($tipo_relatorio, $periodo['inicio'], $periodo['fim']);
                    $snapshot = $this->modelSnapshot->buscarPorPeriodo($tipo_relatorio, $periodo['inicio'], $periodo['fim']);
                }

                // Envia relatório
                $this->enviarRelatorioManual(
                    $config['colaborador_id'],
                    $tipo_relatorio,
                    $periodo['inicio'],
                    $periodo['fim'],
                    $config['formato_relatorio']
                );

                $totalEnviados++;
            } catch (\Exception $e) {
                // Log erro mas continua processando outros
                error_log("Erro ao enviar relatório automático para colaborador {$config['colaborador_id']}: " . $e->getMessage());
            }
        }

        return $totalEnviados;
    }

    // ========== MÉTODOS AUXILIARES ==========

    private function agregarPorFrota(array $abastecimentos, array $metricas): array
    {
        $grupos = [];

        foreach ($abastecimentos as $abast) {
            $frotaId = $abast['frota_id'];

            if (!isset($grupos[$frotaId])) {
                $grupos[$frotaId] = [
                    'frota_id' => $frotaId,
                    'placa' => $abast['frota_placa'] ?? 'N/A',
                    'total_abastecimentos' => 0,
                    'total_litros' => 0,
                    'total_valor' => 0,
                    'total_km_percorrido' => 0,
                    'consumos' => []
                ];
            }

            $grupos[$frotaId]['total_abastecimentos']++;
            $grupos[$frotaId]['total_litros'] += $abast['litros'] ?? 0;
            $grupos[$frotaId]['total_valor'] += $abast['valor'] ?? 0;

            // Busca métrica correspondente
            $metrica = array_filter($metricas, fn($m) => $m['abastecimento_id'] == $abast['id']);
            $metrica = reset($metrica);

            if ($metrica && $metrica['km_percorrido']) {
                $grupos[$frotaId]['total_km_percorrido'] += $metrica['km_percorrido'];
                $grupos[$frotaId]['consumos'][] = $metrica['consumo_km_por_litro'];
            }
        }

        // Calcula médias
        foreach ($grupos as &$grupo) {
            $grupo['consumo_medio'] = !empty($grupo['consumos']) ? array_sum($grupo['consumos']) / count($grupo['consumos']) : 0;
            $grupo['custo_medio_por_km'] = $grupo['total_km_percorrido'] > 0 ? $grupo['total_valor'] / $grupo['total_km_percorrido'] : 0;
            unset($grupo['consumos']);
        }

        // Ordena por consumo médio (melhor primeiro)
        usort($grupos, fn($a, $b) => $b['consumo_medio'] <=> $a['consumo_medio']);

        return array_values($grupos);
    }

    private function agregarPorMotorista(array $abastecimentos, array $metricas): array
    {
        $grupos = [];

        foreach ($abastecimentos as $abast) {
            $motoristaId = $abast['colaborador_id'];

            if (!isset($grupos[$motoristaId])) {
                $grupos[$motoristaId] = [
                    'colaborador_id' => $motoristaId,
                    'nome' => $abast['motorista_nome'] ?? 'N/A',
                    'total_abastecimentos' => 0,
                    'total_litros' => 0,
                    'total_valor' => 0,
                    'total_km_percorrido' => 0
                ];
            }

            $grupos[$motoristaId]['total_abastecimentos']++;
            $grupos[$motoristaId]['total_litros'] += $abast['litros'] ?? 0;
            $grupos[$motoristaId]['total_valor'] += $abast['valor'] ?? 0;

            $metrica = array_filter($metricas, fn($m) => $m['abastecimento_id'] == $abast['id']);
            $metrica = reset($metrica);

            if ($metrica && $metrica['km_percorrido']) {
                $grupos[$motoristaId]['total_km_percorrido'] += $metrica['km_percorrido'];
            }
        }

        // Calcula médias
        foreach ($grupos as &$grupo) {
            $grupo['custo_medio_por_km'] = $grupo['total_km_percorrido'] > 0 ? $grupo['total_valor'] / $grupo['total_km_percorrido'] : 0;
        }

        // Ordena por custo médio (menor primeiro = mais econômico)
        usort($grupos, fn($a, $b) => $a['custo_medio_por_km'] <=> $b['custo_medio_por_km']);

        return array_values($grupos);
    }

    private function agregarPorCombustivel(array $abastecimentos): array
    {
        $grupos = [];

        foreach ($abastecimentos as $abast) {
            $tipo = $abast['combustivel'] ?? 'desconhecido';

            if (!isset($grupos[$tipo])) {
                $grupos[$tipo] = [
                    'tipo' => $tipo,
                    'total_abastecimentos' => 0,
                    'total_litros' => 0,
                    'total_valor' => 0,
                    'precos' => []
                ];
            }

            $grupos[$tipo]['total_abastecimentos']++;
            $grupos[$tipo]['total_litros'] += $abast['litros'] ?? 0;
            $grupos[$tipo]['total_valor'] += $abast['valor'] ?? 0;

            if ($abast['preco_por_litro']) {
                $grupos[$tipo]['precos'][] = $abast['preco_por_litro'];
            }
        }

        // Calcula médias
        foreach ($grupos as &$grupo) {
            $grupo['preco_medio'] = !empty($grupo['precos']) ? array_sum($grupo['precos']) / count($grupo['precos']) : 0;
            unset($grupo['precos']);
        }

        return array_values($grupos);
    }

    private function calcularRankingConsumo(array $dadosPorFrota): array
    {
        return [
            'melhores' => array_slice($dadosPorFrota, 0, 5),
            'piores' => array_slice(array_reverse($dadosPorFrota), 0, 5)
        ];
    }

    private function calcularRankingEconomia(array $dadosPorMotorista): array
    {
        return [
            'melhores' => array_slice($dadosPorMotorista, 0, 5),
            'piores' => array_slice(array_reverse($dadosPorMotorista), 0, 5)
        ];
    }

    private function buscarSnapshotAnterior(string $tipo_periodo, string $periodo_inicio): ?array
    {
        $data = new \DateTime($periodo_inicio);

        if ($tipo_periodo === 'mensal') {
            $data->modify('-1 month');
            return $this->modelSnapshot->buscarPorMes((int) $data->format('Y'), (int) $data->format('n'));
        } else {
            $data->modify('-1 week');
            return $this->modelSnapshot->buscarPorSemana((int) $data->format('Y'), (int) $data->format('W'));
        }
    }

    private function determinarPeriodo(string $tipo_relatorio): array
    {
        $hoje = new \DateTime();

        if ($tipo_relatorio === 'mensal') {
            // Mês anterior
            $mesAnterior = clone $hoje;
            $mesAnterior->modify('-1 month');

            return [
                'inicio' => $mesAnterior->format('Y-m-01'),
                'fim' => $mesAnterior->format('Y-m-t')
            ];
        } else {
            // Semana anterior
            $semanaAnterior = clone $hoje;
            $semanaAnterior->modify('-1 week');
            $semanaAnterior->modify('monday this week');
            $inicio = clone $semanaAnterior;
            $fim = clone $semanaAnterior;
            $fim->modify('+6 days');

            return [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d')
            ];
        }
    }
}
