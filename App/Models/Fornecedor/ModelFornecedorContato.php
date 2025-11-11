<?php

namespace App\Models\Fornecedor;

use App\Core\BancoDados;

/**
 * Model para gerenciar contatos de fornecedores
 */
class ModelFornecedorContato
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca um contato por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores_contatos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Lista todos os contatos de um fornecedor
     */
    public function listarPorFornecedor(int $fornecedorId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM fornecedores_contatos WHERE fornecedor_id = ? ORDER BY id",
            [$fornecedorId]
        );
    }

    /**
     * Cria um novo contato
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'fornecedor_id' => $dados['fornecedor_id'],
            'nome' => $dados['nome'],
            'contato' => $dados['contato'],
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        if (isset($dados['cargo']) && $dados['cargo'] !== '') {
            $dadosInsert['cargo'] = $dados['cargo'];
        }
        if (isset($dados['observacao']) && $dados['observacao'] !== '') {
            $dadosInsert['observacao'] = $dados['observacao'];
        }

        return $this->db->inserir('fornecedores_contatos', $dadosInsert);
    }

    /**
     * Atualiza um contato
     */
    public function atualizar(int $id, array $dados): bool
    {
        $dadosUpdate = [
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = ['nome', 'contato', 'cargo', 'observacao'];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        return $this->db->atualizar('fornecedores_contatos', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um contato
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('fornecedores_contatos', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os contatos de um fornecedor
     */
    public function deletarPorFornecedor(int $fornecedorId): bool
    {
        return $this->db->deletar('fornecedores_contatos', 'fornecedor_id = ?', [$fornecedorId]);
    }

    /**
     * Conta total de contatos de um fornecedor
     */
    public function contarPorFornecedor(int $fornecedorId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM fornecedores_contatos WHERE fornecedor_id = ?",
            [$fornecedorId]
        );
        return (int) $resultado['total'];
    }
}
