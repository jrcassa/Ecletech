<?php

namespace App\Models\Fornecedor;

use App\Core\BancoDados;

/**
 * Model para gerenciar endereços de fornecedores
 */
class ModelFornecedorEndereco
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca um endereço por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM fornecedores_enderecos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um endereço com informações da cidade
     */
    public function buscarComCidade(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT
                fe.*,
                c.nome as nome_cidade,
                c.estado_id
            FROM fornecedores_enderecos fe
            LEFT JOIN cidades c ON fe.cidade_id = c.id
            WHERE fe.id = ?",
            [$id]
        );
    }

    /**
     * Lista todos os endereços de um fornecedor
     */
    public function listarPorFornecedor(int $fornecedorId): array
    {
        return $this->db->buscarTodos(
            "SELECT
                fe.*,
                c.nome as nome_cidade,
                c.estado_id
            FROM fornecedores_enderecos fe
            LEFT JOIN cidades c ON fe.cidade_id = c.id
            WHERE fe.fornecedor_id = ?
            ORDER BY fe.id",
            [$fornecedorId]
        );
    }

    /**
     * Cria um novo endereço
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'fornecedor_id' => $dados['fornecedor_id'],
            'criado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'cep', 'logradouro', 'numero', 'complemento',
            'bairro', 'cidade_id', 'estado'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        return $this->db->inserir('fornecedores_enderecos', $dadosInsert);
    }

    /**
     * Atualiza um endereço
     */
    public function atualizar(int $id, array $dados): bool
    {
        $dadosUpdate = [
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        // Campos que podem ser atualizados
        $camposAtualizaveis = [
            'cep', 'logradouro', 'numero', 'complemento',
            'bairro', 'cidade_id', 'estado'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        return $this->db->atualizar('fornecedores_enderecos', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um endereço
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('fornecedores_enderecos', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os endereços de um fornecedor
     */
    public function deletarPorFornecedor(int $fornecedorId): bool
    {
        return $this->db->deletar('fornecedores_enderecos', 'fornecedor_id = ?', [$fornecedorId]);
    }

    /**
     * Conta total de endereços de um fornecedor
     */
    public function contarPorFornecedor(int $fornecedorId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM fornecedores_enderecos WHERE fornecedor_id = ?",
            [$fornecedorId]
        );
        return (int) $resultado['total'];
    }
}
