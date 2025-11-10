<?php

namespace App\Models\Colaborador;

use App\Core\BancoDados;
use App\Core\RegistroAuditoria;

/**
 * Model para gerenciar relação entre roles e permissões
 */
class ModelColaboradorRolePermission
{
    private BancoDados $db;
    private RegistroAuditoria $auditoria;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
        $this->auditoria = new RegistroAuditoria();
    }

    /**
     * Busca permissões de uma role
     */
    public function buscarPermissoesRole(int $roleId): array
    {
        return $this->db->buscarTodos(
            "SELECT p.*
             FROM colaborador_permissions p
             INNER JOIN colaborador_role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ? AND p.ativo = 1
             ORDER BY p.modulo ASC, p.nome ASC",
            [$roleId]
        );
    }

    /**
     * Busca roles que têm uma permissão
     */
    public function buscarRolesPermissao(int $permissionId): array
    {
        return $this->db->buscarTodos(
            "SELECT r.*
             FROM colaborador_roles r
             INNER JOIN colaborador_role_permissions rp ON rp.role_id = r.id
             WHERE rp.permission_id = ? AND r.ativo = 1
             ORDER BY r.nome ASC",
            [$permissionId]
        );
    }

    /**
     * Adiciona uma permissão a uma role
     */
    public function adicionar(int $roleId, int $permissionId, ?int $usuarioId = null): int
    {
        // Verifica se já existe
        if ($this->existe($roleId, $permissionId)) {
            return 0;
        }

        $id = $this->db->inserir('colaborador_role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        $this->auditoria->registrarCriacao(
            'colaborador_role_permissions',
            $id,
            ['role_id' => $roleId, 'permission_id' => $permissionId],
            $usuarioId
        );

        return $id;
    }

    /**
     * Remove uma permissão de uma role
     */
    public function remover(int $roleId, int $permissionId, ?int $usuarioId = null): bool
    {
        $dados = $this->db->buscarUm(
            "SELECT * FROM colaborador_role_permissions WHERE role_id = ? AND permission_id = ?",
            [$roleId, $permissionId]
        );

        if (!$dados) {
            return false;
        }

        $resultado = $this->db->deletar(
            'colaborador_role_permissions',
            'role_id = ? AND permission_id = ?',
            [$roleId, $permissionId]
        );

        if ($resultado > 0) {
            $this->auditoria->registrarExclusao(
                'colaborador_role_permissions',
                $dados['id'],
                $dados,
                $usuarioId
            );
        }

        return $resultado > 0;
    }

    /**
     * Sincroniza permissões de uma role
     */
    public function sincronizar(int $roleId, array $permissionIds, ?int $usuarioId = null): bool
    {
        $this->db->iniciarTransacao();

        try {
            // Remove todas as permissões atuais
            $this->db->deletar('colaborador_role_permissions', 'role_id = ?', [$roleId]);

            // Adiciona as novas permissões
            foreach ($permissionIds as $permissionId) {
                $this->db->inserir('colaborador_role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'criado_em' => date('Y-m-d H:i:s')
                ]);
            }

            $this->db->commit();

            $this->auditoria->registrar(
                'sincronizar',
                'colaborador_role_permissions',
                $roleId,
                null,
                ['permission_ids' => $permissionIds],
                $usuarioId
            );

            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Verifica se uma role tem uma permissão
     */
    public function existe(int $roleId, int $permissionId): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT id FROM colaborador_role_permissions WHERE role_id = ? AND permission_id = ?",
            [$roleId, $permissionId]
        );

        return $resultado !== null;
    }

    /**
     * Verifica se um usuário (através do seu nível e role) tem uma permissão
     */
    public function usuarioTemPermissao(int $nivelId, string $codigoPermissao): bool
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total
             FROM colaborador_permissions p
             INNER JOIN colaborador_role_permissions rp ON rp.permission_id = p.id
             INNER JOIN colaborador_roles r ON r.id = rp.role_id
             WHERE r.nivel_id = ? AND p.codigo = ? AND r.ativo = 1 AND p.ativo = 1",
            [$nivelId, $codigoPermissao]
        );

        return $resultado && $resultado['total'] > 0;
    }

    /**
     * Lista todas as permissões de um nível
     */
    public function buscarPermissoesNivel(int $nivelId): array
    {
        return $this->db->buscarTodos(
            "SELECT DISTINCT p.*
             FROM colaborador_permissions p
             INNER JOIN colaborador_role_permissions rp ON rp.permission_id = p.id
             INNER JOIN colaborador_roles r ON r.id = rp.role_id
             WHERE r.nivel_id = ? AND r.ativo = 1 AND p.ativo = 1
             ORDER BY p.modulo ASC, p.nome ASC",
            [$nivelId]
        );
    }

    /**
     * Remove todas as permissões de uma role
     */
    public function removerTodasRole(int $roleId, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->deletar(
            'colaborador_role_permissions',
            'role_id = ?',
            [$roleId]
        );

        if ($resultado > 0) {
            $this->auditoria->registrar(
                'remover_todas',
                'colaborador_role_permissions',
                $roleId,
                null,
                null,
                $usuarioId
            );
        }

        return $resultado > 0;
    }

    /**
     * Remove todas as associações de uma permissão
     */
    public function removerTodasPermissao(int $permissionId, ?int $usuarioId = null): bool
    {
        $resultado = $this->db->deletar(
            'colaborador_role_permissions',
            'permission_id = ?',
            [$permissionId]
        );

        if ($resultado > 0) {
            $this->auditoria->registrar(
                'remover_todas',
                'colaborador_role_permissions',
                $permissionId,
                null,
                null,
                $usuarioId
            );
        }

        return $resultado > 0;
    }

    /**
     * Conta o total de permissões de uma role
     */
    public function contarPermissoesRole(int $roleId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM colaborador_role_permissions WHERE role_id = ?",
            [$roleId]
        );

        return (int) $resultado['total'];
    }

    /**
     * Conta o total de roles que têm uma permissão
     */
    public function contarRolesPermissao(int $permissionId): int
    {
        $resultado = $this->db->buscarUm(
            "SELECT COUNT(*) as total FROM colaborador_role_permissions WHERE permission_id = ?",
            [$permissionId]
        );

        return (int) $resultado['total'];
    }
}
