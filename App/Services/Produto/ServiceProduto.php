<?php

namespace App\Services\Produto;

use App\Models\Produtos\ModelProdutos;
use App\Core\Autenticacao;
use App\Core\BancoDados;

/**
 * Service para gerenciar lógica de negócio de Produtos
 * Coordena Produto + Fornecedores + Valores + Variações com transações
 */
class ServiceProduto
{
    private ModelProdutos $model;
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelProdutos();
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Cria produto
     */
    public function criar(array $dados): array
    {
        // Validações de negócio
        $this->validarDuplicatas($dados);

        // Enriquece com usuário
        $dados['colaborador_id'] = $this->obterUsuarioAtual();

        // Cria produto
        $produtoId = $this->model->criar($dados);

        // Retorna produto completo
        return $this->model->buscarPorId($produtoId);
    }

    /**
     * Atualiza produto
     */
    public function atualizar(int $id, array $dados): array
    {
        // Verifica existência
        $this->verificarExistencia($id);

        // Validações de negócio
        $this->validarDuplicatasParaAtualizacao($id, $dados);

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Atualiza
        $this->model->atualizar($id, $dados, $usuarioId);

        // Retorna produto atualizado
        return $this->model->buscarPorId($id);
    }

    /**
     * Remove produto
     */
    public function deletar(int $id): bool
    {
        // Verifica existência
        $this->verificarExistencia($id);

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Deleta
        return $this->model->deletar($id, $usuarioId);
    }

    /**
     * Busca produto por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Lista produtos
     */
    public function listar(array $filtros): array
    {
        return $this->model->listar($filtros);
    }

    /**
     * Conta produtos
     */
    public function contar(array $filtros): int
    {
        return $this->model->contar($filtros);
    }

    /**
     * Obtém estatísticas
     */
    public function obterEstatisticas(): array
    {
        return $this->model->obterEstatisticas();
    }

    // =============== MÉTODOS PRIVADOS ===============

    /**
     * Valida duplicatas (external_id e codigo_interno)
     */
    private function validarDuplicatas(array $dados): void
    {
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'])) {
                throw new \Exception('External ID já cadastrado no sistema');
            }
        }

        if (isset($dados['codigo_interno']) && !empty($dados['codigo_interno'])) {
            if ($this->model->codigoInternoExiste($dados['codigo_interno'])) {
                throw new \Exception('Código interno já cadastrado no sistema');
            }
        }
    }

    /**
     * Valida duplicatas para atualização
     */
    private function validarDuplicatasParaAtualizacao(int $id, array $dados): void
    {
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'], $id)) {
                throw new \Exception('External ID já cadastrado em outro produto');
            }
        }

        if (isset($dados['codigo_interno']) && !empty($dados['codigo_interno'])) {
            if ($this->model->codigoInternoExiste($dados['codigo_interno'], $id)) {
                throw new \Exception('Código interno já cadastrado em outro produto');
            }
        }
    }

    /**
     * Verifica se produto existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Produto não encontrado');
        }
    }

    /**
     * Obtém ID do usuário autenticado
     */
    private function obterUsuarioAtual(): ?int
    {
        $usuario = $this->auth->obterUsuarioAutenticado();
        return $usuario['id'] ?? null;
    }
}
