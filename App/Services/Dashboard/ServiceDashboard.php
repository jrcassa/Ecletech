<?php

namespace App\Services\Dashboard;

use App\Models\Dashboard\ModelDashboard;
use App\Models\Dashboard\ModelDashboardWidget;
use App\Models\Dashboard\ModelDashboardTemplate;
use App\Models\Dashboard\ModelWidgetTipo;

/**
 * Service para lógica de negócio de dashboards
 */
class ServiceDashboard
{
    private ModelDashboard $modelDashboard;
    private ModelDashboardWidget $modelWidget;
    private ModelDashboardTemplate $modelTemplate;
    private ModelWidgetTipo $modelWidgetTipo;

    public function __construct()
    {
        $this->modelDashboard = new ModelDashboard();
        $this->modelWidget = new ModelDashboardWidget();
        $this->modelTemplate = new ModelDashboardTemplate();
        $this->modelWidgetTipo = new ModelWidgetTipo();
    }

    /**
     * Cria dashboard padrão para colaborador (template_geral)
     */
    public function criarDashboardPadrao(int $colaboradorId): ?int
    {
        $template = $this->modelTemplate->buscarPorCodigo('template_geral');
        if (!$template) {
            return null;
        }

        return $this->aplicarTemplate('template_geral', $colaboradorId, 'Meu Dashboard');
    }

    /**
     * Aplica template para criar novo dashboard
     */
    public function aplicarTemplate(string $templateCodigo, int $colaboradorId, string $nomeDashboard): ?int
    {
        $template = $this->modelTemplate->buscarPorCodigo($templateCodigo);
        if (!$template) {
            throw new \Exception("Template não encontrado: {$templateCodigo}");
        }

        // Valida permissões
        if (!$this->modelTemplate->validarPermissoes($template['id'], $colaboradorId)) {
            throw new \Exception("Sem permissão para usar este template");
        }

        // Cria dashboard
        $dashboardId = $this->modelDashboard->criar($colaboradorId, [
            'nome' => $nomeDashboard,
            'descricao' => $template['descricao'],
            'icone' => $template['icone'],
            'cor' => $template['cor'],
            'is_padrao' => 0
        ]);

        // Adiciona widgets do template
        $configLayout = $template['config_layout'];
        if (!empty($configLayout['widgets'])) {
            foreach ($configLayout['widgets'] as $widgetConfig) {
                // Busca tipo do widget pelo código
                $widgetTipo = $this->modelWidgetTipo->buscarPorCodigo($widgetConfig['widget_tipo_codigo']);
                if (!$widgetTipo) {
                    continue;
                }

                // Adiciona widget ao dashboard
                $this->modelWidget->adicionar($dashboardId, [
                    'widget_tipo_id' => $widgetTipo['id'],
                    'titulo' => $widgetConfig['titulo'] ?? $widgetTipo['nome'],
                    'config' => $widgetConfig['config'] ?? [],
                    'posicao_x' => $widgetConfig['posicao_x'] ?? 0,
                    'posicao_y' => $widgetConfig['posicao_y'] ?? 0,
                    'largura' => $widgetConfig['largura'] ?? $widgetTipo['largura_padrao'],
                    'altura' => $widgetConfig['altura'] ?? $widgetTipo['altura_padrao'],
                    'ativo' => 1
                ], $colaboradorId);
            }
        }

        return $dashboardId;
    }

    /**
     * Valida nome do dashboard (único por colaborador)
     */
    public function validarNomeDashboard(string $nome, int $colaboradorId, ?int $dashboardIdAtual = null): bool
    {
        $dashboards = $this->modelDashboard->listarPorColaborador($colaboradorId);

        foreach ($dashboards as $dashboard) {
            if ($dashboard['nome'] === $nome && $dashboard['id'] !== $dashboardIdAtual) {
                return false;
            }
        }

        return true;
    }

    /**
     * Garante que colaborador tenha pelo menos um dashboard
     */
    public function garantirDashboardPadrao(int $colaboradorId): array
    {
        // Verifica se já tem dashboard
        $dashboards = $this->modelDashboard->listarPorColaborador($colaboradorId);

        if (empty($dashboards)) {
            // Cria dashboard padrão
            $dashboardId = $this->criarDashboardPadrao($colaboradorId);

            // Define como padrão
            $this->modelDashboard->definirComoPadrao($dashboardId, $colaboradorId);

            // Retorna o dashboard criado
            return $this->modelDashboard->buscarPorId($dashboardId, $colaboradorId);
        }

        // Retorna dashboard padrão ou primeiro da lista
        $dashboardPadrao = $this->modelDashboard->obterPadrao($colaboradorId);
        if ($dashboardPadrao) {
            return $dashboardPadrao;
        }

        return $dashboards[0];
    }
}
