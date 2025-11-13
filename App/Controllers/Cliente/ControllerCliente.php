<?php

namespace App\Controllers\Cliente;

use App\Controllers\BaseController;
use App\Services\Cliente\ServiceCliente;

/**
 * Controller para gerenciar clientes
 */
class ControllerCliente extends BaseController
{
    private ServiceCliente $service;

    public function __construct()
    {
        $this->service = new ServiceCliente();
    }

    /**
     * Lista todos os clientes
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação usando BaseController
            $filtros = $this->obterFiltros(['ativo', 'tipo_pessoa', 'busca']);
            $paginacao = $this->obterPaginacao();
            $ordenacao = $this->obterOrdenacao('nome');

            // Combina todos os parâmetros
            $parametros = $this->mergeArrays($filtros, $paginacao, $ordenacao);

            // Busca dados via Service
            $clientes = $this->service->listar($parametros);
            $total = $this->service->contar(array_diff_key($parametros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $clientes,
                $total,
                $paginacao['pagina'],
                $paginacao['por_pagina'],
                'Clientes listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Busca um cliente por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Cliente')) {
                return;
            }

            $cliente = $this->service->buscarComRelacionamentos((int) $id);

            if (!$this->validarExistencia($cliente, 'Cliente')) {
                return;
            }

            $this->sucesso($cliente, 'Cliente encontrado');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Cria um novo cliente
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica usando BaseController
            if (!$this->validar($dados, [
                'tipo_pessoa' => 'obrigatorio|em:PF,PJ',
                'nome' => 'obrigatorio|min:3|max:200'
            ])) {
                return;
            }

            // Validações condicionais delegadas ao Service
            // O Service tratará validações de negócio complexas

            // Delega para o Service (validações de negócio + criação + transação)
            $cliente = $this->service->criarCompleto($dados);

            $this->criado($cliente, 'Cliente cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Atualiza um cliente
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Cliente')) {
                return;
            }

            $dados = $this->obterDados();

            // Delega para o Service (validações de negócio + atualização + transação)
            // O Service tratará todas as validações complexas e relacionamentos
            $cliente = $this->service->atualizarCompleto((int) $id, $dados);

            $this->sucesso($cliente, 'Cliente atualizado com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Deleta um cliente (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id, 'Cliente')) {
                return;
            }

            // Deleta via Service
            $this->service->deletar((int) $id);

            $this->sucesso(null, 'Cliente removido com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }

    /**
     * Obtém estatísticas dos clientes
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas dos clientes obtidas com sucesso');
        } catch (\Exception $e) {
            $this->tratarErro($e);
        }
    }
}
