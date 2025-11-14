<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\Dashboard\ModelWidgetTipo;

class ControllerWidgetTipo extends BaseController
{
    private ModelWidgetTipo $model;

    public function __construct()
    {
        $this->model = new ModelWidgetTipo();
    }

    public function listar(): void
    {
        try {
            $colaboradorId = $this->obterIdUsuarioAutenticado();
            $widgets = $this->model->listarDisponiveis($colaboradorId);
            $this->sucesso($widgets, 'Tipos de widgets listados');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    public function listarCategorias(): void
    {
        try {
            $categorias = $this->model->listarCategorias();
            $this->sucesso($categorias, 'Categorias listadas');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    public function obter(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Widget Tipo')) {
                return;
            }

            $widget = $this->model->buscarPorId((int) $id);
            if (!$this->validarExistencia($widget, 'Widget Tipo')) {
                return;
            }

            $this->sucesso($widget, 'Tipo de widget encontrado');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }
}
