<?php

namespace App\Models\Cliente;

use App\Core\BancoDados;

/**
 * Model para gerenciar contatos de clientes
 */
class ModelClienteContato
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
            "SELECT * FROM clientes_contatos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Lista todos os contatos de um cliente
     */
    public function listarPorCliente(int $clienteId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM clientes_contatos WHERE cliente_id = ? ORDER BY id",
            [$clienteId]
        );
    }

    /**
     * Cria um novo contato
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'cliente_id' => $dados['cliente_id'],
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

        return $this->db->inserir('clientes_contatos', $dadosInsert);
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

        return $this->db->atualizar('clientes_contatos', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um contato
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('clientes_contatos', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os contatos de um cliente
     */
    public function deletarPorCliente(int $clienteId): bool
    {
        return $this->db->deletar('clientes_contatos', 'cliente_id = ?', [$clienteId]);
    }

    /**
     * Conta total de contatos de um cliente
     */
    public function contarPorCliente(int $clienteId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM clientes_contatos WHERE cliente_id = ?",
            [$clienteId]
        );
        return (int) $resultado['total'];
    }
}
