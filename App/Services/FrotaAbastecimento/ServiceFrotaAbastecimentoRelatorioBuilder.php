<?php

namespace App\Services\FrotaAbastecimento;

/**
 * Service para construir mensagens formatadas de relatórios
 */
class ServiceFrotaAbastecimentoRelatorioBuilder
{
    /**
     * Constrói relatório resumido
     */
    public function construirResumo(array $dados): string
    {
        $tipo = ucfirst($dados['tipo_periodo']);
        $periodo = $dados['periodo_inicio'] . ' a ' . $dados['periodo_fim'];

        $mensagem = "*📊 RELATÓRIO {$tipo} DE ABASTECIMENTOS*\n\n";
        $mensagem .= "📅 *Período:* {$periodo}\n\n";

        // Resumo geral
        $mensagem .= "*📈 RESUMO GERAL*\n";
        $mensagem .= "✓ Total de abastecimentos: *{$dados['total_abastecimentos']}*\n";
        $mensagem .= "⛽ Total de litros: *" . number_format($dados['total_litros'], 2, ',', '.') . " L*\n";
        $mensagem .= "💰 Valor total: *R$ " . number_format($dados['total_valor'], 2, ',', '.') . "*\n";
        $mensagem .= "🚗 KM percorrido: *" . number_format($dados['total_km_percorrido'], 2, ',', '.') . " km*\n\n";

        // Médias
        $mensagem .= "*📊 MÉDIAS*\n";
        $mensagem .= "⛽ Consumo médio: *" . number_format($dados['consumo_medio_geral'], 2, ',', '.') . " km/L*\n";
        $mensagem .= "💸 Custo/km: *R$ " . number_format($dados['custo_medio_por_km'], 2, ',', '.') . "*\n";
        $mensagem .= "💵 Custo/litro: *R$ " . number_format($dados['custo_medio_por_litro'], 3, ',', '.') . "*\n\n";

        // Variações
        if ($dados['variacao_consumo_vs_anterior'] !== null) {
            $sinal = $dados['variacao_consumo_vs_anterior'] > 0 ? '📈' : '📉';
            $mensagem .= "*{$sinal} COMPARATIVO*\n";
            $mensagem .= "Variação consumo: *" . number_format($dados['variacao_consumo_vs_anterior'], 2, ',', '.') . "%*\n";

            if ($dados['economia_vs_anterior'] !== null) {
                $economia = $dados['economia_vs_anterior'] > 0 ? '✅ Economia' : '⚠️ Aumento';
                $mensagem .= "{$economia}: *R$ " . number_format(abs($dados['economia_vs_anterior']), 2, ',', '.') . "*\n\n";
            }
        }

        // Alertas
        if ($dados['total_alertas'] > 0) {
            $mensagem .= "*⚠️ ALERTAS*\n";
            $mensagem .= "Total: *{$dados['total_alertas']}*";
            if ($dados['alertas_criticos'] > 0) {
                $mensagem .= " (🔴 {$dados['alertas_criticos']} críticos)";
            }
            $mensagem .= "\n";
        }

        return $mensagem;
    }

    /**
     * Constrói relatório detalhado
     */
    public function construirDetalhado(array $dados): string
    {
        $mensagem = $this->construirResumo($dados);
        $mensagem .= "\n";

        // Dados por frota (top 5)
        if (!empty($dados['dados_por_frota'])) {
            $mensagem .= "*🚗 TOP 5 VEÍCULOS (por consumo)*\n";
            $frotas = array_slice($dados['dados_por_frota'], 0, 5);
            $posicao = 1;

            foreach ($frotas as $frota) {
                $placa = $frota['placa'] ?? 'N/A';
                $consumo = number_format($frota['consumo_medio'] ?? 0, 2, ',', '.');
                $abastecimentos = $frota['total_abastecimentos'] ?? 0;

                $mensagem .= "{$posicao}. *{$placa}* - {$consumo} km/L ({$abastecimentos} abast.)\n";
                $posicao++;
            }
            $mensagem .= "\n";
        }

        // Dados por motorista (top 5)
        if (!empty($dados['dados_por_motorista'])) {
            $mensagem .= "*👤 TOP 5 MOTORISTAS (por economia)*\n";
            $motoristas = array_slice($dados['dados_por_motorista'], 0, 5);
            $posicao = 1;

            foreach ($motoristas as $motorista) {
                $nome = $motorista['nome'] ?? 'N/A';
                $economia = number_format($motorista['custo_medio_por_km'] ?? 0, 2, ',', '.');
                $abastecimentos = $motorista['total_abastecimentos'] ?? 0;

                $mensagem .= "{$posicao}. *{$nome}* - R$ {$economia}/km ({$abastecimentos} abast.)\n";
                $posicao++;
            }
            $mensagem .= "\n";
        }

        // Dados por combustível
        if (!empty($dados['dados_por_combustivel'])) {
            $mensagem .= "*⛽ POR TIPO DE COMBUSTÍVEL*\n";

            foreach ($dados['dados_por_combustivel'] as $combustivel) {
                $tipo = ucfirst($combustivel['tipo'] ?? 'N/A');
                $litros = number_format($combustivel['total_litros'] ?? 0, 2, ',', '.');
                $preco_medio = number_format($combustivel['preco_medio'] ?? 0, 3, ',', '.');

                $mensagem .= "• *{$tipo}*: {$litros} L - R$ {$preco_medio}/L\n";
            }
        }

        return $mensagem;
    }

