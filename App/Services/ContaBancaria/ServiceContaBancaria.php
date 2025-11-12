<?php

namespace App\Services\ContaBancaria;

use App\Models\ContaBancaria\ModelContaBancaria;
use App\Core\Autenticacao;

/**
 * Service para gerenciar lógica de negócio de Contas Bancárias
 */
class ServiceContaBancaria
{
    private ModelContaBancaria $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelContaBancaria();
        $this->auth = new Autenticacao();
    }

    /**
     * Cria uma conta bancária
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
     * Atualiza uma conta bancária
     */
    public function atualizar(int $id, array $dados): array
    {
        // Verifica se existe
        $this->verificarExistencia($id);

        // Validações de negócio
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
     * Remove uma conta bancária
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
     * Valida duplicatas (external_id)
     */
    private function validarDuplicatas(array $dados): void
    {
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'])) {
                throw new \Exception('External ID já cadastrado no sistema');
            }
        }
    }

    /**
     * Valida se external_id é único (excluindo o próprio registro)
     */
    private function validarExternalIdUnico(string $externalId, int $excluirId): void
    {
        if ($this->model->externalIdExiste($externalId, $excluirId)) {
            throw new \Exception('External ID já cadastrado em outra conta bancária');
        }
    }

    /**
     * Verifica se a conta bancária existe
     */
    private function verificarExistencia(int $id): void
    {
        if (!$this->model->buscarPorId($id)) {
            throw new \Exception('Conta bancária não encontrada');
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
