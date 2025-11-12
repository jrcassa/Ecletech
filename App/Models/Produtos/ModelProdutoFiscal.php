<?php

namespace App\Models\Produtos;

use App\Core\BancoDados;

/**
 * Model para gerenciar informações fiscais de produtos
 */
class ModelProdutoFiscal
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca informações fiscais de um produto
     */
    public function buscarPorProduto(int $produtoId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM produto_fiscal WHERE produto_id = ?",
            [$produtoId]
        );
    }

    /**
     * Busca informação fiscal por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM produto_fiscal WHERE id = ?",
            [$id]
        );
    }

    /**
     * Cria informações fiscais para o produto
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'produto_id' => $dados['produto_id'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = ['ncm', 'cest', 'peso_liquido', 'peso_bruto', 'valor_aproximado_tributos'];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        return $this->db->inserir('produto_fiscal', $dadosInsert);
    }

    /**
     * Atualiza informações fiscais do produto
     */
    public function atualizar(int $id, array $dados): bool
    {
        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = ['ncm', 'cest', 'peso_liquido', 'peso_bruto', 'valor_aproximado_tributos'];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo] !== '' ? $dados[$campo] : null;
            }
        }

        return $this->db->atualizar('produto_fiscal', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Atualiza ou cria informações fiscais do produto
     */
    public function atualizarOuCriar(int $produtoId, array $dados): bool
    {
        $fiscal = $this->buscarPorProduto($produtoId);

        if ($fiscal) {
            // Atualiza
            return $this->atualizar($fiscal['id'], $dados);
        } else {
            // Cria
            $dados['produto_id'] = $produtoId;
            $this->criar($dados);
            return true;
        }
    }

    /**
     * Deleta informações fiscais do produto
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('produto_fiscal', 'id = ?', [$id]);
    }

    /**
     * Deleta informações fiscais por produto
     */
    public function deletarPorProduto(int $produtoId): bool
    {
        return $this->db->deletar('produto_fiscal', 'produto_id = ?', [$produtoId]);
    }
}
