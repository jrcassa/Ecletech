<?php

namespace App\Services\FormaDePagamento;

use App\Models\FormaDePagamento\ModelFormaDePagamento;
use App\Core\Autenticacao;

/**
 * Service para gerenciar lógica de negócio de Formas de Pagamento
 */
class ServiceFormaDePagamento
{
    private ModelFormaDePagamento $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelFormaDePagamento();
        $this->auth = new Autenticacao();
    }

    /**
     * Cria uma forma de pagamento
     */
    public function criar(array $dados): array
    {
        // Validações de negócio
        $this->validarDuplicatas($dados);

        // Enriquece com dados do sistema
        $dados['colaborador_id'] = $this->obterUsuarioAtual();

        // Persiste
        $id = $this->model->criar($dados);

        // Retorna dados completos
        return $this->model->buscarPorId($id);
    }

    /**
     * Atualiza uma forma de pagamento
     */
    public function atualizar(int $id, array $dados): array
    {
        // Verifica se existe
        $this->verificarExistencia($id);

        // Validações de negócio
        if (isset($dados['nome']) && !empty($dados['nome'])) {
            $this->validarNomeUnico($dados['nome'], $id);
        }

        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $this->validarExternalIdUnico($dados['external_id'], $id);
        }

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Atualiza
        $this->model->atualizar($id, $dados, $usuarioId);

        // Retorna dados atualizados
        return $this->model->buscarPorId($id);
    }

    /**
     * Remove uma forma de pagamento
     */
    public function deletar(int $id): bool
    {
        // Verifica se existe
        $this->verificarExistencia($id);

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Deleta
        return $this->model->deletar($id, $usuarioId);
    }

    /**
     * Busca por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Lista com filtros e paginação
     */
    public function listar(array $filtros): array
    {
        return $this->model->listar($filtros);
    }

    /**
     * Conta total
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
     * Valida duplicatas (nome e external_id)
     */
    private function validarDuplicatas(array $dados): void
    {
        if ($this->model->nomeExiste($dados['nome'])) {
            throw new \Exception('Já existe uma forma de pagamento com este nome');
        }

        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'])) {
                throw new \Exception('External ID já cadastrado no sistema');
            }
        }
    }

    /**
     * Valida se nome é único (excluindo o próprio registro)
     */
    private function validarNomeUnico(string $nome, int $excluirId): void
    {
        if ($this->model->nomeExiste($nome, $excluirId)) {
            throw new \Exception('Já existe outra forma de pagamento com este nome');
        }
    }

    /**
     * Valida se external_id é único (excluindo o próprio registro)
     */
    private function validarExternalIdUnico(string $externalId, int $excluirId): void
    {
        if ($this->model->externalIdExiste($externalId, $excluirId)) {
            throw new \Exception('External ID já cadastrado em outra forma de pagamento');
        }
    }

    /**
     * Verifica se a forma de pagamento existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Forma de pagamento não encontrada');
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
