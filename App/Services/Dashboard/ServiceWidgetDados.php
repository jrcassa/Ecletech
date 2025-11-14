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
            'vendas_mes_linha', 'vendas_mes_barra', 'vendas_periodo_area' => $this->vendas_evolucao_periodo($colaboradorId, $config),
            'vendas_valor_total_card' => $this->vendas_valor_total($colaboradorId, $config),
            'vendas_hoje_contador' => $this->vendas_hoje($colaboradorId, $config),
            'vendas_ticket_medio_card' => $this->vendas_ticket_medio($colaboradorId, $config),
            'vendas_top_produtos' => $this->vendas_top_produtos($colaboradorId, $config),
            'vendas_ultimas_lista' => $this->vendas_ultimas($colaboradorId, $config),
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

    /**
     * FINANCEIRO - Dados financeiros
     */
    private function obterDadosFinanceiro(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'financeiro_saldo_card' => $this->financeiro_saldo($colaboradorId, $config),
            'financeiro_fluxo_caixa_linha' => $this->financeiro_fluxo_caixa($colaboradorId, $config),
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

    /**
     * FROTA - Dados da frota
     */
    private function obterDadosFrota(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'frota_ativos_contador' => $this->frota_ativos($colaboradorId, $config),
            'frota_km_contador' => $this->frota_km_total($colaboradorId, $config),
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
             FROM frotas_abastecimento
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

    /**
     * CLIENTES - Dados de clientes
     */
    private function obterDadosClientes(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'clientes_total_card' => $this->clientes_total($colaboradorId, $config),
            'clientes_novos_contador' => $this->clientes_novos($colaboradorId, $config),
            default => ['labels' => [], 'values' => []]
        };
    }

    private function clientes_total(int $colaboradorId, array $config): array
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM clientes WHERE deletado_em IS NULL"
        );

        return [
            'valor' => (int) $resultado['total'],
            'label' => 'Total de Clientes',
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
             WHERE criado_em BETWEEN ? AND ?
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

    /**
     * PRODUTOS - Dados de produtos
     */
    private function obterDadosProdutos(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'produtos_total_contador' => $this->produtos_total($colaboradorId, $config),
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

    /**
     * GERAL - Dados gerais
     */
    private function obterDadosGeral(string $widgetCodigo, int $colaboradorId, array $config, array $filtros): array
    {
        return match ($widgetCodigo) {
            'geral_resumo_dia_cards' => $this->geral_resumo_dia($colaboradorId, $config),
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
             WHERE DATE(criado_em) = ? AND deletado_em IS NULL",
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
