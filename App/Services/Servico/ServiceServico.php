<?php

namespace App\Services\Servico;

use App\Models\Servico\ModelServico;
use Exception;

/**
 * Service para orquestrar operações de serviços
 */
class ServiceServico
{
    private ModelServico $model;

    public function __construct()
    {
        $this->model = new ModelServico();
    }

    /**
     * Cria novo serviço com validações de negócio
     */
    public function criar(array $dados): int
    {
        // Validação de código duplicado
        if ($this->model->codigoExiste($dados['codigo'])) {
            throw new Exception('Já existe um serviço com este código');
        }

        // Normalizar valor_venda
        if (isset($dados['valor_venda'])) {
            $dados['valor_venda'] = $this->normalizarValor($dados['valor_venda']);
        }

        return $this->model->criar($dados);
    }

    /**
     * Atualiza serviço com validações de negócio
     */
    public function atualizar(int $id, array $dados): bool
    {
        // Verifica se serviço existe
        $servico = $this->model->buscarPorId($id);
        if (!$servico) {
            throw new Exception('Serviço não encontrado');
        }

        // Validação de código duplicado (se estiver alterando)
        if (isset($dados['codigo']) && $dados['codigo'] !== $servico['codigo']) {
            if ($this->model->codigoExiste($dados['codigo'], $id)) {
                throw new Exception('Já existe um serviço com este código');
            }
        }

        // Normalizar valor_venda
        if (isset($dados['valor_venda'])) {
            $dados['valor_venda'] = $this->normalizarValor($dados['valor_venda']);
        }

        return $this->model->atualizar($id, $dados);
    }

    /**
     * Deleta serviço com validações
     */
    public function deletar(int $id): bool
    {
        // Verifica se serviço existe
        $servico = $this->model->buscarPorId($id);
        if (!$servico) {
            throw new Exception('Serviço não encontrado');
        }

        // Aqui você pode adicionar validações adicionais
        // Por exemplo, verificar se o serviço está sendo usado em alguma ordem de serviço

        return $this->model->deletar($id);
    }

    /**
     * Importa ou atualiza serviço de sistema externo
     */
    public function importarOuAtualizar(array $dados): array
    {
        // Validações básicas
        if (empty($dados['external_id'])) {
            throw new Exception('external_id é obrigatório para importação');
        }

        if (empty($dados['codigo'])) {
            throw new Exception('código é obrigatório para importação');
        }

        // Normalizar valor_venda
        if (isset($dados['valor_venda'])) {
            $dados['valor_venda'] = $this->normalizarValor($dados['valor_venda']);
        }

        // Verifica se já existe por external_id
        $servicoExistente = $this->model->buscarPorExternalId($dados['external_id']);

        if ($servicoExistente) {
            // Atualiza serviço existente
            $this->model->atualizar($servicoExistente['id'], $dados);
            return [
                'acao' => 'atualizado',
                'id' => $servicoExistente['id'],
                'external_id' => $dados['external_id']
            ];
        } else {
            // Cria novo serviço
            $id = $this->model->criar($dados);
            return [
                'acao' => 'criado',
                'id' => $id,
                'external_id' => $dados['external_id']
            ];
        }
    }

    /**
     * Normaliza valor (converte string para decimal)
     */
    private function normalizarValor($valor): float
    {
        if (is_string($valor)) {
            // Remove pontos de milhar e substitui vírgula por ponto
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        }

        return (float) $valor;
    }

    /**
     * Valida dados do serviço
     */
    public function validarDados(array $dados, bool $isUpdate = false): array
    {
        $erros = [];

        // Código obrigatório
        if (!$isUpdate && empty($dados['codigo'])) {
            $erros['codigo'] = 'Código é obrigatório';
        }

        // Nome obrigatório
        if (!$isUpdate && empty($dados['nome'])) {
            $erros['nome'] = 'Nome é obrigatório';
        }

        // Validar valor_venda
        if (isset($dados['valor_venda'])) {
            $valor = $this->normalizarValor($dados['valor_venda']);
            if ($valor < 0) {
                $erros['valor_venda'] = 'Valor de venda não pode ser negativo';
            }
        }

        return $erros;
    }
}
