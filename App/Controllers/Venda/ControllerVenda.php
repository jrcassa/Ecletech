<?php

namespace App\Controllers\Venda;

use App\Controllers\BaseController;
use App\Services\Venda\ServiceVenda;

/**
 * Controller para gerenciar vendas/pedidos
 */
class ControllerVenda extends BaseController
{
    private ServiceVenda $service;

    public function __construct()
    {
        $this->service = new ServiceVenda();
    }

    /**
     * Lista todas as vendas
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = $this->obterFiltros([
                'cliente_id', 'vendedor_id', 'situacao_venda_id',
                'situacao_financeiro', 'situacao_estoque', 'canal_venda',
                'loja_id', 'data_inicio', 'data_fim', 'ativo', 'busca'
            ]);
            $paginacao = $this->obterPaginacao();
            $ordenacao = $this->obterOrdenacao('data_venda');

            // Combina todos os parâmetros
            $parametros = $this->mergeArrays($filtros, $paginacao, $ordenacao);

            // Busca dados via Service
            $vendas = $this->service->listarVendas($parametros);
            $total = $this->service->contarVendas(
                array_diff_key($parametros, array_flip(['limite', 'offset', 'ordenacao', 'direcao']))
            );

            $this->paginado(
                $vendas,
                $total,
                $paginacao['pagina'],
                $paginacao['por_pagina'],
                'Vendas listadas com sucesso'
            );
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Busca uma venda por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Venda')) {
                return;
            }

            $venda = $this->service->buscarVendaCompleta((int) $id);

            if (!$this->validarExistencia($venda, 'Venda')) {
                return;
            }

            $this->sucesso($venda, 'Venda encontrada');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Cria uma nova venda
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica
            if (!$this->validar($dados, [
                'data_venda' => 'obrigatorio',
                'itens' => 'obrigatorio'
            ])) {
                return;
            }

            // Obtém ID do usuário autenticado
            $usuarioId = $this->obterIdUsuarioAutenticado();

            // Delega para o Service (validações complexas + transação)
            $venda = $this->service->criarVendaCompleta($dados, $usuarioId);

            $this->criado($venda, 'Venda cadastrada com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Atualiza uma venda
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Venda')) {
                return;
            }

            $dados = $this->obterDados();

            // Obtém ID do usuário autenticado
            $usuarioId = $this->obterIdUsuarioAutenticado();

            // Delega para o Service
            $venda = $this->service->atualizarVendaCompleta((int) $id, $dados, $usuarioId);

            $this->sucesso($venda, 'Venda atualizada com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Deleta uma venda (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Venda')) {
                return;
            }

            // Obtém ID do usuário autenticado
            $usuarioId = $this->obterIdUsuarioAutenticado();

            // Deleta via Service
            $this->service->deletarVenda((int) $id, $usuarioId);

            $this->sucesso(null, 'Venda removida com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Atualiza situação financeira de uma venda
     */
    public function atualizarSituacaoFinanceira(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Venda')) {
                return;
            }

            $this->service->atualizarSituacaoFinanceira((int) $id);

            $this->sucesso(null, 'Situação financeira atualizada com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Recalcula os totais de uma venda baseado nos itens
     */
    public function recalcularTotais(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Venda')) {
                return;
            }

            $totais = $this->service->recalcularTotaisVenda((int) $id);

            $this->sucesso($totais, 'Totais recalculados com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Corrige valores de frete/desconto zerados e recalcula
     */
    public function corrigirValores(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Venda')) {
                return;
            }

            $usuarioId = $this->obterIdUsuarioAutenticado();
            $resultado = $this->service->corrigirValoresVenda((int) $id, $usuarioId);

            $this->sucesso($resultado, 'Valores corrigidos com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }
}
