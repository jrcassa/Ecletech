<?php

namespace App\Models\Venda;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar endereços de entrega das vendas
 */
class ModelVendaEndereco
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca um endereço por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_enderecos WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca um endereço por external_id
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM vendas_enderecos WHERE external_id = ?",
            [$externalId]
        );
    }

    /**
     * Busca endereço de uma venda com informações da cidade
     */
    public function buscarPorVenda(int $vendaId): ?array
    {
        return $this->db->buscarUm(
            "SELECT
                ve.*,
                c.nome as nome_cidade,
                e.nome as nome_estado
            FROM vendas_enderecos ve
            LEFT JOIN cidades c ON ve.cidade_id = c.id
            LEFT JOIN estados e ON ve.estado = e.sigla
            WHERE ve.venda_id = ?",
            [$vendaId]
        );
    }

    /**
     * Lista todos os endereços de uma venda
     */
    public function listarPorVenda(int $vendaId): array
    {
        return $this->db->buscarTodos(
            "SELECT
                ve.*,
                c.nome as nome_cidade,
                e.nome as nome_estado
            FROM vendas_enderecos ve
            LEFT JOIN cidades c ON ve.cidade_id = c.id
            LEFT JOIN estados e ON ve.estado = e.sigla
            WHERE ve.venda_id = ?
            ORDER BY ve.id",
            [$vendaId]
        );
    }

    /**
     * Cria um novo endereço de entrega
     */
    public function criar(array $dados): int
    {
        $dadosInsert = [
            'venda_id' => $dados['venda_id'],
            'cadastrado_em' => date('Y-m-d H:i:s')
        ];

        // Campos opcionais
        $camposOpcionais = [
            'external_id', 'venda_external_id',
            'cidade_id', 'cidade_external_id', 'nome_cidade',
            'cep', 'logradouro', 'numero', 'complemento',
            'bairro', 'estado', 'pais', 'referencia'
        ];

        foreach ($camposOpcionais as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] !== '') {
                $dadosInsert[$campo] = $dados[$campo];
            }
        }

        $id = $this->db->inserir('vendas_enderecos', $dadosInsert);

        // Registra auditoria
        $this->auditoria->registrar(
            'criar',
            'venda_endereco',
            $id,
            null,
            $dadosInsert,
            $dados['colaborador_id'] ?? null
        );

        return $id;
    }

    /**
     * Atualiza um endereço
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $dadosUpdate = [];

        // Campos atualizáveis
        $camposAtualizaveis = [
            'external_id', 'venda_external_id',
            'cidade_id', 'cidade_external_id', 'nome_cidade',
            'cep', 'logradouro', 'numero', 'complemento',
            'bairro', 'estado', 'pais', 'referencia'
        ];

        foreach ($camposAtualizaveis as $campo) {
            if (isset($dados[$campo])) {
                $dadosUpdate[$campo] = $dados[$campo];
            }
        }

        if (empty($dadosUpdate)) {
            return false;
        }

        $sucesso = $this->db->atualizar('vendas_enderecos', $dadosUpdate, "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'atualizar',
                'venda_endereco',
                $id,
                $dadosAtuais,
                $dadosUpdate,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta um endereço
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dadosAtuais = $this->buscarPorId($id);
        if (!$dadosAtuais) {
            return false;
        }

        $sucesso = $this->db->deletar('vendas_enderecos', "id = ?", [$id]);

        if ($sucesso) {
            $this->auditoria->registrar(
                'deletar',
                'venda_endereco',
                $id,
                $dadosAtuais,
                null,
                $usuarioId
            );
        }

        return $sucesso;
    }

    /**
     * Deleta todos os endereços de uma venda
     */
    public function deletarPorVenda(int $vendaId, ?int $usuarioId = null): bool
    {
        $enderecos = $this->listarPorVenda($vendaId);

        foreach ($enderecos as $endereco) {
            $this->deletar($endereco['id'], $usuarioId);
        }

        return true;
    }
}
