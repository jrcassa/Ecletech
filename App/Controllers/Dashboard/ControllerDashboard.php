<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\Dashboard\ModelDashboard;
use App\Models\Dashboard\ModelDashboardWidget;
use App\Services\Dashboard\ServiceDashboard;

/**
 * Controller para gerenciar dashboards
 */
class ControllerDashboard extends BaseController
{
    private ModelDashboard $model;
    private ModelDashboardWidget $modelWidget;
    private ServiceDashboard $service;

    public function __construct()
    {
        $this->model = new ModelDashboard();
        $this->modelWidget = new ModelDashboardWidget();
        $this->service = new ServiceDashboard();
    }

    /**
     * Lista dashboards do colaborador autenticado
     */
    public function listar(): void
    {
        try {
            $colaboradorId = $this->obterColaboradorIdAutenticado();

            $dashboards = $this->model->listarPorColaborador($colaboradorId);

            $this->sucesso($dashboards, 'Dashboards listados com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Cria novo dashboard
     */
    public function criar(): void
    {
        try {
            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            // Validação básica
            if (!$this->validarCamposObrigatorios($dados, ['nome'])) {
                return;
            }

            // Verifica se já existe dashboard com mesmo nome
            if (!$this->service->validarNomeDashboard($dados['nome'], $colaboradorId)) {
                $this->erro('Já existe um dashboard com este nome', 400);
                return;
            }

            // Verifica se deve aplicar template
            if (isset($dados['template_id']) && !empty($dados['template_id'])) {
                $dashboardId = $this->service->aplicarTemplate(
                    $dados['template_codigo'] ?? 'template_geral',
                    $colaboradorId,
                    $dados['nome']
                );
            } else {
                // Cria dashboard vazio
                $dashboardId = $this->model->criar($colaboradorId, $dados);
            }

            // Busca dashboard criado com widgets
            $dashboard = $this->model->buscarPorId($dashboardId, $colaboradorId);
            $dashboard['widgets'] = $this->modelWidget->listarPorDashboard($dashboardId);

            $this->sucesso($dashboard, 'Dashboard criado com sucesso', 201);
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Busca dashboard por ID
     */
    public function obter(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();

            $dashboard = $this->model->buscarPorId((int) $id, $colaboradorId);

            if (!$this->validarExistencia($dashboard, 'Dashboard')) {
                return;
            }

            // Adiciona widgets
            $dashboard['widgets'] = $this->modelWidget->listarPorDashboard((int) $id);

            $this->sucesso($dashboard, 'Dashboard encontrado');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Obtém dashboard padrão do usuário
     */
    public function obterPadrao(): void
    {
        try {
            $colaboradorId = $this->obterColaboradorIdAutenticado();

            // Garante que tenha pelo menos um dashboard
            $dashboard = $this->service->garantirDashboardPadrao($colaboradorId);

            // Adiciona widgets
            $dashboard['widgets'] = $this->modelWidget->listarPorDashboard($dashboard['id']);

            $this->sucesso($dashboard, 'Dashboard padrão obtido');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Atualiza dashboard
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            // Verifica se dashboard existe e pertence ao colaborador
            if (!$this->model->validarPropriedade((int) $id, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            // Valida nome único (se estiver alterando)
            if (isset($dados['nome'])) {
                if (!$this->service->validarNomeDashboard($dados['nome'], $colaboradorId, (int) $id)) {
                    $this->erro('Já existe um dashboard com este nome', 400);
                    return;
                }
            }

            $sucesso = $this->model->atualizar((int) $id, $colaboradorId, $dados);

            if ($sucesso) {
                $dashboard = $this->model->buscarPorId((int) $id, $colaboradorId);
                $this->sucesso($dashboard, 'Dashboard atualizado com sucesso');
            } else {
                $this->erro('Erro ao atualizar dashboard', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Deleta dashboard
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();

            // Verifica se dashboard existe e pertence ao colaborador
            if (!$this->model->validarPropriedade((int) $id, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            // Não permite deletar se for o único dashboard
            if ($this->model->contarPorColaborador($colaboradorId) <= 1) {
                $this->erro('Não é possível deletar o último dashboard', 400);
                return;
            }

            $sucesso = $this->model->deletar((int) $id, $colaboradorId);

            if ($sucesso) {
                $this->sucesso(null, 'Dashboard deletado com sucesso');
            } else {
                $this->erro('Erro ao deletar dashboard', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Define dashboard como padrão
     */
    public function definirPadrao(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();

            // Verifica se dashboard existe e pertence ao colaborador
            if (!$this->model->validarPropriedade((int) $id, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            $sucesso = $this->model->definirComoPadrao((int) $id, $colaboradorId);

            if ($sucesso) {
                $this->sucesso(null, 'Dashboard definido como padrão');
            } else {
                $this->erro('Erro ao definir dashboard padrão', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Duplica dashboard
     */
    public function duplicar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Dashboard')) {
                return;
            }

            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            if (!isset($dados['nome']) || empty($dados['nome'])) {
                $this->erro('Nome do novo dashboard é obrigatório', 400);
                return;
            }

            // Verifica se dashboard existe e pertence ao colaborador
            if (!$this->model->validarPropriedade((int) $id, $colaboradorId)) {
                $this->erro('Dashboard não encontrado', 404);
                return;
            }

            // Valida nome único
            if (!$this->service->validarNomeDashboard($dados['nome'], $colaboradorId)) {
                $this->erro('Já existe um dashboard com este nome', 400);
                return;
            }

            $novoDashboardId = $this->model->duplicar((int) $id, $colaboradorId, $dados['nome']);

            if ($novoDashboardId) {
                $dashboard = $this->model->buscarPorId($novoDashboardId, $colaboradorId);
                $dashboard['widgets'] = $this->modelWidget->listarPorDashboard($novoDashboardId);
                $this->sucesso($dashboard, 'Dashboard duplicado com sucesso', 201);
            } else {
                $this->erro('Erro ao duplicar dashboard', 400);
            }
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Cria dashboard a partir de template
     */
    public function criarDeTemplate(): void
    {
        try {
            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $dados = $this->obterDados();

            if (!$this->validarCamposObrigatorios($dados, ['nome', 'template_codigo'])) {
                return;
            }

            // Valida nome único
            if (!$this->service->validarNomeDashboard($dados['nome'], $colaboradorId)) {
                $this->erro('Já existe um dashboard com este nome', 400);
                return;
            }

            $dashboardId = $this->service->aplicarTemplate(
                $dados['template_codigo'],
                $colaboradorId,
                $dados['nome']
            );

            $dashboard = $this->model->buscarPorId($dashboardId, $colaboradorId);
            $dashboard['widgets'] = $this->modelWidget->listarPorDashboard($dashboardId);

            $this->sucesso($dashboard, 'Dashboard criado a partir do template', 201);
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }
}
