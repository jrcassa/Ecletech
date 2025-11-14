<?php

use App\Controllers\Dashboard\ControllerDashboard;
use App\Controllers\Dashboard\ControllerDashboardWidget;
use App\Controllers\Dashboard\ControllerWidgetTipo;
use App\Controllers\Dashboard\ControllerDashboardTemplate;

return function($router) {
    // Grupo de rotas de dashboards (requer autenticação)
    $router->grupo([
        'prefixo' => 'dashboard',
        'middleware' => ['auth']
    ], function($router) {

        // ============================================
        // ROTAS DE DASHBOARDS
        // ============================================

        // GET /dashboard - Listar dashboards do usuário
        $router->get('/', [ControllerDashboard::class, 'listar']);

        // GET /dashboard/padrao - Obter dashboard padrão
        $router->get('/padrao', [ControllerDashboard::class, 'obterPadrao']);

        // POST /dashboard - Criar novo dashboard
        $router->post('/', [ControllerDashboard::class, 'criar']);

        // POST /dashboard/from-template - Criar dashboard a partir de template
        $router->post('/from-template', [ControllerDashboard::class, 'criarDeTemplate']);

        // GET /dashboard/{id} - Buscar dashboard por ID
        $router->get('/{id}', [ControllerDashboard::class, 'obter']);

        // PUT /dashboard/{id} - Atualizar dashboard
        $router->put('/{id}', [ControllerDashboard::class, 'atualizar']);

        // DELETE /dashboard/{id} - Deletar dashboard
        $router->delete('/{id}', [ControllerDashboard::class, 'deletar']);

        // POST /dashboard/{id}/padrao - Definir como padrão
        $router->post('/{id}/padrao', [ControllerDashboard::class, 'definirPadrao']);

        // POST /dashboard/{id}/duplicar - Duplicar dashboard
        $router->post('/{id}/duplicar', [ControllerDashboard::class, 'duplicar']);

        // ============================================
        // ROTAS DE WIDGETS
        // ============================================

        // GET /dashboard/{id}/widgets - Listar widgets do dashboard
        $router->get('/{id}/widgets', [ControllerDashboardWidget::class, 'listar']);

        // POST /dashboard/{id}/widgets - Adicionar widget ao dashboard
        $router->post('/{id}/widgets', [ControllerDashboardWidget::class, 'adicionar']);

        // PUT /dashboard/{id}/widgets/posicoes - Atualizar posições de widgets
        $router->put('/{id}/widgets/posicoes', [ControllerDashboardWidget::class, 'atualizarPosicoes']);

        // GET /dashboard/widgets/{widgetId}/dados - Obter dados do widget
        $router->get('/widgets/{widgetId}/dados', [ControllerDashboardWidget::class, 'obterDados']);

        // PUT /dashboard/widgets/{widgetId} - Atualizar widget
        $router->put('/widgets/{widgetId}', [ControllerDashboardWidget::class, 'atualizar']);

        // DELETE /dashboard/widgets/{widgetId} - Remover widget
        $router->delete('/widgets/{widgetId}', [ControllerDashboardWidget::class, 'remover']);

        // ============================================
        // ROTAS DE TIPOS DE WIDGETS
        // ============================================

        // GET /dashboard/widget-tipos - Listar tipos de widgets disponíveis
        $router->get('/widget-tipos', [ControllerWidgetTipo::class, 'listar']);

        // GET /dashboard/widget-tipos/categorias - Listar categorias de widgets
        $router->get('/widget-tipos/categorias', [ControllerWidgetTipo::class, 'listarCategorias']);

        // GET /dashboard/widget-tipos/{id} - Obter tipo de widget por ID
        $router->get('/widget-tipos/{id}', [ControllerWidgetTipo::class, 'obter']);

        // ============================================
        // ROTAS DE TEMPLATES
        // ============================================

        // GET /dashboard/templates - Listar templates disponíveis
        $router->get('/templates', [ControllerDashboardTemplate::class, 'listar']);

        // GET /dashboard/templates/{id} - Obter template por ID
        $router->get('/templates/{id}', [ControllerDashboardTemplate::class, 'obter']);
    });
};
