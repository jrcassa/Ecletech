<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Produto
 * Ecletech <-> GestãoClick
 *
 * Baseado na estrutura real da API GestãoClick
 */
class ProdutoHandler
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Transforma dados do Ecletech para formato GestaoClick
     */
    public function transformarParaExterno(array $produto): array
    {
        // Estrutura real da API GestãoClick (conforme Postman)
        $dados = [
            'nome' => $produto['nome'] ?? '',
            'tipo_produto' => $produto['tipo_produto'] ?? '1', // 1 = produto, 2 = serviço
            'controla_estoque' => isset($produto['controla_estoque']) ? (string) $produto['controla_estoque'] : '1',
            'categoria_id' => $produto['categoria_id'] ?? '',
            'marca_id' => $produto['marca_id'] ?? '',
            'linha_id' => $produto['linha_id'] ?? '',
            'preco_minimo_venda' => $produto['preco_minimo_venda'] ?? '',
            'comissao' => $produto['comissao'] ?? '',
            'unidade_venda' => $produto['unidade_venda'] ?? $produto['unidade'] ?? 'UN',
            'peso_bruto' => $produto['peso_bruto'] ?? '0',
            'peso_liquido' => $produto['peso_liquido'] ?? '0',
            'ncm' => $produto['ncm'] ?? '',
            'origem' => $produto['origem'] ?? '0',
            'situacao' => !empty($produto['ativo']) || !empty($produto['situacao']) ? '1' : '0',
            'referencia' => $produto['referencia'] ?? $produto['codigo'] ?? '',
            'observacoes' => $produto['observacoes'] ?? '',
            'codigo_barras' => $produto['codigo_barras'] ?? $produto['ean'] ?? '',
            'usuario_id' => $produto['usuario_id'] ?? '',
            'loja_id' => $produto['loja_id'] ?? '',
            'estoque_inicial' => $produto['estoque_inicial'] ?? $produto['quantidade'] ?? '0',
            'estoque_minimo' => $produto['estoque_minimo'] ?? '0',
            'estoque_maximo' => $produto['estoque_maximo'] ?? '0',
            'preco_custo' => $this->formatarPreco($produto['preco_custo'] ?? $produto['custo'] ?? 0),
            'preco_venda' => $this->formatarPreco($produto['preco_venda'] ?? $produto['preco'] ?? 0),
        ];

        // Fornecedores (array de objetos)
        if (!empty($produto['fornecedores']) && is_array($produto['fornecedores'])) {
            $fornecedores = [];
            foreach ($produto['fornecedores'] as $forn) {
                $fornecedores[] = [
                    'fornecedor_id' => $forn['fornecedor_id'] ?? $forn['id'] ?? '',
                    'produto_fornecedor' => $forn['produto_fornecedor'] ?? $forn['codigo'] ?? ''
                ];
            }
            $dados['fornecedores'] = $fornecedores;
        }

        // Imagem (base64)
        if (!empty($produto['imagem'])) {
            // Se já estiver em base64, usa direto
            if (strpos($produto['imagem'], 'data:image') === 0) {
                // Remove o prefixo data:image/xxx;base64,
                $dados['imagem'] = preg_replace('/^data:image\/[a-z]+;base64,/', '', $produto['imagem']);
            } else {
                $dados['imagem'] = $produto['imagem'];
            }
        }

        return $dados;
    }

    /**
     * Transforma dados do GestaoClick para formato Ecletech
     */
    public function transformarParaInterno(array $produtoCrm): array
    {
        $dados = [
            'external_id' => (string) $produtoCrm['id'],
            'nome' => $produtoCrm['nome'] ?? '',
            'tipo_produto' => $produtoCrm['tipo_produto'] ?? '1',
            'controla_estoque' => $produtoCrm['controla_estoque'] ?? 1,
            'categoria_id' => $produtoCrm['categoria_id'] ?? null,
            'marca_id' => $produtoCrm['marca_id'] ?? null,
            'linha_id' => $produtoCrm['linha_id'] ?? null,
            'unidade' => $produtoCrm['unidade_venda'] ?? 'UN',
            'peso_bruto' => $produtoCrm['peso_bruto'] ?? 0,
            'peso_liquido' => $produtoCrm['peso_liquido'] ?? 0,
            'ncm' => $produtoCrm['ncm'] ?? '',
            'origem' => $produtoCrm['origem'] ?? 0,
            'ativo' => $produtoCrm['situacao'] === '1' ? 1 : 0,
            'codigo' => $produtoCrm['referencia'] ?? '',
            'observacoes' => $produtoCrm['observacoes'] ?? '',
            'ean' => $produtoCrm['codigo_barras'] ?? '',
            'quantidade' => $produtoCrm['estoque_atual'] ?? $produtoCrm['estoque_inicial'] ?? 0,
            'estoque_minimo' => $produtoCrm['estoque_minimo'] ?? 0,
            'estoque_maximo' => $produtoCrm['estoque_maximo'] ?? 0,
            'custo' => $this->formatarPreco($produtoCrm['preco_custo'] ?? 0),
            'preco' => $this->formatarPreco($produtoCrm['preco_venda'] ?? 0),
        ];

        // Fornecedores
        if (!empty($produtoCrm['fornecedores']) && is_array($produtoCrm['fornecedores'])) {
            $fornecedores = [];
            foreach ($produtoCrm['fornecedores'] as $forn) {
                $fornecedores[] = [
                    'id' => $forn['fornecedor_id'] ?? '',
                    'codigo' => $forn['produto_fornecedor'] ?? ''
                ];
            }
            $dados['fornecedores'] = $fornecedores;
        }

        return $dados;
    }

    /**
     * Formata preço para envio (remove formatação, mantém decimal)
     */
    private function formatarPreco($preco): string
    {
        if (is_string($preco)) {
            // Remove tudo exceto números e ponto/vírgula
            $preco = str_replace(['.', ','], ['', '.'], $preco);
            $preco = preg_replace('/[^0-9.]/', '', $preco);
        }

        return number_format((float) $preco, 2, '.', '');
    }
}
