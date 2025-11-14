<?php

namespace App\Services\Recebimento;

use App\Models\Recebimento\ModelRecebimento;
use App\Core\Autenticacao;

/**
 * Service para gerenciar lógica de negócio de Recebimentos
 */
class ServiceRecebimento
{
    private ModelRecebimento $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelRecebimento();
        $this->auth = new Autenticacao();
    }

    /**
     * Cria um recebimento
     */
    public function criar(array $dados): array
    {
        // Validações de negócio
        $this->validarDadosRecebimento($dados, false);

        // Enriquece com dados do sistema
        $dados['usuario_id'] = $this->obterUsuarioAtual();

        // Persiste
        $id = $this->model->criar($dados);

        // Retorna dados completos
        return $this->model->buscarPorId($id);
    }

    /**
     * Atualiza um recebimento
     */
    public function atualizar(int $id, array $dados): array
    {
        // Verifica se existe
        $this->verificarExistencia($id);

        // Validações de negócio
        $this->validarDadosRecebimento($dados, true);

        // Verifica se external_id é único (se fornecido)
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            if ($this->model->externalIdExiste($dados['external_id'], $id)) {
                throw new \Exception('External ID já cadastrado em outro recebimento');
            }
        }

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Atualiza
        $this->model->atualizar($id, $dados, $usuarioId);

        // Retorna dados atualizados
        return $this->model->buscarPorId($id);
    }

    /**
     * Remove um recebimento
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
     * Baixa/liquida um recebimento
     */
    public function baixar(int $id, array $dados): array
    {
        // Verifica se existe
        $recebimento = $this->model->buscarPorId($id);
        if (!$recebimento) {
            throw new \Exception('Recebimento não encontrado');
        }

        // Verifica se já está baixado
        if ($recebimento['liquidado'] == 1) {
            throw new \Exception('Recebimento já está baixado/liquidado');
        }

        // Validação da data de liquidação
        if (!isset($dados['data_liquidacao']) || empty($dados['data_liquidacao'])) {
            $dados['data_liquidacao'] = date('Y-m-d');
        }

        // Enriquece
        $usuarioId = $this->obterUsuarioAtual();

        // Baixa
        $this->model->baixar($id, $dados['data_liquidacao'], $usuarioId);

        // Retorna dados atualizados
        return $this->model->buscarPorId($id);
    }

    /**
     * Busca por ID
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->model->buscarPorId($id);
    }

    /**
     * Busca por External ID
     */
    public function buscarPorExternalId(string $externalId): ?array
    {
        return $this->model->buscarPorExternalId($externalId);
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
    public function obterEstatisticas(array $filtros = []): array
    {
        return $this->model->obterEstatisticas($filtros);
    }

    // =============== MÉTODOS PRIVADOS ===============

    /**
     * Valida dados do recebimento
     */
    private function validarDadosRecebimento(array $dados, bool $isUpdate): void
    {
        // Validações apenas para criação
        if (!$isUpdate) {
            // Verifica external_id duplicado
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    throw new \Exception('External ID já cadastrado no sistema');
                }
            }

            // Validação de entidade obrigatória
            if (!isset($dados['entidade']) || !in_array($dados['entidade'], ['C', 'F', 'T'])) {
                throw new \Exception('Entidade inválida. Deve ser C (Cliente), F (Fornecedor) ou T (Transportadora)');
            }

            // Validação de acordo com a entidade
            switch ($dados['entidade']) {
                case 'C': // Cliente
                    if (empty($dados['cliente_id'])) {
                        throw new \Exception('Cliente é obrigatório quando entidade=C');
                    }
                    break;

                case 'F': // Fornecedor
                    if (empty($dados['fornecedor_id'])) {
                        throw new \Exception('Fornecedor é obrigatório quando entidade=F');
                    }
                    break;

                case 'T': // Transportadora
                    if (empty($dados['transportadora_id'])) {
                        throw new \Exception('Transportadora é obrigatória quando entidade=T');
                    }
                    break;
            }

            // Validação de campos obrigatórios
            if (empty($dados['plano_contas_id'])) {
                throw new \Exception('Plano de contas é obrigatório');
            }

            if (empty($dados['centro_custo_id'])) {
                throw new \Exception('Centro de custo é obrigatório');
            }

            if (empty($dados['conta_bancaria_id'])) {
                throw new \Exception('Conta bancária é obrigatória');
            }

            if (empty($dados['forma_pagamento_id'])) {
                throw new \Exception('Forma de pagamento é obrigatória');
            }
        }

        // Validações de valores
        if (isset($dados['valor']) && $dados['valor'] < 0) {
            throw new \Exception('Valor não pode ser negativo');
        }

        if (isset($dados['juros']) && $dados['juros'] < 0) {
            throw new \Exception('Juros não pode ser negativo');
        }

        if (isset($dados['desconto']) && $dados['desconto'] < 0) {
            throw new \Exception('Desconto não pode ser negativo');
        }

        // Validação de data de vencimento
        if (isset($dados['data_vencimento'])) {
            if (!$this->validarData($dados['data_vencimento'])) {
                throw new \Exception('Data de vencimento inválida');
            }
        }

        // Validação de data de liquidação
        if (isset($dados['data_liquidacao']) && !empty($dados['data_liquidacao'])) {
            if (!$this->validarData($dados['data_liquidacao'])) {
                throw new \Exception('Data de liquidação inválida');
            }
        }

        // Validação de data de competência
        if (isset($dados['data_competencia']) && !empty($dados['data_competencia'])) {
            if (!$this->validarData($dados['data_competencia'])) {
                throw new \Exception('Data de competência inválida');
            }
        }
    }

    /**
     * Valida formato de data
     */
    private function validarData(string $data): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $data);
        return $d && $d->format('Y-m-d') === $data;
    }

    /**
     * Verifica se recebimento existe
     */
    private function verificarExistencia(int $id): void
    {
        $recebimento = $this->model->buscarPorId($id);
        if (!$recebimento) {
            throw new \Exception('Recebimento não encontrado');
        }
    }

    /**
     * Obtém ID do usuário atual
     */
    private function obterUsuarioAtual(): ?int
    {
        $usuario = $this->auth->obterUsuarioAutenticado();
        return $usuario['id'] ?? null;
    }
}
