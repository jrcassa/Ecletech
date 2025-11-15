<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Venda
 * Ecletech <-> GestãoClick
 */
class VendaHandler
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Transforma dados do Ecletech para formato GestaoClick
     */
    public function transformarParaExterno(array $venda): array
    {
        $dados = [
            'title' => $venda['titulo'] ?? "Venda #{$venda['id']}",
            'customer_id' => $venda['external_id_cliente'] ?? null,
            'total_value' => (float) ($venda['valor_total'] ?? 0),
            'status' => $this->mapearStatus($venda['status'] ?? 'pendente'),
        ];

        // Data da venda
        if (!empty($venda['data_venda'])) {
            $dados['date'] = $venda['data_venda'];
        } elseif (!empty($venda['created_at'])) {
            $dados['date'] = $venda['created_at'];
        }

        // Desconto
        if (isset($venda['desconto']) && $venda['desconto'] > 0) {
            $dados['discount'] = (float) $venda['desconto'];
        }

        // Itens da venda
        if (!empty($venda['itens'])) {
            $dados['items'] = $this->transformarItens($venda['itens']);
        }

        // Observações
        if (!empty($venda['observacoes'])) {
            $dados['notes'] = $venda['observacoes'];
        }

        // Forma de pagamento
        if (!empty($venda['forma_pagamento'])) {
            $dados['payment_method'] = $venda['forma_pagamento'];
        }

        // Vendedor
        if (!empty($venda['vendedor']) || !empty($venda['id_vendedor'])) {
            $dados['salesperson'] = $venda['vendedor'] ?? $venda['id_vendedor'];
        }

        return $dados;
    }

    /**
     * Transforma dados do GestaoClick para formato Ecletech
     */
    public function transformarParaInterno(array $vendaCrm): array
    {
        $dados = [
            'external_id' => (string) $vendaCrm['id'],
            'titulo' => $vendaCrm['title'] ?? '',
            'valor_total' => (float) ($vendaCrm['total_value'] ?? 0),
            'status' => $this->mapearStatusInterno($vendaCrm['status'] ?? 'pending'),
        ];

        // Data da venda
        if (!empty($vendaCrm['date'])) {
            $dados['data_venda'] = $vendaCrm['date'];
        }

        // Desconto
        if (isset($vendaCrm['discount']) && $vendaCrm['discount'] > 0) {
            $dados['desconto'] = (float) $vendaCrm['discount'];
        }

        // Observações
        if (!empty($vendaCrm['notes'])) {
            $dados['observacoes'] = $vendaCrm['notes'];
        }

        // Forma de pagamento
        if (!empty($vendaCrm['payment_method'])) {
            $dados['forma_pagamento'] = $vendaCrm['payment_method'];
        }

        // Vendedor
        if (!empty($vendaCrm['salesperson'])) {
            $dados['vendedor'] = $vendaCrm['salesperson'];
        }

        // Cliente (external_id)
        if (!empty($vendaCrm['customer_id'])) {
            $dados['external_id_cliente'] = (string) $vendaCrm['customer_id'];
        }

        return $dados;
    }

    /**
     * Transforma itens da venda para formato externo
     */
    private function transformarItens(array $itens): array
    {
        return array_map(function ($item) {
            return [
                'product_id' => $item['external_id_produto'] ?? null,
                'product_name' => $item['nome_produto'] ?? '',
                'quantity' => (int) ($item['quantidade'] ?? 1),
                'unit_price' => (float) ($item['preco_unitario'] ?? 0),
                'total_price' => (float) ($item['preco_total'] ?? 0),
                'discount' => (float) ($item['desconto'] ?? 0)
            ];
        }, $itens);
    }

    /**
     * Mapeia status do Ecletech para GestaoClick
     */
    private function mapearStatus(string $status): string
    {
        $mapa = $this->config['status_venda'] ?? [
            'pendente' => 'pending',
            'aprovado' => 'won',
            'cancelado' => 'lost',
            'em_andamento' => 'in_progress'
        ];

        return $mapa[$status] ?? 'pending';
    }

    /**
     * Mapeia status do GestaoClick para Ecletech
     */
    private function mapearStatusInterno(string $status): string
    {
        $mapa = $this->config['status_venda_reverso'] ?? [
            'pending' => 'pendente',
            'won' => 'aprovado',
            'lost' => 'cancelado',
            'in_progress' => 'em_andamento'
        ];

        return $mapa[$status] ?? 'pendente';
    }
}
