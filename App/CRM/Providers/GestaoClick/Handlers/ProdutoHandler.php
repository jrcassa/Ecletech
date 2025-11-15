<?php

namespace App\CRM\Providers\GestaoClick\Handlers;

/**
 * Handler para transformação de dados de Produto
 * Ecletech <-> GestãoClick
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
        $dados = [
            'name' => $produto['nome'] ?? '',
            'code' => $produto['codigo'] ?? null,
            'description' => $produto['descricao'] ?? null,
            'price' => (float) ($produto['preco'] ?? $produto['preco_venda'] ?? 0),
            'cost' => (float) ($produto['custo'] ?? $produto['preco_custo'] ?? 0),
        ];

        // Estoque
        if (isset($produto['estoque'])) {
            $dados['stock_quantity'] = (int) $produto['estoque'];
        }

        // Unidade de medida
        if (!empty($produto['unidade'])) {
            $dados['unit'] = $produto['unidade'];
        }

        // Categoria/Grupo
        if (!empty($produto['grupo']) || !empty($produto['categoria'])) {
            $dados['category'] = $produto['grupo'] ?? $produto['categoria'];
        }

        // Código de barras
        if (!empty($produto['codigo_barras']) || !empty($produto['ean'])) {
            $dados['barcode'] = $produto['codigo_barras'] ?? $produto['ean'];
        }

        // NCM (Nomenclatura Comum do Mercosul)
        if (!empty($produto['ncm'])) {
            $dados['ncm'] = $produto['ncm'];
        }

        // Status
        if (isset($produto['ativo'])) {
            $dados['active'] = (bool) $produto['ativo'];
        }

        // Peso
        if (!empty($produto['peso'])) {
            $dados['weight'] = (float) $produto['peso'];
        }

        // Dimensões
        if (!empty($produto['largura']) || !empty($produto['altura']) || !empty($produto['comprimento'])) {
            $dados['dimensions'] = [
                'width' => (float) ($produto['largura'] ?? 0),
                'height' => (float) ($produto['altura'] ?? 0),
                'length' => (float) ($produto['comprimento'] ?? 0)
            ];
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
            'nome' => $produtoCrm['name'] ?? '',
            'codigo' => $produtoCrm['code'] ?? null,
            'descricao' => $produtoCrm['description'] ?? null,
            'preco' => (float) ($produtoCrm['price'] ?? 0),
            'preco_venda' => (float) ($produtoCrm['price'] ?? 0),
            'custo' => (float) ($produtoCrm['cost'] ?? 0),
            'preco_custo' => (float) ($produtoCrm['cost'] ?? 0),
        ];

        // Estoque
        if (isset($produtoCrm['stock_quantity'])) {
            $dados['estoque'] = (int) $produtoCrm['stock_quantity'];
        }

        // Unidade de medida
        if (!empty($produtoCrm['unit'])) {
            $dados['unidade'] = $produtoCrm['unit'];
        }

        // Categoria/Grupo
        if (!empty($produtoCrm['category'])) {
            $dados['grupo'] = $produtoCrm['category'];
            $dados['categoria'] = $produtoCrm['category'];
        }

        // Código de barras
        if (!empty($produtoCrm['barcode'])) {
            $dados['codigo_barras'] = $produtoCrm['barcode'];
            $dados['ean'] = $produtoCrm['barcode'];
        }

        // NCM
        if (!empty($produtoCrm['ncm'])) {
            $dados['ncm'] = $produtoCrm['ncm'];
        }

        // Status
        if (isset($produtoCrm['active'])) {
            $dados['ativo'] = (int) $produtoCrm['active'];
        }

        // Peso
        if (!empty($produtoCrm['weight'])) {
            $dados['peso'] = (float) $produtoCrm['weight'];
        }

        // Dimensões
        if (!empty($produtoCrm['dimensions'])) {
            $dims = $produtoCrm['dimensions'];
            $dados['largura'] = (float) ($dims['width'] ?? 0);
            $dados['altura'] = (float) ($dims['height'] ?? 0);
            $dados['comprimento'] = (float) ($dims['length'] ?? 0);
        }

        return $dados;
    }
}
