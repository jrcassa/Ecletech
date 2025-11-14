<?php

namespace App\Models\Dashboard;

use App\Core\BancoDados;

/**
 * Model para gerenciar templates de dashboards
 */
class ModelDashboardTemplate
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Lista todos os templates disponíveis
     */
    public function listar(int $colaboradorId): array
    {
        // TODO: Filtrar por permissões e nível do colaborador
        $templates = $this->db->buscarTodos(
            "SELECT * FROM dashboard_templates
             WHERE ativo = 1
             ORDER BY ordem ASC, nome ASC"
        );

        foreach ($templates as &$template) {
            if ($template['permissoes_requeridas']) {
                $template['permissoes_requeridas'] = json_decode($template['permissoes_requeridas'], true);
            }
            if ($template['config_layout']) {
                $template['config_layout'] = json_decode($template['config_layout'], true);
            }
        }

        return $templates;
    }

    /**
     * Lista templates por categoria
     */
    public function listarPorCategoria(string $categoria, int $colaboradorId): array
    {
        $templates = $this->db->buscarTodos(
            "SELECT * FROM dashboard_templates
             WHERE categoria = ? AND ativo = 1
             ORDER BY ordem ASC, nome ASC",
            [$categoria]
        );

        foreach ($templates as &$template) {
            if ($template['permissoes_requeridas']) {
                $template['permissoes_requeridas'] = json_decode($template['permissoes_requeridas'], true);
            }
            if ($template['config_layout']) {
                $template['config_layout'] = json_decode($template['config_layout'], true);
            }
        }

        return $templates;
    }

    /**
     * Busca template por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $template = $this->db->buscarUm(
            "SELECT * FROM dashboard_templates WHERE id = ?",
            [$id]
        );

        if ($template) {
            if ($template['permissoes_requeridas']) {
                $template['permissoes_requeridas'] = json_decode($template['permissoes_requeridas'], true);
            }
            if ($template['config_layout']) {
                $template['config_layout'] = json_decode($template['config_layout'], true);
            }
        }

        return $template;
    }

    /**
     * Busca template por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $template = $this->db->buscarUm(
            "SELECT * FROM dashboard_templates WHERE codigo = ?",
            [$codigo]
        );

        if ($template) {
            if ($template['permissoes_requeridas']) {
                $template['permissoes_requeridas'] = json_decode($template['permissoes_requeridas'], true);
            }
            if ($template['config_layout']) {
                $template['config_layout'] = json_decode($template['config_layout'], true);
            }
        }

        return $template;
    }

    /**
     * Valida permissões do colaborador para usar template
     */
    public function validarPermissoes(int $templateId, int $colaboradorId): bool
    {
        $template = $this->buscarPorId($templateId);
        if (!$template) {
            return false;
        }

        // Se não tem permissões requeridas, permite
        if (empty($template['permissoes_requeridas'])) {
            return true;
        }

        // TODO: Verificar permissões do colaborador no sistema RBAC
        return true;
    }
}
