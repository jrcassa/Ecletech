<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Venda
 * Ecletech <-> GestãoClick
 *
 * Baseado na estrutura real da API GestãoClick
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
        // Estrutura real da API GestãoClick (conforme Postman)
        $dados = [
            'cliente_id' => $venda['cliente_id'] ?? $venda['external_id_cliente'] ?? '',
            'vendedor_id' => $venda['vendedor_id'] ?? $venda['id_vendedor'] ?? '',
            'data' => $venda['data'] ?? $venda['data_venda'] ?? date('Y-m-d'),
            'observacoes' => $venda['observacoes'] ?? '',
            'usuario_id' => $venda['usuario_id'] ?? '',
            'loja_id' => $venda['loja_id'] ?? '',
        ];

        // Produtos (array de objetos com estrutura específica)
        if (!empty($venda['produtos']) && is_array($venda['produtos'])) {
            $produtos = [];
            foreach ($venda['produtos'] as $prod) {
                $produtos[] = [
                    'produto' => [
                        'id' => $prod['id'] ?? $prod['produto_id'] ?? '',
                        'quantidade' => $prod['quantidade'] ?? '1',
                        'valor_unitario' => $this->formatarPreco($prod['valor_unitario'] ?? $prod['preco'] ?? 0),
                        'valor_desconto' => $this->formatarPreco($prod['valor_desconto'] ?? $prod['desconto'] ?? 0),
                        'valor_desconto_percentual' => $prod['valor_desconto_percentual'] ?? '',
                        'valor_acrescimo' => $prod['valor_acrescimo'] ?? '',
                        'valor_acrescimo_percentual' => $prod['valor_acrescimo_percentual'] ?? '',
                        'valor_frete' => $prod['valor_frete'] ?? '',
                        'valor_seguro' => $prod['valor_seguro'] ?? '',
                        'outras_despesas' => $prod['outras_despesas'] ?? '',
                        'valor_total' => $this->formatarPreco($prod['valor_total'] ?? 0)
                    ]
                ];
            }
            $dados['produtos'] = $produtos;
        }

        // Parcelas (array de objetos com estrutura específica)
        if (!empty($venda['parcelas']) && is_array($venda['parcelas'])) {
            $parcelas = [];
            foreach ($venda['parcelas'] as $parc) {
                $parcelas[] = [
                    'parcela' => [
                        'data_vencimento' => $parc['data_vencimento'] ?? '',
                        'conta_id' => $parc['conta_id'] ?? '',
                        'valor' => $this->formatarPreco($parc['valor'] ?? 0),
                        'forma_pagamento_id' => $parc['forma_pagamento_id'] ?? '',
                        'situacao' => $parc['situacao'] ?? '0' // 0 = aberto, 1 = pago
                    ]
                ];
            }
            $dados['parcelas'] = $parcelas;
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
            'cliente_id' => $vendaCrm['cliente_id'] ?? null,
            'vendedor_id' => $vendaCrm['vendedor_id'] ?? null,
            'data_venda' => $vendaCrm['data'] ?? null,
            'observacoes' => $vendaCrm['observacoes'] ?? '',
            'valor_total' => 0, // Será calculado a partir dos produtos
        ];

        // Produtos
        if (!empty($vendaCrm['produtos']) && is_array($vendaCrm['produtos'])) {
            $produtos = [];
            $valorTotal = 0;

            foreach ($vendaCrm['produtos'] as $prodObj) {
                $prod = $prodObj['produto'] ?? $prodObj;
                $produtos[] = [
                    'produto_id' => $prod['id'] ?? '',
                    'quantidade' => $prod['quantidade'] ?? 1,
                    'preco' => $this->formatarPreco($prod['valor_unitario'] ?? 0),
                    'desconto' => $this->formatarPreco($prod['valor_desconto'] ?? 0),
                    'total' => $this->formatarPreco($prod['valor_total'] ?? 0)
                ];

                $valorTotal += (float) ($prod['valor_total'] ?? 0);
            }

            $dados['produtos'] = $produtos;
            $dados['valor_total'] = $valorTotal;
        }

        // Parcelas
        if (!empty($vendaCrm['parcelas']) && is_array($vendaCrm['parcelas'])) {
            $parcelas = [];
            foreach ($vendaCrm['parcelas'] as $parcObj) {
                $parc = $parcObj['parcela'] ?? $parcObj;
                $parcelas[] = [
                    'data_vencimento' => $parc['data_vencimento'] ?? '',
                    'valor' => $this->formatarPreco($parc['valor'] ?? 0),
                    'forma_pagamento_id' => $parc['forma_pagamento_id'] ?? '',
                    'situacao' => $parc['situacao'] ?? '0'
                ];
            }
            $dados['parcelas'] = $parcelas;
        }

        return $dados;
    }

    /**
     * Formata preço para envio (remove formatação, mantém decimal)
     */
    private function formatarPreco($preco): string
    {
        if (is_string($preco)) {
            $preco = str_replace(['.', ','], ['', '.'], $preco);
            $preco = preg_replace('/[^0-9.]/', '', $preco);
        }

        return number_format((float) $preco, 2, '.', '');
    }
}
