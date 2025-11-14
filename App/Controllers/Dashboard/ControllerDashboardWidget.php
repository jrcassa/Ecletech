<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\Dashboard\ModelDashboard;
use App\Models\Dashboard\ModelDashboardWidget;
use App\Services\Dashboard\ServiceWidget;
use App\Services\Dashboard\ServiceWidgetDados;

/**
 * Controller para gerenciar widgets dos dashboards
 */
class ControllerDashboardWidget extends BaseController
{
    private ModelDashboard $modelDashboard;
    private ModelDashboardWidget $model;
    private ServiceWidget $service;
    private ServiceWidgetDados $serviceDados;

    public function __construct()
    {
        $this->modelDashboard = new ModelDashboard();
        $this->model = new ModelDashboardWidget();
        $this->service = new ServiceWidget();
        $this->serviceDados = new ServiceWidgetDados();
    }

    /**
     * Lista widgets de um dashboard
     */
    public function listar(string $dashboardId): void
    {
        try {
            if (!$this->validarId($dashboardId, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();

            // Verifica propriedade do dashboard
            if (!$this->modelDashboard->validarPropriedade((int) $dashboardId, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            $widgets = $this->model->listarPorDashboard((int) $dashboardId);

            $this->sucesso($widgets, 'Widgets listados com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Adiciona widget ao dashboard
     */
    public function adicionar(string $dashboardId): void
    {
        try {
            if (!$this->validarId($dashboardId, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            // Verifica propriedade do dashboard
            if (!$this->modelDashboard->validarPropriedade((int) $dashboardId, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            if (!$this->validarCamposObrigatorios($dados, ['widget_tipo_id'])) {
                return;
            }

            // Valida e mescla configuração com padrões
            if (isset($dados['config'])) {
                $dados['config'] = $this->service->validarConfiguracao(
                    $dados['widget_tipo_id'],
                    $dados['config']
                );
            }

            $widgetId = $this->model->adicionar((int) $dashboardId, $dados, $colaboradorId);

            $widget = $this->model->buscarPorId($widgetId);

            $this->sucesso($widget, 'Widget adicionado com sucesso', 201);
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Atualiza widget
     */
    public function atualizar(string $widgetId): void
    {
        try {
            if (!$this->validarId($widgetId, 'Widget')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            // Busca widget
            $widget = $this->model->buscarPorId((int) $widgetId);
            if (!$widget) {
                $this->erro('Widget não encontrado', 404);
                return;
            }

            // Verifica propriedade do dashboard
            if (!$this->modelDashboard->validarPropriedade($widget['dashboard_id'], $colaboradorId)) {
                $this->erro('Sem permissão para alterar este widget', 403);
                return;
            }

            $sucesso = $this->model->atualizar((int) $widgetId, $dados, $colaboradorId);

            if ($sucesso) {
                $widget = $this->model->buscarPorId((int) $widgetId);
                $this->sucesso($widget, 'Widget atualizado com sucesso');
            } else {
                $this->erro('Erro ao atualizar widget', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Remove widget
     */
    public function remover(string $widgetId): void
    {
        try {
            if (!$this->validarId($widgetId, 'Widget')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();

            // Busca widget
            $widget = $this->model->buscarPorId((int) $widgetId);
            if (!$widget) {
                $this->erro('Widget não encontrado', 404);
                return;
            }

            // Verifica propriedade do dashboard
            if (!$this->modelDashboard->validarPropriedade($widget['dashboard_id'], $colaboradorId)) {
                $this->erro('Sem permissão para remover este widget', 403);
                return;
            }

            $sucesso = $this->model->remover((int) $widgetId, $colaboradorId);

            if ($sucesso) {
                $this->sucesso(null, 'Widget removido com sucesso');
            } else {
                $this->erro('Erro ao remover widget', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Atualiza posições de múltiplos widgets
     */
    public function atualizarPosicoes(string $dashboardId): void
    {
        try {
            if (!$this->validarId($dashboardId, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            // Verifica propriedade do dashboard
            if (!$this->modelDashboard->validarPropriedade((int) $dashboardId, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            if (!isset($dados['widgets']) || !is_array($dados['widgets'])) {
                $this->erro('Array de widgets é obrigatório', 400);
                return;
            }

            $sucesso = $this->model->atualizarPosicoes($dados['widgets'], $colaboradorId);

            if ($sucesso) {
                $this->sucesso(null, 'Posições atualizadas com sucesso');
            } else {
                $this->erro('Erro ao atualizar posições', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Obtém dados do widget
     */
    public function obterDados(string $widgetId): void
    {
        try {
            if (!$this->validarId($widgetId, 'Widget')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();

            // Busca widget
            $widget = $this->model->buscarPorId((int) $widgetId);
            if (!$widget) {
                $this->erro('Widget não encontrado', 404);
                return;
            }

            // Verifica propriedade do dashboard
            if (!$this->modelDashboard->validarPropriedade($widget['dashboard_id'], $colaboradorId)) {
                $this->erro('Sem permissão para acessar este widget', 403);
                return;
            }

            // Obtém filtros opcionais da query string
            $filtros = $_GET['filtros'] ?? [];

            // Busca dados do widget
            $dados = $this->serviceDados->obterDados(
                $widget['widget_tipo_codigo'],
                $colaboradorId,
                $widget['config'] ?? [],
                $filtros
            );

            $this->sucesso($dados, 'Dados do widget obtidos');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }
}
