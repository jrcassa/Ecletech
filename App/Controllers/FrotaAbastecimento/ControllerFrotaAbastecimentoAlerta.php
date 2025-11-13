<?php

namespace App\Controllers\FrotaAbastecimento;

use App\Controllers\BaseController;
use App\Models\FrotaAbastecimento\ModelFrotaAbastecimentoAlerta;

/**
 * Controller para gerenciar alertas de abastecimentos
 */
class ControllerFrotaAbastecimentoAlerta extends BaseController
{
    private ModelFrotaAbastecimentoAlerta $model;

    public function __construct()
    {
        $this->model = new ModelFrotaAbastecimentoAlerta();
    }

    /**
     * Lista todos os alertas com filtros
     */
    public function listar(): void
    {
        try {
            $filtros = [
                'abastecimento_id' => $_GET['abastecimento_id'] ?? null,
                'frota_id' => $_GET['frota_id'] ?? null,
                'tipo_alerta' => $_GET['tipo_alerta'] ?? null,
                'severidade' => $_GET['severidade'] ?? null,
                'visualizado' => isset($_GET['visualizado']) ? (bool) $_GET['visualizado'] : null,
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null
            ];

            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 20);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            $alertas = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset'])));

            $this->paginado(
                $alertas,
                $total,
                $paginaAtual,
                $porPagina,
                'Alertas listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca alerta por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $alerta = $this->model->buscarPorId((int) $id);

            if (!$alerta) {
                $this->naoEncontrado('Alerta não encontrado');
                return;
            }

            $this->sucesso($alerta, 'Alerta encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém alertas críticos não visualizados
     */
    public function criticosNaoVisualizados(): void
    {
        try {
            $alertas = $this->model->buscarCriticosNaoVisualizados();

            $this->sucesso($alertas, 'Alertas críticos obtidos com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém alertas de um abastecimento específico
     */
    public function porAbastecimento(string $abastecimento_id): void
    {
        try {
            if (!$this->validarId($abastecimento_id)) { return; }

            $alertas = $this->model->buscarPorAbastecimento((int) $abastecimento_id);

            $this->sucesso($alertas, 'Alertas do abastecimento obtidos com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém alertas de uma frota específica
     */
    public function porFrota(string $frota_id): void
    {
        try {
            if (!$this->validarId($frota_id)) { return; }

            $limite = (int) ($_GET['limite'] ?? 20);
            $alertas = $this->model->buscarPorFrota((int) $frota_id, $limite);

            $this->sucesso($alertas, 'Alertas da frota obtidos com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Marca alerta como visualizado
     */
    public function marcarVisualizado(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $usuarioLogado = $this->obterUsuarioAutenticado();

            $alerta = $this->model->buscarPorId((int) $id);
            if (!$alerta) {
                $this->naoEncontrado('Alerta não encontrado');
                return;
            }

            $this->model->marcarVisualizado((int) $id, $usuarioLogado['id']);

            $this->sucesso(['id' => (int) $id], 'Alerta marcado como visualizado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Marca múltiplos alertas como visualizados
     */
    public function marcarVariosVisualizados(): void
    {
        try {
            $dados = $this->obterDados();

            if (!isset($dados['ids']) || !is_array($dados['ids'])) {
                $this->erro('Forneça um array de IDs');
                return;
            }

            $usuarioLogado = $this->obterUsuarioAutenticado();

            foreach ($dados['ids'] as $id) {
                $this->model->marcarVisualizado((int) $id, $usuarioLogado['id']);
            }

            $this->sucesso(['total' => count($dados['ids'])], 'Alertas marcados como visualizados');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém dashboard de alertas
     */
    public function dashboard(): void
    {
        try {
            $dashboard = $this->model->obterDashboard();

            $this->sucesso($dashboard, 'Dashboard de alertas obtido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas de alertas por tipo
     */
    public function estatisticasPorTipo(): void
    {
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null
            ];

            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            $estatisticas = $this->model->obterEstatisticasPorTipo($filtros);

            $this->sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
