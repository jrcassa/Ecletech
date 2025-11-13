<?php

namespace App\Controllers\Produtos;

use App\Controllers\BaseController;

use App\Services\Produto\ServiceProduto;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar produtos (estrutura refatorada - 2 tabelas)
 */
class ControllerProdutos extends BaseController
{
    private ServiceProduto $service;

    public function __construct()
    {
        $this->service = new ServiceProduto();
    }

    /**
     * Lista todos os produtos
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'grupo_id' => $_GET['grupo_id'] ?? null,
                'busca' => $_GET['busca'] ?? null,
                'ordenacao' => $_GET['ordenacao'] ?? 'nome',
                'direcao' => $_GET['direcao'] ?? 'ASC'
            ];

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            // Paginação
            $paginaAtual = (int) ($_GET['pagina'] ?? 1);
            $porPagina = (int) ($_GET['por_pagina'] ?? 20);
            $offset = ($paginaAtual - 1) * $porPagina;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = $offset;

            // Busca dados via Service
            $produtos = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $produtos,
                $total,
                $paginaAtual,
                $porPagina,
                'Produtos listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um produto por ID com relacionamentos
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $produto = $this->service->buscarPorId((int) $id);

            if (!$produto) {
                $this->naoEncontrado('Produto não encontrado');
                return;
            }

            $this->sucesso($produto, 'Produto encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo produto
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:255'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + criação)
            $produto = $this->service->criar($dados);

            $this->criado($produto, 'Produto cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um produto
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            // Validação de formato HTTP
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:255';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização)
            $produto = $this->service->atualizar((int) $id, $dados);

            $this->sucesso($produto, 'Produto atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um produto (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Delega para o Service
            $this->service->deletar((int) $id);

            $this->sucesso(null, 'Produto removido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos produtos
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas dos produtos obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
