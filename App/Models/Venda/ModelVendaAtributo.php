<?php

namespace App\Models\Venda;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar atributos customizados das vendas
 */
class ModelVendaAtributo
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um atributo por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_atributos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um atributo por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_atributos WHERE external_id = ?",
            [$externalId]
        );
    }

    /**
     * Lista todos os atributos de uma venda
     */
    public function listarPorVenda(int $vendaId): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM vendas_atributos WHERE venda_id = ? ORDER BY id",
            [$vendaId]
        );
    }

    /**
     * Busca atributo específico de uma venda
     */
    public function buscarPorVendaEAtributo(int $vendaId, string $atributoId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_atributos WHERE venda_id = ? AND atributo_id = ?",
            [$vendaId, $atributoId]
        );
    }

    /**
     * Cria um novo atributo
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'venda_id' => $dados['venda_id'],
            'descricao' => $dados['descricao'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'venda_external_id',
            'atributo_id', 'atributo_external_id',
            'tipo', 'conteudo'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('vendas_atributos', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'venda_atributo',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um atributo
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = ['modificado_em' => date('Y-m-d H:i:s')];

        // Campos atualizáveis
        $camposAtualizaveis = [
            'external_id', 'venda_external_id',
            'atributo_id', 'atributo_external_id',
            'descricao', 'tipo', 'conteudo'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        $sucesso = $this->db->atualizar('vendas_atributos', $dadosUpdate, "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'atualizar',
                'venda_atributo',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta um atributo
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $sucesso = $this->db->deletar('vendas_atributos', "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'deletar',
                'venda_atributo',
                $id,
                $dadosAtuais,
                null,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta todos os atributos de uma venda
     */
    public function deletarPorVenda(int $vendaId, ?int $usuarioId = null): bool
    {
        $atributos = $this->listarPorVenda($vendaId);

        foreach ($atributos as $atributo) {
            $this->deletar($atributo['id'], $usuarioId);
        }

        return true;
    }
}
