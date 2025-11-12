<?php

namespace App\Models\Produtos;

use App\Core\BancoDados;

/**
 * Model para gerenciar valores/preços de produtos
 */
class ModelProdutoValores
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca todos os valores de um produto
     */
    public function buscarPorProduto(int $produtoId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM produto_valores WHERE produto_id = ? ORDER BY id",
            [$produtoId]
        );
    }

    /**
     * Busca um valor específico por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM produto_valores WHERE id = ?",
            [$id]
        );
    }

    /**
     * Cria um novo valor para o produto
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'produto_id' => $dados['produto_id'],
            'tipo_id' => $dados['tipo_id'],
            'nome_tipo' => $dados['nome_tipo'],
            'valor_custo' => $dados['valor_custo'] ?? 0,
            'valor_venda' => $dados['valor_venda'] ?? 0,
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campo opcional
        if (isset($dados['lucro_utilizado']) && $dados['lucro_utilizado'] !== '') {
            $dadosInsert['lucro_utilizado'] = $dados['lucro_utilizado'];
        }

        return $this->db->inserir('produto_valores', $dadosInsert);
    }

    /**
     * Atualiza um valor do produto
     */
    public function atualizar(int $id, array $dados): bool
    {
        $dadosUpdate = [
            'modificado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = ['tipo_id', 'nome_tipo', 'lucro_utilizado', 'valor_custo', 'valor_venda'];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        return $this->db->atualizar('produto_valores', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um valor do produto
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('produto_valores', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os valores de um produto
     */
    public function deletarPorProduto(int $produtoId): bool
    {
        return $this->db->deletar('produto_valores', 'produto_id = ?', [$produtoId]);
    }

    /**
     * Sincroniza valores de um produto
     * Remove os antigos e insere os novos
     */
    public function sincronizar(int $produtoId, array $valores): bool
    {
        // Deleta todos os valores atuais
        $this->deletarPorProduto($produtoId);

        // Insere os novos valores
        foreach ($valores as $valor) {
            $valor['produto_id'] = $produtoId;
            $this->criar($valor);
        }

        return true;
    }

    /**
     * Verifica se já existe um valor com o mesmo tipo_id para o produto
     */
    public function tipoExiste(int $produtoId, string $tipoId, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM produto_valores WHERE produto_id = ? AND tipo_id = ?";
        $parametros = [$produtoId, $tipoId];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado['total'] > 0;
    }
}
