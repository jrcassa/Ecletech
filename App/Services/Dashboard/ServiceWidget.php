<?php

namespace App\Services\Dashboard;

use App\Models\Dashboard\ModelWidgetTipo;

/**
 * Service para lógica de widgets
 */
class ServiceWidget
{
    private ModelWidgetTipo $modelWidgetTipo;

    public function __construct()
    {
        $this->modelWidgetTipo = new ModelWidgetTipo();
    }

    /**
     * Valida configuração do widget contra schema
     */
    public function validarConfiguracao(int $widgetTipoId, array $config): array
    {
        $widgetTipo = $this->modelWidgetTipo->buscarPorId($widgetTipoId);
        if (!$widgetTipo) {
            throw new \Exception("Tipo de widget não encontrado");
        }

        // TODO: Implementar validação contra config_schema
        // Por enquanto retorna config mesclada com padrões

        $configPadrao = $widgetTipo['config_padrao'] ?? [];
        return array_merge($configPadrao, $config);
    }
}
