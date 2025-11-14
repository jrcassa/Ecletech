<?php

namespace App\Services\Dashboard;

use App\Core\BancoDados;

class ServiceWidgetDados
{
    private BancoDados $db;
    private array $cache = [];

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    public function obterDados(string $widgetCodigo, int $colaboradorId, array $config, array $filtros = []): array
    {
        $cacheKey = $this->gerarChaveCache($widgetCodigo, $colaboradorId, $config, $filtros);
        if ($this->temCache($cacheKey)) {
            return $this->obterCache($cacheKey);
        }

        $partes = explode('_', $widgetCodigo);
        $categoria = $partes[0];

        $dados = match ($categoria) {
            'vendas' => $this->obterDadosVendas($widgetCodigo, $colaboradorId, $config, $filtros),
            'financeiro' => $this->obterDadosFinanceiro($widgetCodigo, $colaboradorId, $config, $filtros),
            'frota' => $this->obterDadosFrota($widgetCodigo, $colaboradorId, $config, $filtros),
            'clientes' => $this->obterDadosClientes($widgetCodigo, $colaboradorId, $config, $filtros),
            'produtos' => $this->obterDadosProdutos($widgetCodigo, $colaboradorId, $config, $filtros),
            'geral' => $this->obterDadosGeral($widgetCodigo, $colaboradorId, $config, $filtros),
            default => throw new \Exception("Categoria de widget não suportada: {$categoria}")
        };

        $this->salvarCache($cacheKey, $dados, 300);
        return $dados;
    }

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
            "SELECT DATE(cadastrado_em) as data, SUM(valor_total) as total
             FROM vendas
             WHERE cadastrado_em BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(cadastrado_em)
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
             WHERE cadastrado_em BETWEEN ? AND ?
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
             WHERE DATE(cadastrado_em) = ?
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
             WHERE cadastrado_em BETWEEN ? AND ?
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

    private function vendas_ultimas(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $vendas = $this->db->buscarTodos(
            "SELECT
                v.id,
                v.codigo,
                v.valor_total,
                v.cadastrado_em,
                v.nome_cliente
             FROM vendas v
             WHERE v.deletado_em IS NULL
             ORDER BY v.cadastrado_em DESC
             LIMIT ?",
            [$limite]
        );

        $items = [];
        foreach ($vendas as $venda) {
            $items[] = [
                'id' => $venda['id'],
                'titulo' => 'Venda #' . $venda['codigo'],
                'subtitulo' => $venda['nome_cliente'] ?? 'Cliente não informado',
                'valor' => 'R$ ' . number_format($venda['valor_total'], 2, ',', '.'),
                'data' => date('d/m/Y H:i', strtotime($venda['cadastrado_em']))
            ];
        }

        return ['items' => $items];
    }

