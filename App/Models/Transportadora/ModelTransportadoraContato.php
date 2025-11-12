<?php

namespace App\Models\Transportadora;

use App\Core\BancoDados;

/**
 * Model para gerenciar contatos de transportadoras
 */
class ModelTransportadoraContato
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
            "SELECT * FROM transportadoras_contatos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Lista todos os contatos de uma transportadora
     */
    public function listarPorTransportadora(int $transportadoraId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM transportadoras_contatos WHERE transportadora_id = ? ORDER BY id",
            [$transportadoraId]
        );
    }

    /**
     * Cria um novo contato
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'transportadora_id' => $dados['transportadora_id'],
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

        return $this->db->inserir('transportadoras_contatos', $dadosInsert);
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

        return $this->db->atualizar('transportadoras_contatos', $dadosUpdate, 'id = ?', [$id]);
    }

    /**
     * Deleta um contato
     */
    public function deletar(int $id): bool
    {
        return $this->db->deletar('transportadoras_contatos', 'id = ?', [$id]);
    }

    /**
     * Deleta todos os contatos de uma transportadora
     */
    public function deletarPorTransportadora(int $transportadoraId): bool
    {
        return $this->db->deletar('transportadoras_contatos', 'transportadora_id = ?', [$transportadoraId]);
    }

    /**
     * Conta total de contatos de uma transportadora
     */
    public function contarPorTransportadora(int $transportadoraId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM transportadoras_contatos WHERE transportadora_id = ?",
            [$transportadoraId]
        );
        return (int) $resultado['total'];
    }
}
