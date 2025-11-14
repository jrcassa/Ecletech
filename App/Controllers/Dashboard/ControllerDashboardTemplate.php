<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\Dashboard\ModelDashboardTemplate;

class ControllerDashboardTemplate extends BaseController
{
    private ModelDashboardTemplate $model;

    public function __construct()
    {
        $this->model = new ModelDashboardTemplate();
    }

    public function listar(): void
    {
        try {
            $colaboradorId = $this->obterColaboradorIdAutenticado();
            $templates = $this->model->listar($colaboradorId);
            $this->sucesso($templates, 'Templates listados');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    public function obter(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Template')) {
                return;
            }

            $template = $this->model->buscarPorId((int) $id);
            if (!$this->validarExistencia($template, 'Template')) {
                return;
            }

            $this->sucesso($template, 'Template encontrado');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }
}
