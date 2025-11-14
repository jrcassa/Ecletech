<?php

namespace App\Models\Dashboard;

use App\Core\BancoDados;

/**
 * Model para gerenciar tipos de widgets disponíveis
 */
class ModelWidgetTipo
{
    private BancoDados $db;

    public function __construct()
    {
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Lista todos os tipos de widgets disponíveis
     */
    public function listarDisponiveis(int $colaboradorId): array
    {
        // TODO: Filtrar por permissões do colaborador
        // Por enquanto retorna todos ativos
        $widgets = $this->db->buscarTodos(
            "SELECT * FROM widget_tipos
             WHERE ativo = 1
             ORDER BY categoria ASC, ordem ASC, nome ASC"
        );

        // Decodifica JSON
        foreach ($widgets as &$widget) {
            if (!empty($widget['permissoes_requeridas'])) {
                $widget['permissoes_requeridas'] = json_decode($widget['permissoes_requeridas'], true);
            }
            if (isset($widget['config_schema']) && !empty($widget['config_schema'])) {
                $widget['config_schema'] = json_decode($widget['config_schema'], true);
            }
            if (!empty($widget['config_padrao'])) {
                $widget['config_padrao'] = json_decode($widget['config_padrao'], true);
            }
        }

        return $widgets;
    }

    /**
     * Lista widgets por categoria
     */
    public function listarPorCategoria(string $categoria, int $colaboradorId): array
    {
        $widgets = $this->db->buscarTodos(
            "SELECT * FROM widget_tipos
             WHERE categoria = ? AND ativo = 1
             ORDER BY ordem ASC, nome ASC",
            [$categoria]
        );

        foreach ($widgets as &$widget) {
            if (!empty($widget['permissoes_requeridas'])) {
                $widget['permissoes_requeridas'] = json_decode($widget['permissoes_requeridas'], true);
            }
            if (isset($widget['config_schema']) && !empty($widget['config_schema'])) {
                $widget['config_schema'] = json_decode($widget['config_schema'], true);
            }
            if (!empty($widget['config_padrao'])) {
                $widget['config_padrao'] = json_decode($widget['config_padrao'], true);
            }
        }

        return $widgets;
    }

    /**
     * Lista todas as categorias de widgets
     */
    public function listarCategorias(): array
    {
        return $this->db->buscarTodos(
            "SELECT DISTINCT categoria
             FROM widget_tipos
             WHERE ativo = 1
             ORDER BY categoria ASC"
        );
    }

    /**
     * Busca widget por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $widget = $this->db->buscarUm(
            "SELECT * FROM widget_tipos WHERE id = ?",
            [$id]
        );

        if ($widget) {
            if (!empty($widget['permissoes_requeridas'])) {
                $widget['permissoes_requeridas'] = json_decode($widget['permissoes_requeridas'], true);
            }
            if (isset($widget['config_schema']) && !empty($widget['config_schema'])) {
                $widget['config_schema'] = json_decode($widget['config_schema'], true);
            }
            if (!empty($widget['config_padrao'])) {
                $widget['config_padrao'] = json_decode($widget['config_padrao'], true);
            }
        }

        return $widget;
    }

    /**
     * Busca widget por código
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $widget = $this->db->buscarUm(
            "SELECT * FROM widget_tipos WHERE codigo = ?",
            [$codigo]
        );

        if ($widget) {
            if (!empty($widget['permissoes_requeridas'])) {
                $widget['permissoes_requeridas'] = json_decode($widget['permissoes_requeridas'], true);
            }
            if (isset($widget['config_schema']) && !empty($widget['config_schema'])) {
                $widget['config_schema'] = json_decode($widget['config_schema'], true);
            }
            if (!empty($widget['config_padrao'])) {
                $widget['config_padrao'] = json_decode($widget['config_padrao'], true);
            }
        }

        return $widget;
    }

    /**
     * Valida se colaborador tem permissões para usar o widget
     * TODO: Implementar verificação real de permissões RBAC
     */
    public function validarPermissoes(int $widgetTipoId, int $colaboradorId): bool
    {
        $widget = $this->buscarPorId($widgetTipoId);
        if (!$widget) {
            return false;
        }

        // Se não tem permissões requeridas, permite
        if (empty($widget['permissoes_requeridas'])) {
            return true;
        }

        // TODO: Verificar permissões do colaborador no sistema RBAC
        // Por enquanto retorna true
        return true;
    }
}
