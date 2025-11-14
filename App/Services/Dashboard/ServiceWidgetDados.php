<?php

namespace App\Services\Dashboard;

use App\Core\BancoDados;

/**
 * Service para obter dados dos widgets
 * Integra com as tabelas existentes do sistema
 */
class ServiceWidgetDados
{
    private BancoDados $db;
    private array $cache = [];

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Método principal que roteia para o widget correto
     */
    public function obterDados(string $widgetCodigo, int $colaboradorId, array $config, array $filtros = []): array
    {
        // Verifica cache
        $cacheKey = $this->gerarChaveCache($widgetCodigo, $colaboradorId, $config, $filtros);
        if ($this->temCache($cacheKey)) {
            return $this->obterCache($cacheKey);
        }

        // Extrai categoria do código
        $partes = explode('_', $widgetCodigo);
        $categoria = $partes[0];

        // Roteia para categoria
        $dados = match ($categoria) {
            'vendas' => $this->obterDadosVendas($widgetCodigo, $colaboradorId, $config, $filtros),
            'financeiro' => $this->obterDadosFinanceiro($widgetCodigo, $colaboradorId, $config, $filtros),
            'frota' => $this->obterDadosFrota($widgetCodigo, $colaboradorId, $config, $filtros),
            'clientes' => $this->obterDadosClientes($widgetCodigo, $colaboradorId, $config, $filtros),
            'produtos' => $this->obterDadosProdutos($widgetCodigo, $colaboradorId, $config, $filtros),
            'geral' => $this->obterDadosGeral($widgetCodigo, $colaboradorId, $config, $filtros),
            default => throw new \Exception("Categoria de widget não suportada: {$categoria}")
        };

        // Salva no cache (5 minutos)
        $this->salvarCache($cacheKey, $dados, 300);

        return $dados;
    }

