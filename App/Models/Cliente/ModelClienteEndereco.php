<?php

namespace App\Models\Cliente;

use App\Core\BancoDados;

/**
 * Model para gerenciar endereços de clientees
 */
class ModelClienteEndereco
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
            "SELECT * FROM clientees_enderecos WHERE id = ?",
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
                c.nome as nome_cidade
            FROM clientees_enderecos fe
            LEFT JOIN cidades c ON fe.cidade_id = c.id
            WHERE fe.id = ?",
            [$id]
        );
    }

    /**
     * Lista todos os endereços de um cliente
     */
    public function listarPorCliente(int $clienteId): array
    {
        return $this->db->buscarTodos(
            "SELECT
                fe.*,
                c.nome as nome_cidade
            FROM clientees_enderecos fe
            LEFT JOIN cidades c ON fe.cidade_id = c.id
            WHERE fe.cliente_id = ?
            ORDER BY fe.id",
            [$clienteId]
        );
    }

    /**
     * Cria um novo endereço
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'cliente_id' => $dados['cliente_id'],
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

        return $this->db->inserir('clientees_enderecos', $dadosInsert);
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

        return $this->db->atualizar('clientees_enderecos', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um endereço
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('clientees_enderecos', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os endereços de um cliente
     */
    public function deletarPorCliente(int $clienteId): bool
    {
        return $this->db->deletar('clientees_enderecos', 'cliente_id = ?', [$clienteId]);
    }

    /**
     * Conta total de endereços de um cliente
     */
    public function contarPorCliente(int $clienteId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM clientees_enderecos WHERE cliente_id = ?",
            [$clienteId]
        );
        return (int) $resultado['total'];
    }
}