    /**
     * Constrói relatório completo
     */
    public function construirCompleto(array $dados): string
    {
        $mensagem = $this->construirDetalhado($dados);
        $mensagem .= "\n\n";

        // Ranking completo de consumo
        if (!empty($dados['ranking_consumo'])) {
            $mensagem .= "*🏆 RANKING COMPLETO DE CONSUMO*\n\n";
            $mensagem .= "_Melhor consumo (km/L):_\n";

            $melhores = array_slice($dados['ranking_consumo']['melhores'] ?? [], 0, 3);
            foreach ($melhores as $i => $item) {
                $pos = $i + 1;
                $placa = $item['placa'] ?? 'N/A';
                $consumo = number_format($item['consumo_medio'] ?? 0, 2, ',', '.');
                $mensagem .= "{$pos}º 🥇 *{$placa}* - {$consumo} km/L\n";
            }

            $mensagem .= "\n_Pior consumo:_\n";
            $piores = array_slice($dados['ranking_consumo']['piores'] ?? [], 0, 3);
            foreach ($piores as $i => $item) {
                $pos = $i + 1;
                $placa = $item['placa'] ?? 'N/A';
                $consumo = number_format($item['consumo_medio'] ?? 0, 2, ',', '.');
                $mensagem .= "{$pos}º ⚠️ *{$placa}* - {$consumo} km/L\n";
            }
            $mensagem .= "\n";
        }

        // Ranking de economia
        if (!empty($dados['ranking_economia'])) {
            $mensagem .= "*💰 RANKING DE ECONOMIA*\n\n";
            $mensagem .= "_Melhor custo/km:_\n";

            $melhores = array_slice($dados['ranking_economia']['melhores'] ?? [], 0, 3);
            foreach ($melhores as $i => $item) {
                $pos = $i + 1;
                $nome = $item['motorista_nome'] ?? $item['placa'] ?? 'N/A';
                $custo = number_format($item['custo_medio_por_km'] ?? 0, 2, ',', '.');
                $mensagem .= "{$pos}º 💚 *{$nome}* - R$ {$custo}/km\n";
            }

            $mensagem .= "\n_Maior custo/km:_\n";
            $piores = array_slice($dados['ranking_economia']['piores'] ?? [], 0, 3);
            foreach ($piores as $i => $item) {
                $pos = $i + 1;
                $nome = $item['motorista_nome'] ?? $item['placa'] ?? 'N/A';
                $custo = number_format($item['custo_medio_por_km'] ?? 0, 2, ',', '.');
                $mensagem .= "{$pos}º 💸 *{$nome}* - R$ {$custo}/km\n";
            }
        }

        $mensagem .= "\n\n_Relatório gerado automaticamente pelo sistema de gestão de frotas._";

        return $mensagem;
    }

    /**
     * Constrói mensagem baseada no formato
     */
    public function construir(array $dados, string $formato = 'detalhado'): string
    {
        return match($formato) {
            'resumido' => $this->construirResumo($dados),
            'detalhado' => $this->construirDetalhado($dados),
            'completo' => $this->construirCompleto($dados),
            default => $this->construirDetalhado($dados)
        };
    }
}
