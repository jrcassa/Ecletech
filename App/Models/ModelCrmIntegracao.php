<?php

namespace App\Models;

use App\Core\BancoDados;

/**
 * Model para gerenciar configurações de integração CRM
 */
class ModelCrmIntegracao
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Busca configuração CRM por ID da loja
     */
    public function buscarPorLoja(int $idLoja): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM crm_integracoes WHERE id_loja = ? AND deletado_em IS NULL",
            [$idLoja]
        );
    }

    /**
     * Busca configuração CRM por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM crm_integracoes WHERE id = ? AND deletado_em IS NULL",
            [$id]
        );
    }

    /**
     * Lista todas as integrações ativas
     */
    public function listarAtivas(): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM crm_integracoes WHERE ativo = 1 AND deletado_em IS NULL ORDER BY id_loja"
        );
    }

    /**
     * Cria nova configuração de integração
     */
    public function criar(array $dados): int
    {
        $campos = [
            'id_loja',
            'provider',
            'credenciais',
            'configuracoes',
            'ativo',
            'criado_em',
            'atualizado_em'
        ];

        $dados['criado_em'] = date('Y-m-d H:i:s');
        $dados['atualizado_em'] = date('Y-m-d H:i:s');

        $placeholders = array_fill(0, count($campos), '?');
        $valores = array_map(fn($campo) => $dados[$campo] ?? null, $campos);

        $sql = sprintf(
            "INSERT INTO crm_integracoes (%s) VALUES (%s)",
            implode(', ', $campos),
            implode(', ', $placeholders)
        );

        $this->db->executar($sql, $valores);

        return (int) $this->db->obterUltimoId();
    }

    /**
     * Atualiza configuração existente
     */
    public function atualizar(int $id, array $dados): bool
    {
        $dados['atualizado_em'] = date('Y-m-d H:i:s');

        $camposPermitidos = ['provider', 'credenciais', 'configuracoes', 'ativo', 'atualizado_em'];
        $sets = [];
        $valores = [];

        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                $sets[] = "{$campo} = ?";
                $valores[] = $dados[$campo];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $valores[] = $id;

        $sql = sprintf(
            "UPDATE crm_integracoes SET %s WHERE id = ?",
            implode(', ', $sets)
        );

        $stmt = $this->db->executar($sql, $valores);
        return $stmt->rowCount() > 0;
    }

    /**
     * Soft delete da integração
     */
    public function deletar(int $id): bool
    {
        $stmt = $this->db->executar(
            "UPDATE crm_integracoes SET deletado_em = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Ativa ou desativa integração
     */
    public function alterarStatus(int $id, bool $ativo): bool
    {
        return $this->atualizar($id, ['ativo' => $ativo ? 1 : 0]);
    }
}