    private function vendas_por_produto_donut(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 8;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $produtos = $this->db->buscarTodos(
            "SELECT
            vi.nome_produto as produto,
            SUM(vi.valor_total) as total
         FROM vendas_itens vi
         INNER JOIN vendas v ON vi.venda_id = v.id
         WHERE v.cadastrado_em BETWEEN ? AND ?
           AND v.deletado_em IS NULL
           AND vi.tipo = 'produto'
         GROUP BY vi.nome_produto
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

        $vendedores = $this->db->buscarTodos(
            "SELECT
                v.nome_vendedor as vendedor,
                COUNT(v.id) as quantidade,
                SUM(v.valor_total) as total
             FROM vendas v
             WHERE v.cadastrado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
               AND v.vendedor_id IS NOT NULL
             GROUP BY v.vendedor_id, v.nome_vendedor
             ORDER BY total DESC
             LIMIT 10",
            [$dataInicio, $dataFim . ' 23:59:59']
        );

        $labels = [];
        $values = [];

        foreach ($vendedores as $vendedor) {
            $labels[] = $vendedor['vendedor'] ?? 'Vendedor';
            $values[] = (float) $vendedor['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function vendas_metas(int $colaboradorId, array $config): array
    {
        return ['labels' => ['Realizado', 'Meta'], 'values' => [0, 0]];
    }

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
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM recebimentos
             WHERE liquidado = 1 AND deletado_em IS NULL",
            []
        );

        $pagamentos = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM pagamentos
             WHERE liquidado = 1 AND deletado_em IS NULL",
            []
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

        $recebimentos = $this->db->buscarTodos(
            "SELECT DATE(data_liquidacao) as data, SUM(valor_total) as total
             FROM recebimentos
             WHERE data_liquidacao BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(data_liquidacao)",
            [$dataInicio, $dataFim]
        );

        $pagamentos = $this->db->buscarTodos(
            "SELECT DATE(data_liquidacao) as data, SUM(valor_total) as total
             FROM pagamentos
             WHERE data_liquidacao BETWEEN ? AND ?
               AND deletado_em IS NULL
             GROUP BY DATE(data_liquidacao)",
            [$dataInicio, $dataFim]
        );

        $recebimentosPorDia = [];
        foreach ($recebimentos as $r) {
            $recebimentosPorDia[$r['data']] = (float) $r['total'];
        }

        $pagamentosPorDia = [];
        foreach ($pagamentos as $p) {
            $pagamentosPorDia[$p['data']] = (float) $p['total'];
        }

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

    private function financeiro_receber_periodo(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM recebimentos
             WHERE data_vencimento BETWEEN ? AND ?
               AND liquidado = 0
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
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM pagamentos
             WHERE data_vencimento BETWEEN ? AND ?
               AND liquidado = 0
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
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM recebimentos
             WHERE data_vencimento < ?
               AND liquidado = 0
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
            "SELECT COALESCE(SUM(valor_total), 0) as total
             FROM pagamentos
             WHERE data_vencimento < ?
               AND liquidado = 0
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
        $contas = $this->db->buscarTodos(
            "SELECT
                nome,
                banco_nome,
                saldo_inicial
             FROM contas_bancarias
             WHERE ativo = 1 AND deletado_em IS NULL
             ORDER BY nome ASC
             LIMIT 10",
            []
        );

        $cards = [];
        foreach ($contas as $conta) {
            $cards[] = [
                'titulo' => $conta['nome'],
                'subtitulo' => $conta['banco_nome'] ?? 'Banco não informado',
                'valor' => 'R$ ' . number_format($conta['saldo_inicial'], 2, ',', '.'),
                'icone' => 'fa-university'
            ];
        }

        return ['cards' => $cards];
    }

    private function financeiro_despesas_categoria(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $despesas = $this->db->buscarTodos(
            "SELECT
                pc.nome as categoria,
                SUM(p.valor_total) as total
             FROM pagamentos p
             INNER JOIN plano_de_contas pc ON p.plano_contas_id = pc.id
             WHERE p.data_liquidacao BETWEEN ? AND ?
               AND p.deletado_em IS NULL
             GROUP BY pc.id, pc.nome
             ORDER BY total DESC
             LIMIT 10",
            [$dataInicio, $dataFim]
        );

        $labels = [];
        $values = [];

        foreach ($despesas as $despesa) {
            $labels[] = $despesa['categoria'];
            $values[] = (float) $despesa['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

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
             WHERE status = 'ativo' AND deletado_em IS NULL",
            []
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
            "SELECT COALESCE(SUM(km), 0) as total
             FROM frotas_abastecimentos
             WHERE data_abastecimento BETWEEN ? AND ?
               AND deletado_em IS NULL
               AND status = 'abastecido'",
            [$dataInicio, $dataFim]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Km Registrados',
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
               AND status = 'abastecido'
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

        return ['labels' => $labels, 'values' => $values];
    }

    private function frota_custo_total(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM frotas_abastecimentos
             WHERE data_abastecimento BETWEEN ? AND ?
               AND deletado_em IS NULL
               AND status = 'abastecido'",
            [$dataInicio, $dataFim]
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Custo Total Frota',
            'formato' => 'moeda'
        ];
    }

    private function frota_manutencoes_pendentes(int $colaboradorId, array $config): array
    {
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
             GROUP BY status",
            []
        );

        $labels = [];
        $values = [];

        foreach ($veiculos as $v) {
            $labels[] = ucfirst($v['status']);
            $values[] = (int) $v['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function frota_ultimas_manutencoes(int $colaboradorId, array $config): array
    {
        return ['items' => []];
    }

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
            "SELECT COUNT(*) as total FROM clientes WHERE ativo = 1 AND deletado_em IS NULL",
            []
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
                v.nome_cliente as nome,
                COUNT(v.id) as qtd_compras,
                COALESCE(SUM(v.valor_total), 0) as total_gasto
             FROM vendas v
             WHERE v.cadastrado_em BETWEEN ? AND ?
               AND v.deletado_em IS NULL
               AND v.cliente_id IS NOT NULL
             GROUP BY v.cliente_id, v.nome_cliente
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

        return ['labels' => $labels, 'values' => $values];
    }

    private function clientes_por_cidade(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $cidades = $this->db->buscarTodos(
            "SELECT
                ci.nome as cidade,
                COUNT(DISTINCT ce.cliente_id) as total
             FROM clientes_enderecos ce
             LEFT JOIN cidades ci ON ce.cidade_id = ci.id
             INNER JOIN clientes c ON ce.cliente_id = c.id AND c.deletado_em IS NULL
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

        return ['labels' => $labels, 'values' => $values];
    }

    private function clientes_inativos(int $colaboradorId, array $config): array
    {
        $diasInatividade = $config['dias_inatividade'] ?? 90;
        $dataLimite = date('Y-m-d', strtotime("-{$diasInatividade} days"));

        $resultado = $this->db->buscarUm(
            "SELECT COUNT(DISTINCT c.id) as total
             FROM clientes c
             LEFT JOIN vendas v ON c.id = v.cliente_id
                AND v.cadastrado_em >= ?
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
            "SELECT COUNT(*) as total FROM produtos WHERE deleted_at IS NULL AND ativo = 1",
            []
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Total de Produtos',
            'formato' => 'numero',
            'icone' => 'fa-box'
        ];
    }

    private function produtos_estoque_baixo_lista(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;
        $estoqueMinimo = $config['estoque_minimo'] ?? 10;

        $produtos = $this->db->buscarTodos(
            "SELECT
            id,
            nome,
            codigo_interno,
            estoque
         FROM produtos
         WHERE deleted_at IS NULL
           AND ativo = 1
           AND movimenta_estoque = 1
           AND estoque < ?
         ORDER BY estoque ASC
         LIMIT ?",
            [$estoqueMinimo, $limite]
        );

        $items = [];
        foreach ($produtos as $produto) {
            $items[] = [
                'id' => $produto['id'],
                'titulo' => $produto['nome'],
                'subtitulo' => 'Código: ' . ($produto['codigo_interno'] ?? '-'),
                'valor' => number_format($produto['estoque'], 2, ',', '.') . ' un',
                'cor' => '#e74c3c'
            ];
        }

        return ['items' => $items];
    }

    private function produtos_mais_vendidos_barra(int $colaboradorId, array $config): array
    {
        $periodo = $config['periodo'] ?? 'mes_atual';
        $limite = $config['limite'] ?? 10;
        [$dataInicio, $dataFim] = $this->calcularPeriodo($periodo);

        $produtos = $this->db->buscarTodos(
            "SELECT
            vi.nome_produto as nome,
            SUM(vi.quantidade) as total_vendido
         FROM vendas_itens vi
         INNER JOIN vendas v ON vi.venda_id = v.id
         WHERE v.cadastrado_em BETWEEN ? AND ?
           AND v.deletado_em IS NULL
           AND vi.tipo = 'produto'
         GROUP BY vi.produto_id, vi.nome_produto
         ORDER BY total_vendido DESC
         LIMIT ?",
            [$dataInicio, $dataFim . ' 23:59:59', $limite]
        );

        $labels = [];
        $values = [];

        foreach ($produtos as $produto) {
            $labels[] = $produto['nome'];
            $values[] = (float) $produto['total_vendido'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function produtos_valor_estoque(int $colaboradorId, array $config): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT COALESCE(SUM(estoque * valor_custo), 0) as total
         FROM produtos
         WHERE deleted_at IS NULL
           AND ativo = 1
           AND movimenta_estoque = 1",
            []
        );

        return [
            'valor' => (float) $resultado['total'],
            'label' => 'Valor em Estoque',
            'formato' => 'moeda'
        ];
    }

    private function produtos_por_grupo(int $colaboradorId, array $config): array
    {
        $grupos = $this->db->buscarTodos(
            "SELECT
            gp.nome as grupo,
            COUNT(p.id) as total
         FROM produtos p
         INNER JOIN grupos_produtos gp ON p.grupo_id = gp.id
         WHERE p.deleted_at IS NULL
           AND gp.deletado_em IS NULL
         GROUP BY gp.id, gp.nome
         ORDER BY total DESC
         LIMIT 10",
            []
        );

        $labels = [];
        $values = [];

        foreach ($grupos as $grupo) {
            $labels[] = $grupo['grupo'];
            $values[] = (int) $grupo['total'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function produtos_margem_lucro(int $colaboradorId, array $config): array
    {
        $limite = $config['limite'] ?? 10;

        $produtos = $this->db->buscarTodos(
            "SELECT
            nome,
            valor_custo,
            valor_venda,
            CASE
                WHEN valor_custo > 0 THEN ((valor_venda - valor_custo) / valor_custo * 100)
                ELSE 0
            END as margem
         FROM produtos
         WHERE deleted_at IS NULL
           AND ativo = 1
           AND valor_custo > 0
           AND valor_venda > 0
         ORDER BY margem DESC
         LIMIT ?",
            [$limite]
        );

        $linhas = [];
        foreach ($produtos as $produto) {
            $linhas[] = [
                $produto['nome'],
                'R$ ' . number_format($produto['valor_custo'], 2, ',', '.'),
                'R$ ' . number_format($produto['valor_venda'], 2, ',', '.'),
                number_format($produto['margem'], 2, ',', '.') . '%'
            ];
        }

        return [
            'colunas' => ['Produto', 'Custo', 'Venda', 'Margem'],
            'linhas' => $linhas
        ];
    }

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

        $vendas = $this->db->buscarUm(
            "SELECT COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as total
             FROM vendas WHERE DATE(cadastrado_em) = ? AND deletado_em IS NULL",
            [$hoje]
        );

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

    private function geral_avisos(int $colaboradorId, array $config): array
    {
        return ['items' => []];
    }

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