    /**
     * VENDAS - Dados de vendas
     */
    private function obterDadosVendas(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'vendas_total_periodo' => $this->vendas_valor_total($colaboradorId, $config),
            'vendas_quantidade_periodo' => $this->vendas_hoje($colaboradorId, $config),
            'vendas_evolucao_mensal' => $this->vendas_evolucao_periodo($colaboradorId, $config),
            'vendas_por_vendedor' => $this->vendas_por_vendedor($colaboradorId, $config),
            'vendas_por_produto' => $this->vendas_por_produto_donut($colaboradorId, $config),
            'vendas_ticket_medio' => $this->vendas_ticket_medio($colaboradorId, $config),
            'vendas_ultimas' => $this->vendas_ultimas($colaboradorId, $config),
            'vendas_metas' => $this->vendas_metas($colaboradorId, $config),
            default => ['labels' => [], 'values' => []]
        };
    }

    private function vendas_evolucao_periodo(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $vendas = $this->db->buscarTodos(
            "SELECT DATE(criado_em) as data, SUM(valor_total) as total
             FROM vendas
             WHERE criado_em BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(criado_em)
             ORDER BY data ASC",
            [$dataInicio, $dataFim . ' 23:59:59']
        );

        $labels = [];
        $values = [];

        foreach ($vendas as $venda) {
            $labels[] = date('d/m', strtotime($venda['data']));
            $values[] = (float) $venda['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'meta' => [
                'total' => array_sum($values),
                'media' => count($values) > 0 ? array_sum($values) / count($values) : 0,
                'periodo' => "{$dataInicio} a {$dataFim}"
            ]
        ];
    }

    private function vendas_valor_total(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as quantidade,
                COALESCE(SUM(valor_total), 0) as total
             FROM vendas
             WHERE criado_em BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim . ' 23:59:59']
        );

        return [
            'valor' => (float) $resultado['total'],
            'quantidade' => (int) $resultado['quantidade'],
            'label' => 'Total de Vendas',
            'formato' => 'moeda'
        ];
    }

    private function vendas_hoje(int $colaboradorId, array $config): array
    {
        $hoje = date('Y-m-d');

        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as quantidade,
                COALESCE(SUM(valor_total), 0) as total
             FROM vendas
             WHERE DATE(criado_em) = ?
               AND deletado_em IS NULL",
            [$hoje]
        );

        return [
            'valor' => (float) $resultado['total'],
            'quantidade' => (int) $resultado['quantidade'],
            'label' => 'Vendas Hoje',
            'formato' => 'moeda',
            'subtitulo' => $resultado['quantidade'] . ' vendas'
        ];
    }

    private function vendas_ticket_medio(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT
                COUNT(*) as quantidade,
                COALESCE(SUM(valor_total), 0) as total
             FROM vendas
             WHERE criado_em BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim . ' 23:59:59']
        );

        $ticketMedio = $resultado['quantidade'] > 0
            ? $resultado['total'] / $resultado['quantidade']
            : 0;

        return [
            'valor' => (float) $ticketMedio,
            'label' => 'Ticket Médio',
            'formato' => 'moeda',
            'subtitulo' => 'Média por venda'
        ];
    }

    private function vendas_top_produtos(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 10;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $produtos = $this->db->buscarTodos(
            "SELECT
                p.nome as produto,
                COUNT(vi.id) as quantidade,
                SUM(vi.valor_total) as total
             FROM vendas_itens vi
             INNER JOIN vendas v ON vi.venda_id = v.id
             INNER JOIN produtos p ON vi.produto_id = p.id
             WHERE v.criado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
               AND p.deletado_em IS NULL
             GROUP BY p.id, p.nome
             ORDER BY total DESC
             LIMIT ?",
            [$dataInicio, $dataFim . ' 23:59:59', $limite]
        );

        $linhas = [];
        foreach ($produtos as $index => $item) {
            $linhas[] = [
                $index + 1,
                $item['produto'],
                $item['quantidade'],
                'R$ ' . number_format($item['total'], 2, ',', '.')
            ];
        }

        return [
            'colunas' => ['#', 'Produto', 'Qtd', 'Valor Total'],
            'linhas' => $linhas
        ];
    }

    private function vendas_ultimas(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $vendas = $this->db->buscarTodos(
            "SELECT
                v.id,
                v.numero_venda,
                v.valor_total,
                v.criado_em,
                c.nome as cliente_nome
             FROM vendas v
             LEFT JOIN clientes c ON v.cliente_id = c.id
             WHERE v.deletado_em IS NULL
             ORDER BY v.criado_em DESC
             LIMIT ?",
            [$limite]
        );

        $items = [];
        foreach ($vendas as $venda) {
            $items[] = [
                'id' => $venda['id'],
                'titulo' => 'Venda #' . $venda['numero_venda'],
                'subtitulo' => $venda['cliente_nome'] ?? 'Cliente não informado',
                'valor' => 'R$ ' . number_format($venda['valor_total'], 2, ',', '.'),
                'data' => date('d/m/Y H:i', strtotime($venda['criado_em']))
            ];
        }

        return ['items' => $items];
    }

    private function vendas_comparativo_ano(int $colaboradorId, array $config): array
    {
        $anoAtual = date('Y');
        $anoAnterior = $anoAtual - 1;

        // Vendas ano atual
        $vendasAtual = $this->db->buscarTodos(
            "SELECT MONTH(criado_em) as mes, SUM(valor_total) as total
             FROM vendas
             WHERE YEAR(criado_em) = ?
               AND deletado_em IS NULL
             GROUP BY MONTH(criado_em)
             ORDER BY mes ASC",
            [$anoAtual]
        );

        // Vendas ano anterior
        $vendasAnterior = $this->db->buscarTodos(
            "SELECT MONTH(criado_em) as mes, SUM(valor_total) as total
             FROM vendas
             WHERE YEAR(criado_em) = ?
               AND deletado_em IS NULL
             GROUP BY MONTH(criado_em)
             ORDER BY mes ASC",
            [$anoAnterior]
        );

        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $labels = [];
        $datasetsAtual = [];
        $datasetsAnterior = [];

        // Organiza dados por mês
        $vendasAtualPorMes = [];
        foreach ($vendasAtual as $v) {
            $vendasAtualPorMes[$v['mes']] = (float) $v['total'];
        }

        $vendasAnteriorPorMes = [];
        foreach ($vendasAnterior as $v) {
            $vendasAnteriorPorMes[$v['mes']] = (float) $v['total'];
        }

        for ($mes = 1; $mes <= 12; $mes++) {
            $labels[] = $meses[$mes - 1];
            $datasetsAtual[] = $vendasAtualPorMes[$mes] ?? 0;
            $datasetsAnterior[] = $vendasAnteriorPorMes[$mes] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $anoAtual,
                    'data' => $datasetsAtual,
                    'borderColor' => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)'
                ],
                [
                    'label' => $anoAnterior,
                    'data' => $datasetsAnterior,
                    'borderColor' => '#95a5a6',
                    'backgroundColor' => 'rgba(149, 165, 166, 0.1)'
                ]
            ]
        ];
    }

    private function vendas_por_cliente_pizza(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 5;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $clientes = $this->db->buscarTodos(
            "SELECT
                c.nome as cliente,
                SUM(v.valor_total) as total
             FROM vendas v
             INNER JOIN clientes c ON v.cliente_id = c.id
             WHERE v.criado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
             GROUP BY c.id, c.nome
             ORDER BY total DESC
             LIMIT ?",
            [$dataInicio, $dataFim . ' 23:59:59', $limite]
        );

        $labels = [];
        $values = [];

        foreach ($clientes as $cliente) {
            $labels[] = $cliente['cliente'];
            $values[] = (float) $cliente['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function vendas_por_produto_donut(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 8;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $produtos = $this->db->buscarTodos(
            "SELECT
                p.nome as produto,
                SUM(vi.valor_total) as total
             FROM vendas_itens vi
             INNER JOIN vendas v ON vi.venda_id = v.id
             INNER JOIN produtos p ON vi.produto_id = p.id
             WHERE v.criado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
               AND p.deletado_em IS NULL
             GROUP BY p.id, p.nome
             ORDER BY total DESC
             LIMIT ?",
            [$dataInicio, $dataFim . ' 23:59:59', $limite]
        );

        $labels = [];
        $values = [];

        foreach ($produtos as $produto) {
            $labels[] = $produto['produto'];
            $values[] = (float) $produto['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function vendas_por_vendedor(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        // Por enquanto retorna vazio - TODO: Implementar quando houver campo vendedor_id na tabela vendas
        return ['labels' => [], 'values' => []];
    }

    private function vendas_metas(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        // Por enquanto retorna vazio - TODO: Implementar quando houver tabela de metas
        return ['labels' => ['Realizado', 'Meta'], 'values' => [0, 0]];
    }

    /**
     * FINANCEIRO - Dados financeiros
     */
    private function obterDadosFinanceiro(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'financeiro_saldo_atual' => $this->financeiro_saldo($colaboradorId, $config),
            'financeiro_receber_periodo' => $this->financeiro_receber_periodo($colaboradorId, $config),
            'financeiro_pagar_periodo' => $this->financeiro_pagar_periodo($colaboradorId, $config),
            'financeiro_fluxo_caixa' => $this->financeiro_fluxo_caixa($colaboradorId, $config),
            'financeiro_recebimentos_vencidos' => $this->financeiro_recebimentos_vencidos($colaboradorId, $config),
            'financeiro_pagamentos_vencidos' => $this->financeiro_pagamentos_vencidos($colaboradorId, $config),
            'financeiro_contas_bancarias' => $this->financeiro_contas_bancarias($colaboradorId, $config),
            'financeiro_despesas_categoria' => $this->financeiro_despesas_categoria($colaboradorId, $config),
            default => ['labels' => [], 'values' => []]
        };
    }

    private function financeiro_saldo(int $colaboradorId, array $config): array
    {
        $recebimentos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM recebimentos
             WHERE deletado_em IS NULL"
        );

        $pagamentos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM pagamentos
             WHERE deletado_em IS NULL"
        );

        $saldo = $recebimentos['total'] - $pagamentos['total'];

        return [
            'valor' => (float) $saldo,
            'label' => 'Saldo Atual',
            'formato' => 'moeda',
            'cor' => $saldo >= 0 ? 'green' : 'red'
        ];
    }

    private function financeiro_fluxo_caixa(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        // Busca recebimentos
        $recebimentos = $this->db->buscarTodos(
            "SELECT DATE(data_recebimento) as data, SUM(valor) as total
             FROM recebimentos
             WHERE data_recebimento BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(data_recebimento)",
            [$dataInicio, $dataFim]
        );

        // Busca pagamentos
        $pagamentos = $this->db->buscarTodos(
            "SELECT DATE(data_pagamento) as data, SUM(valor) as total
             FROM pagamentos
             WHERE data_pagamento BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(data_pagamento)",
            [$dataInicio, $dataFim]
        );

        // Organiza por data
        $recebimentosPorDia = [];
        foreach ($recebimentos as $r) {
            $recebimentosPorDia[$r['data']] = (float) $r['total'];
        }

        $pagamentosPorDia = [];
        foreach ($pagamentos as $p) {
            $pagamentosPorDia[$p['data']] = (float) $p['total'];
        }

        // Unifica datas
        $todasDatas = array_unique(array_merge(
            array_keys($recebimentosPorDia),
            array_keys($pagamentosPorDia)
        ));
        sort($todasDatas);

        $labels = [];
        $datasetsRecebimentos = [];
        $datasetsPagamentos = [];

        foreach ($todasDatas as $data) {
            $labels[] = date('d/m', strtotime($data));
            $datasetsRecebimentos[] = $recebimentosPorDia[$data] ?? 0;
            $datasetsPagamentos[] = $pagamentosPorDia[$data] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Recebimentos',
                    'data' => $datasetsRecebimentos,
                    'borderColor' => '#27ae60',
                    'backgroundColor' => 'rgba(39, 174, 96, 0.1)'
                ],
                [
                    'label' => 'Pagamentos',
                    'data' => $datasetsPagamentos,
                    'borderColor' => '#e74c3c',
                    'backgroundColor' => 'rgba(231, 76, 60, 0.1)'
                ]
            ]
        ];
    }

    private function financeiro_pagar_tabela(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;
        $apenasPendentes = $config['apenas_pendentes'] ?? true;

        $where = "deletado_em IS NULL";
        if ($apenasPendentes) {
            $where .= " AND data_pagamento IS NULL";
        }

        $pagamentos = $this->db->buscarTodos(
            "SELECT
                id,
                descricao,
                valor,
                data_vencimento,
                data_pagamento
             FROM pagamentos
             WHERE {$where}
             ORDER BY data_vencimento ASC
             LIMIT ?",
            [$limite]
        );

        $linhas = [];
        foreach ($pagamentos as $item) {
            $status = $item['data_pagamento'] ? 'Pago' : 'Pendente';
            $vencimento = date('d/m/Y', strtotime($item['data_vencimento']));

            $linhas[] = [
                $item['descricao'],
                'R$ ' . number_format($item['valor'], 2, ',', '.'),
                $vencimento,
                $status
            ];
        }

        return [
            'colunas' => ['Descrição', 'Valor', 'Vencimento', 'Status'],
            'linhas' => $linhas
        ];
    }

    private function financeiro_receber_tabela(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;
        $apenasPendentes = $config['apenas_pendentes'] ?? true;

        $where = "deletado_em IS NULL";
        if ($apenasPendentes) {
            $where .= " AND data_recebimento IS NULL";
        }

        $recebimentos = $this->db->buscarTodos(
            "SELECT
                id,
                descricao,
                valor,
                data_vencimento,
                data_recebimento
             FROM recebimentos
             WHERE {$where}
             ORDER BY data_vencimento ASC
             LIMIT ?",
            [$limite]
        );

        $linhas = [];
        foreach ($recebimentos as $item) {
            $status = $item['data_recebimento'] ? 'Recebido' : 'Pendente';
            $vencimento = date('d/m/Y', strtotime($item['data_vencimento']));

            $linhas[] = [
                $item['descricao'],
                'R$ ' . number_format($item['valor'], 2, ',', '.'),
                $vencimento,
                $status
            ];
        }

        return [
            'colunas' => ['Descrição', 'Valor', 'Vencimento', 'Status'],
            'linhas' => $linhas
        ];
    }

    private function financeiro_pagamentos_hoje(int $colaboradorId, array $config): array
    {
        $hoje = date('Y-m-d');

        $pagamentos = $this->db->buscarTodos(
            "SELECT
                id,
                descricao,
                valor,
                data_pagamento
             FROM pagamentos
             WHERE DATE(data_pagamento) = ?
               AND deletado_em IS NULL
             ORDER BY data_pagamento DESC
             LIMIT 10",
            [$hoje]
        );

        $items = [];
        foreach ($pagamentos as $pag) {
            $items[] = [
                'id' => $pag['id'],
                'titulo' => $pag['descricao'],
                'subtitulo' => date('H:i', strtotime($pag['data_pagamento'])),
                'valor' => 'R$ ' . number_format($pag['valor'], 2, ',', '.'),
                'cor' => '#e74c3c'
            ];
        }

        return ['items' => $items];
    }

    private function financeiro_recebimentos_hoje(int $colaboradorId, array $config): array
    {
        $hoje = date('Y-m-d');

        $recebimentos = $this->db->buscarTodos(
            "SELECT
                id,
                descricao,
                valor,
                data_recebimento
             FROM recebimentos
             WHERE DATE(data_recebimento) = ?
               AND deletado_em IS NULL
             ORDER BY data_recebimento DESC
             LIMIT 10",
            [$hoje]
        );

        $items = [];
        foreach ($recebimentos as $rec) {
            $items[] = [
                'id' => $rec['id'],
                'titulo' => $rec['descricao'],
                'subtitulo' => date('H:i', strtotime($rec['data_recebimento'])),
                'valor' => 'R$ ' . number_format($rec['valor'], 2, ',', '.'),
                'cor' => '#27ae60'
            ];
        }

        return ['items' => $items];
    }

    private function financeiro_resultado_barra(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $recebimentos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM recebimentos
             WHERE data_recebimento BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        $pagamentos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM pagamentos
             WHERE data_pagamento BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        return [
            'labels' => ['Receitas', 'Despesas', 'Resultado'],
            'values' => [
                (float) $recebimentos['total'],
                (float) $pagamentos['total'],
                (float) ($recebimentos['total'] - $pagamentos['total'])
            ]
        ];
    }

    private function financeiro_receber_periodo(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM recebimentos
             WHERE data_vencimento BETWEEN ? AND ?
               AND data_recebimento IS NULL
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Contas a Receber',
            'formato' => 'moeda'
        ];
    }

    private function financeiro_pagar_periodo(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM pagamentos
             WHERE data_vencimento BETWEEN ? AND ?
               AND data_pagamento IS NULL
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Contas a Pagar',
            'formato' => 'moeda'
        ];
    }

    private function financeiro_recebimentos_vencidos(int $colaboradorId, array $config): array
    {
        $hoje = date('Y-m-d');

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM recebimentos
             WHERE data_vencimento < ?
               AND data_recebimento IS NULL
               AND deletado_em IS NULL",
            [$hoje]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Recebimentos Vencidos',
            'formato' => 'moeda',
            'cor' => '#e67e22'
        ];
    }

    private function financeiro_pagamentos_vencidos(int $colaboradorId, array $config): array
    {
        $hoje = date('Y-m-d');

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM pagamentos
             WHERE data_vencimento < ?
               AND data_pagamento IS NULL
               AND deletado_em IS NULL",
            [$hoje]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Pagamentos Vencidos',
            'formato' => 'moeda',
            'cor' => '#c0392b'
        ];
    }

    private function financeiro_contas_bancarias(int $colaboradorId, array $config): array
    {
        // Por enquanto retorna vazio - TODO: Implementar quando houver tabela contas_bancarias
        return ['cards' => []];
    }

    private function financeiro_despesas_categoria(int $colaboradorId, array $config): array
    {
        // Por enquanto retorna vazio - TODO: Implementar quando houver campo categoria em pagamentos
        return ['labels' => [], 'values' => []];
    }

    /**
     * FROTA - Dados da frota
     */
    private function obterDadosFrota(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'frota_total_veiculos' => $this->frota_ativos($colaboradorId, $config),
            'frota_manutencoes_pendentes' => $this->frota_manutencoes_pendentes($colaboradorId, $config),
            'frota_custo_mensal' => $this->frota_custo_total($colaboradorId, $config),
            'frota_abastecimentos' => $this->frota_consumo_linha($colaboradorId, $config),
            'frota_veiculos_status' => $this->frota_veiculos_status($colaboradorId, $config),
            'frota_ultimas_manutencoes' => $this->frota_ultimas_manutencoes($colaboradorId, $config),
            'frota_km_rodados' => $this->frota_km_total($colaboradorId, $config),
            default => ['labels' => [], 'values' => []]
        };
    }

    private function frota_ativos(int $colaboradorId, array $config): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM frotas
             WHERE status = 'ativo' AND deletado_em IS NULL"
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Veículos Ativos',
            'formato' => 'numero',
            'icone' => 'fa-truck'
        ];
    }

    private function frota_km_total(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(km_percorrido), 0) as total
             FROM frotas_abastecimentos
             WHERE data_abastecimento BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Km Rodados',
            'formato' => 'numero',
            'sufixo' => ' km'
        ];
    }

    private function frota_consumo_linha(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $consumo = $this->db->buscarTodos(
            "SELECT DATE(data_abastecimento) as data, SUM(litros) as total_litros
             FROM frotas_abastecimentos
             WHERE data_abastecimento BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(data_abastecimento)
             ORDER BY data ASC",
            [$dataInicio, $dataFim]
        );

        $labels = [];
        $values = [];

        foreach ($consumo as $c) {
            $labels[] = date('d/m', strtotime($c['data']));
            $values[] = (float) $c['total_litros'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function frota_custo_veiculo_tabela(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $custos = $this->db->buscarTodos(
            "SELECT
                f.placa,
                f.modelo,
                COUNT(a.id) as qtd_abastecimentos,
                COALESCE(SUM(a.valor_total), 0) as custo_total
             FROM frotas f
             LEFT JOIN frotas_abastecimentos a ON f.id = a.frota_id
                AND a.data_abastecimento BETWEEN ? AND ?
                AND a.deletado_em IS NULL
             WHERE f.deletado_em IS NULL
             GROUP BY f.id, f.placa, f.modelo
             ORDER BY custo_total DESC
             LIMIT 10",
            [$dataInicio, $dataFim]
        );

        $linhas = [];
        foreach ($custos as $item) {
            $linhas[] = [
                $item['placa'],
                $item['modelo'],
                $item['qtd_abastecimentos'],
                'R$ ' . number_format($item['custo_total'], 2, ',', '.')
            ];
        }

        return [
            'colunas' => ['Placa', 'Modelo', 'Abastecimentos', 'Custo Total'],
            'linhas' => $linhas
        ];
    }

    private function frota_alertas_lista(int $colaboradorId, array $config): array
    {
        $diasAntecedencia = $config['dias_antecedencia'] ?? 30;
        $dataLimite = date('Y-m-d', strtotime("+{$diasAntecedencia} days"));

        // Por enquanto retorna vazio, pois não temos campo de manutenção programada
        // TODO: Implementar quando houver tabela de manutenções
        return ['items' => []];
    }

    private function frota_abastecimentos_timeline(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $abastecimentos = $this->db->buscarTodos(
            "SELECT
                a.id,
                a.data_abastecimento,
                a.litros,
                a.valor_total,
                a.km_atual,
                f.placa,
                f.modelo
             FROM frotas_abastecimentos a
             INNER JOIN frotas f ON a.frota_id = f.id
             WHERE a.deletado_em IS NULL
             ORDER BY a.data_abastecimento DESC
             LIMIT ?",
            [$limite]
        );

        $items = [];
        foreach ($abastecimentos as $a) {
            $items[] = [
                'id' => $a['id'],
                'titulo' => $a['placa'] . ' - ' . $a['modelo'],
                'subtitulo' => $a['litros'] . 'L • ' . number_format($a['km_atual'], 0, ',', '.') . ' km',
                'valor' => 'R$ ' . number_format($a['valor_total'], 2, ',', '.'),
                'data' => date('d/m/Y H:i', strtotime($a['data_abastecimento']))
            ];
        }

        return ['items' => $items];
    }

    private function frota_custo_total(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM frotas_abastecimentos
             WHERE data_abastecimento BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Custo Total Frota',
            'formato' => 'moeda'
        ];
    }

    private function frota_media_consumo(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT
                COALESCE(SUM(km_percorrido), 0) as total_km,
                COALESCE(SUM(litros), 0) as total_litros
             FROM frotas_abastecimentos
             WHERE data_abastecimento BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim]
        );

        $mediaKmL = $resultado['total_litros'] > 0
            ? $resultado['total_km'] / $resultado['total_litros']
            : 0;

        return [
            'valor' => number_format($mediaKmL, 2, '.', ''),
            'label' => 'Média Km/L',
            'formato' => 'numero',
            'sufixo' => ' km/l'
        ];
    }

    private function frota_manutencoes_pendentes(int $colaboradorId, array $config): array
    {
        // Por enquanto retorna 0 - TODO: Implementar quando houver tabela de manutenções
        return [
            'valor' => 0,
            'label' => 'Manutenções Pendentes',
            'formato' => 'numero'
        ];
    }

    private function frota_veiculos_status(int $colaboradorId, array $config): array
    {
        $veiculos = $this->db->buscarTodos(
            "SELECT status, COUNT(*) as total
             FROM frotas
             WHERE deletado_em IS NULL
             GROUP BY status"
        );

        $labels = [];
        $values = [];

        foreach ($veiculos as $v) {
            $labels[] = ucfirst($v['status']);
            $values[] = (int) $v['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function frota_ultimas_manutencoes(int $colaboradorId, array $config): array
    {
        // Por enquanto retorna vazio - TODO: Implementar quando houver tabela de manutenções
        return ['items' => []];
    }

    /**
     * CLIENTES - Dados de clientes
     */
    private function obterDadosClientes(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'clientes_total_ativos' => $this->clientes_total($colaboradorId, $config),
            'clientes_novos_mes' => $this->clientes_novos($colaboradorId, $config),
            'clientes_evolucao' => $this->clientes_evolucao_linha($colaboradorId, $config),
            'clientes_por_cidade' => $this->clientes_por_cidade($colaboradorId, $config),
            'clientes_top_compradores' => $this->clientes_top_tabela($colaboradorId, $config),
            'clientes_inativos' => $this->clientes_inativos($colaboradorId, $config),
            default => ['labels' => [], 'values' => []]
        };
    }

    private function clientes_total(int $colaboradorId, array $config): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM clientes WHERE ativo = 1 AND deletado_em IS NULL"
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Clientes Ativos',
            'formato' => 'numero',
            'icone' => 'fa-users'
        ];
    }

    private function clientes_novos(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM clientes
             WHERE cadastrado_em BETWEEN ? AND ?
               AND deletado_em IS NULL",
            [$dataInicio, $dataFim . ' 23:59:59']
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Novos Clientes',
            'formato' => 'numero',
            'subtitulo' => ucfirst(str_replace('_', ' ', $periodo))
        ];
    }

    private function clientes_top_tabela(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 10;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $clientes = $this->db->buscarTodos(
            "SELECT
                c.nome,
                COUNT(v.id) as qtd_compras,
                COALESCE(SUM(v.valor_total), 0) as total_gasto
             FROM clientes c
             INNER JOIN vendas v ON c.id = v.cliente_id
             WHERE v.criado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
             GROUP BY c.id, c.nome
             ORDER BY total_gasto DESC
             LIMIT ?",
            [$dataInicio, $dataFim . ' 23:59:59', $limite]
        );

        $linhas = [];
        foreach ($clientes as $index => $cliente) {
            $linhas[] = [
                $index + 1,
                $cliente['nome'],
                $cliente['qtd_compras'],
                'R$ ' . number_format($cliente['total_gasto'], 2, ',', '.')
            ];
        }

        return [
            'colunas' => ['#', 'Cliente', 'Compras', 'Total Gasto'],
            'linhas' => $linhas
        ];
    }

    private function clientes_evolucao_linha(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'ano_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $evolucao = $this->db->buscarTodos(
            "SELECT DATE(cadastrado_em) as data, COUNT(*) as total
             FROM clientes
             WHERE cadastrado_em BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(cadastrado_em)
             ORDER BY data ASC",
            [$dataInicio, $dataFim . ' 23:59:59']
        );

        $labels = [];
        $values = [];
        $acumulado = 0;

        foreach ($evolucao as $e) {
            $acumulado += (int) $e['total'];
            $labels[] = date('d/m', strtotime($e['data']));
            $values[] = $acumulado;
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function clientes_ultimos_lista(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $clientes = $this->db->buscarTodos(
            "SELECT
                id,
                nome,
                email,
                telefone,
                cadastrado_em
             FROM clientes
             WHERE deletado_em IS NULL
             ORDER BY cadastrado_em DESC
             LIMIT ?",
            [$limite]
        );

        $items = [];
        foreach ($clientes as $cliente) {
            $items[] = [
                'id' => $cliente['id'],
                'titulo' => $cliente['nome'],
                'subtitulo' => $cliente['email'] ?? $cliente['telefone'] ?? 'Sem contato',
                'data' => date('d/m/Y H:i', strtotime($cliente['cadastrado_em']))
            ];
        }

        return ['items' => $items];
    }

    private function clientes_por_cidade(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $cidades = $this->db->buscarTodos(
            "SELECT
                ci.nome as cidade,
                COUNT(DISTINCT c.id) as total
             FROM clientes c
             LEFT JOIN clientes_enderecos ce ON c.id = ce.cliente_id
             LEFT JOIN cidades ci ON ce.cidade_id = ci.id
             WHERE c.deletado_em IS NULL
             GROUP BY ci.id, ci.nome
             ORDER BY total DESC
             LIMIT ?",
            [$limite]
        );

        $labels = [];
        $values = [];

        foreach ($cidades as $cidade) {
            $labels[] = $cidade['cidade'] ?? 'Não informada';
            $values[] = (int) $cidade['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function clientes_inativos(int $colaboradorId, array $config): array
    {
        $diasInatividade = $config['dias_inatividade'] ?? 90;
        $dataLimite = date('Y-m-d', strtotime("-{$diasInatividade} days"));

        $resultado = $this->db->buscarUm(
            "SELECT COUNT(DISTINCT c.id) as total
             FROM clientes c
             LEFT JOIN vendas v ON c.id = v.cliente_id
                AND v.criado_em >= ?
                AND v.deletado_em IS NULL
             WHERE c.deletado_em IS NULL
               AND v.id IS NULL",
            [$dataLimite]
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Clientes Inativos',
            'formato' => 'numero',
            'subtitulo' => 'Sem compras em ' . $diasInatividade . ' dias'
        ];
    }

    /**
     * PRODUTOS - Dados de produtos
     */
    private function obterDadosProdutos(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'produtos_total_cadastrados' => $this->produtos_total($colaboradorId, $config),
            'produtos_estoque_baixo' => $this->produtos_estoque_baixo_lista($colaboradorId, $config),
            'produtos_valor_estoque' => $this->produtos_valor_estoque($colaboradorId, $config),
            'produtos_mais_vendidos' => $this->produtos_mais_vendidos_barra($colaboradorId, $config),
            'produtos_por_grupo' => $this->produtos_por_grupo($colaboradorId, $config),
            'produtos_margem_lucro' => $this->produtos_margem_lucro($colaboradorId, $config),
            default => ['labels' => [], 'values' => []]
        };
    }

    private function produtos_total(int $colaboradorId, array $config): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM produtos WHERE deletado_em IS NULL"
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Total de Produtos',
            'formato' => 'numero',
            'icone' => 'fa-box'
        ];
    }

    private function produtos_estoque_tabela(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 20;

        // Nota: Assumindo que a tabela produtos tem um campo 'estoque_atual'
        // Se não houver, retorna lista simples de produtos
        $produtos = $this->db->buscarTodos(
            "SELECT
                id,
                nome,
                codigo_sku,
                preco_venda
             FROM produtos
             WHERE deletado_em IS NULL
             ORDER BY nome ASC
             LIMIT ?",
            [$limite]
        );

        $linhas = [];
        foreach ($produtos as $produto) {
            $linhas[] = [
                $produto['codigo_sku'] ?? '-',
                $produto['nome'],
                'R$ ' . number_format($produto['preco_venda'] ?? 0, 2, ',', '.')
            ];
        }

        return [
            'colunas' => ['SKU', 'Produto', 'Preço'],
            'linhas' => $linhas
        ];
    }

    private function produtos_estoque_baixo_lista(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        // Retorna lista vazia por enquanto
        // TODO: Implementar quando houver controle de estoque
        return ['items' => []];
    }

    private function produtos_mais_vendidos_barra(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 10;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $produtos = $this->db->buscarTodos(
            "SELECT
                p.nome,
                SUM(vi.quantidade) as total_vendido
             FROM vendas_itens vi
             INNER JOIN vendas v ON vi.venda_id = v.id
             INNER JOIN produtos p ON vi.produto_id = p.id
             WHERE v.criado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
               AND p.deletado_em IS NULL
             GROUP BY p.id, p.nome
             ORDER BY total_vendido DESC
             LIMIT ?",
            [$dataInicio, $dataFim . ' 23:59:59', $limite]
        );

        $labels = [];
        $values = [];

        foreach ($produtos as $produto) {
            $labels[] = $produto['nome'];
            $values[] = (int) $produto['total_vendido'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function produtos_valor_estoque(int $colaboradorId, array $config): array
    {
        // Por enquanto retorna 0
        // TODO: Implementar quando houver controle de estoque
        return [
            'valor' => 0,
            'label' => 'Valor em Estoque',
            'formato' => 'moeda'
        ];
    }

    private function produtos_por_grupo(int $colaboradorId, array $config): array
    {
        $grupos = $this->db->buscarTodos(
            "SELECT
                g.nome as grupo,
                COUNT(p.id) as total
             FROM produtos p
             INNER JOIN grupos_produtos g ON p.grupo_produto_id = g.id
             WHERE p.deletado_em IS NULL
             GROUP BY g.id, g.nome
             ORDER BY total DESC
             LIMIT 10"
        );

        $labels = [];
        $values = [];

        foreach ($grupos as $grupo) {
            $labels[] = $grupo['grupo'];
            $values[] = (int) $grupo['total'];
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    private function produtos_margem_lucro(int $colaboradorId, array $config): array
    {
        // Por enquanto retorna vazio - TODO: Implementar quando houver campos de custo e margem
        return [
            'colunas' => ['Produto', 'Custo', 'Venda', 'Margem'],
            'linhas' => []
        ];
    }

    /**
     * GERAL - Dados gerais
     */
    private function obterDadosGeral(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'geral_resumo_dashboard' => $this->geral_resumo_dia($colaboradorId, $config),
            'geral_avisos' => $this->geral_avisos($colaboradorId, $config),
            default => ['items' => []]
        };
    }

    private function geral_resumo_dia(int $colaboradorId, array $config): array
    {
        $hoje = date('Y-m-d');

        // Vendas hoje
        $vendas = $this->db->buscarUm(
            "SELECT COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as total
             FROM vendas WHERE DATE(criado_em) = ? AND deletado_em IS NULL",
            [$hoje]
        );

        // Clientes novos
        $clientes = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM clientes
             WHERE DATE(cadastrado_em) = ? AND deletado_em IS NULL",
            [$hoje]
        );

        return [
            'cards' => [
                [
                    'titulo' => 'Vendas Hoje',
                    'valor' => 'R$ ' . number_format($vendas['total'], 2, ',', '.'),
                    'subtitulo' => $vendas['qtd'] . ' vendas',
                    'icone' => 'fa-shopping-cart',
                    'cor' => '#e74c3c'
                ],
                [
                    'titulo' => 'Novos Clientes',
                    'valor' => $clientes['total'],
                    'subtitulo' => 'Cadastrados hoje',
                    'icone' => 'fa-user-plus',
                    'cor' => '#3498db'
                ]
            ]
        ];
    }

    private function geral_atividade_feed(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 20;

        // Retorna atividades recentes do sistema
        // TODO: Implementar quando houver tabela de logs/auditoria
        return ['items' => []];
    }

    private function geral_notificacoes_lista(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        // Retorna notificações pendentes
        // TODO: Implementar quando houver tabela de notificações
        return ['items' => []];
    }

    private function geral_avisos(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        // Retorna avisos e alertas
        // TODO: Implementar quando houver tabela de avisos
        return ['items' => []];
    }

    /**
     * MÉTODOS AUXILIARES
     */
    private function calcularPeriodo(string $periodo): array
    {
        return match ($periodo) {
            'hoje' => [date('Y-m-d'), date('Y-m-d')],
            'ontem' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'semana_atual' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            'mes_atual' => [date('Y-m-01'), date('Y-m-t')],
            'mes_passado' => [
                date('Y-m-01', strtotime('first day of last month')),
                date('Y-m-t', strtotime('last day of last month'))
            ],
            'ano_atual' => [date('Y-01-01'), date('Y-12-31')],
            'ultimos_7_dias' => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')],
            'ultimos_30_dias' => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
            'ultimos_90_dias' => [date('Y-m-d', strtotime('-90 days')), date('Y-m-d')],
            default => [date('Y-m-01'), date('Y-m-t')]
        };
    }

    private function gerarChaveCache(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): string
    {
        return md5($widgetCodigo . $colaboradorId . json_encode($config) . json_encode($filtros));
    }

    private function temCache(string $chave): bool
    {
        if (!isset($this->cache[$chave])) {
            return false;
        }

        if ($this->cache[$chave]['expira_em'] < time()) {
            unset($this->cache[$chave]);
            return false;
        }

        return true;
    }

    private function obterCache(string $chave): array
    {
        return $this->cache[$chave]['dados'];
    }

    private function salvarCache(string $chave, array $dados, int $ttl): void
    {
        $this->cache[$chave] = [
            'dados' => $dados,
            'expira_em' => time() + $ttl
        ];
    }
}
