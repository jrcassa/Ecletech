<?php

namespace App\Models;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar permissões de colaborador
 */
class ModelColaboradorPermission
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca uma permissão por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM colaborador_permissions WHERE id = ?",
            [$id]
        );
    }

    /**
     * Busca uma permissão por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->db->buscarUm(
            "SELECT * FROM colaborador_permissions WHERE codigo = ?",
            [$codigo]
        );
    }

    /**
     * Lista todas as permissões
     */
    public function listar(array $filtros = []): array
    {
        $sql = "SELECT * FROM colaborador_permissions WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['modulo'])) {
            $sql .= " AND modulo = ?";
            $parametros[] = $filtros['modulo'];
        }

        $sql .= " ORDER BY modulo ASC, nome ASC";

        return $this->db->buscarTodos($sql, $parametros);
    }

    /**
     * Cria uma nova permissão
     */
    public function criar(array $dados, ?int $usuarioId = null): int
    {
        $id = $this->db->inserir('colaborador_permissions', [
            'nome' => $dados['nome'],
            'codigo' => $dados['codigo'],
            'descricao' => $dados['descricao'] ?? null,
            'modulo' => $dados['modulo'] ?? 'geral',
            'ativo' => $dados['ativo'] ?? 1,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        $this->auditoria->registrarCriacao('colaborador_permissions', $id, $dados, $usuarioId);

        return $id;
    }

    /**
     * Atualiza uma permissão
     */
    public function atualizar(int $id, array $dados, ?int $usuarioId = null): bool
    {
        $dadosAntigos = $this->buscarPorId($id);

        if (!$dadosAntigos) {
            return false;
        }

        $dadosAtualizacao = [];

        if (isset($dados['nome'])) {
            $dadosAtualizacao['nome'] = $dados['nome'];
        }

        if (isset($dados['codigo'])) {
            $dadosAtualizacao['codigo'] = $dados['codigo'];
        }

        if (isset($dados['descricao'])) {
            $dadosAtualizacao['descricao'] = $dados['descricao'];
        }

        if (isset($dados['modulo'])) {
            $dadosAtualizacao['modulo'] = $dados['modulo'];
        }

        if (isset($dados['ativo'])) {
            $dadosAtualizacao['ativo'] = $dados['ativo'];
        }

        if (!empty($dadosAtualizacao)) {
            $dadosAtualizacao['atualizado_em'] = date('Y-m-d H:i:s');
            $this->db->atualizar('colaborador_permissions', $dadosAtualizacao, 'id = ?', [$id]);
            $this->auditoria->registrarAtualizacao('colaborador_permissions', $id, $dadosAntigos, $dadosAtualizacao, $usuarioId);
        }

        return true;
    }

    /**
     * Deleta uma permissão (soft delete)
     */
    public function deletar(int $id, ?int $usuarioId = null): bool
    {
        $dados = $this->buscarPorId($id);

        if (!$dados) {
            return false;
        }

        $resultado = $this->db->atualizar(
            'colaborador_permissions',
            ['ativo' => 0, 'deletado_em' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        if ($resultado > 0) {
            $this->auditoria->registrarExclusao('colaborador_permissions', $id, $dados, $usuarioId);
        }

        return $resultado > 0;
    }

    /**
     * Busca permissões por módulo
     */
    public function buscarPorModulo(string $modulo): array
    {
        return $this->db->buscarTodos(
            "SELECT * FROM colaborador_permissions WHERE modulo = ? AND ativo = 1 ORDER BY nome ASC",
            [$modulo]
        );
    }

    /**
     * Lista todos os módulos
     */
    public function listarModulos(): array
    {
        return $this->db->buscarTodos(
            "SELECT DISTINCT modulo FROM colaborador_permissions WHERE ativo = 1 ORDER BY modulo ASC"
        );
    }

    /**
     * Conta o total de permissões
     */
    public function contar(array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM colaborador_permissions WHERE 1=1";
        $parametros = [];

        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = ?";
            $parametros[] = $filtros['ativo'];
        }

        if (isset($filtros['modulo'])) {
            $sql .= " AND modulo = ?";
            $parametros[] = $filtros['modulo'];
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return (int) $resultado['total'];
    }

    /**
     * Verifica se o código já existe
     */
    public function codigoExiste(string $codigo, ?int $excluirId = null): bool
    {
        $sql = "SELECT id FROM colaborador_permissions WHERE codigo = ?";
        $parametros = [$codigo];

        if ($excluirId !== null) {
            $sql .= " AND id != ?";
            $parametros[] = $excluirId;
        }

        $resultado = $this->db->buscarUm($sql, $parametros);
        return $resultado !== null;
    }
}
