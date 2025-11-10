<?php

namespace App\Controllers\Permissao;

use App\Models\Colaborador\ModelColaboradorPermission;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controlador para gerenciar permissões
 */
class ControllerPermissao
{
    private ModelColaboradorPermission $model;

    public function __construct()
    {
        $this->model = new ModelColaboradorPermission();
    }

    /**
     * Lista todas as permissões
     */
    public function listar(): void
    {
        try {
            $filtros = [];

            if (isset($_GET['modulo'])) {
                $filtros['modulo'] = $_GET['modulo'];
            }

            if (isset($_GET['ativo'])) {
                $filtros['ativo'] = (int) $_GET['ativo'];
            }

            if (isset($_GET['busca'])) {
                $filtros['busca'] = $_GET['busca'];
            }

            $permissoes = $this->model->listar($filtros);

            AuxiliarResposta::sucesso($permissoes);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Busca uma permissão por ID
     */
    public function buscar(int $id): void
    {
        try {
            $permissao = $this->model->buscarPorId($id);

            if (!$permissao) {
                AuxiliarResposta::naoEncontrado('Permissão não encontrada');
                return;
            }

            AuxiliarResposta::sucesso($permissao);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Lista permissões agrupadas por módulo
     */
    public function listarPorModulo(): void
    {
        try {
            $permissoes = $this->model->listar(['ativo' => 1]);
            $agrupadas = [];

            foreach ($permissoes as $permissao) {
                $modulo = $permissao['modulo'] ?? 'geral';
                if (!isset($agrupadas[$modulo])) {
                    $agrupadas[$modulo] = [];
                }
                $agrupadas[$modulo][] = $permissao;
            }

            AuxiliarResposta::sucesso($agrupadas);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }
}
