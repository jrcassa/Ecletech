<?php

namespace App\Models\Transportadora;

use App\Core\BancoDados;

/**
 * Model para gerenciar endereços de transportadoras
 */
class ModelTransportadoraEndereco
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
            "SELECT * FROM transportadoras_enderecos WHERE id = ?",
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
                te.*,
                c.nome as nome_cidade
            FROM transportadoras_enderecos te
            LEFT JOIN cidades c ON te.cidade_id = c.id
            WHERE te.id = ?",
            [$id]
        );
    }

    /**
     * Lista todos os endereços de uma transportadora
     */
    public function listarPorTransportadora(int $transportadoraId): array
    {
        return $this->db->buscarTodos(
            "SELECT
                te.*,
                c.nome as nome_cidade
            FROM transportadoras_enderecos te
            LEFT JOIN cidades c ON te.cidade_id = c.id
            WHERE te.transportadora_id = ?
            ORDER BY te.id",
            [$transportadoraId]
        );
    }

    /**
     * Cria um novo endereço
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'transportadora_id' => $dados['transportadora_id'],
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

        return $this->db->inserir('transportadoras_enderecos', $dadosInsert);
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

        return $this->db->atualizar('transportadoras_enderecos', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um endereço
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('transportadoras_enderecos', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os endereços de uma transportadora
     */
    public function deletarPorTransportadora(int $transportadoraId): bool
    {
        return $this->db->deletar('transportadoras_enderecos', 'transportadora_id = ?', [$transportadoraId]);
    }

    /**
     * Conta total de endereços de uma transportadora
     */
    public function contarPorTransportadora(int $transportadoraId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM transportadoras_enderecos WHERE transportadora_id = ?",
            [$transportadoraId]
        );
        return (int) $resultado['total'];
    }
}
