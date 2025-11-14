<?php

namespace App\Controllers\Recebimento;

use App\Controllers\BaseController;
use App\Services\Recebimento\ServiceRecebimento;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar recebimentos (contas a receber)
 */
class ControllerRecebimento extends BaseController
{
    private ServiceRecebimento $service;

    public function __construct()
    {
        $this->service = new ServiceRecebimento();
    }

    /**
     * Lista todos os recebimentos
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'liquidado' => $_GET['liquidado'] ?? null,
                'entidade' => $_GET['entidade'] ?? null,
                'cliente_id' => $_GET['cliente_id'] ?? null,
                'fornecedor_id' => $_GET['fornecedor_id'] ?? null,
                'transportadora_id' => $_GET['transportadora_id'] ?? null,
                'plano_contas_id' => $_GET['plano_contas_id'] ?? null,
                'centro_custo_id' => $_GET['centro_custo_id'] ?? null,
                'conta_bancaria_id' => $_GET['conta_bancaria_id'] ?? null,
                'forma_pagamento_id' => $_GET['forma_pagamento_id'] ?? null,
                'loja_id' => $_GET['loja_id'] ?? null,
                'data_vencimento_inicio' => $_GET['data_vencimento_inicio'] ?? null,
                'data_vencimento_fim' => $_GET['data_vencimento_fim'] ?? null,
                'data_liquidacao_inicio' => $_GET['data_liquidacao_inicio'] ?? null,
                'data_liquidacao_fim' => $_GET['data_liquidacao_fim'] ?? null,
                'data_competencia_inicio' => $_GET['data_competencia_inicio'] ?? null,
                'data_competencia_fim' => $_GET['data_competencia_fim'] ?? null,
                'busca' => $_GET['busca'] ?? null,
                'ordenacao' => $_GET['ordenacao'] ?? 'data_vencimento',
                'direcao' => $_GET['direcao'] ?? 'DESC'
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
            $recebimentos = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $recebimentos,
                $total,
                $paginaAtual,
                $porPagina,
                'Recebimentos listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um recebimento por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $recebimento = $this->service->buscarPorId((int) $id);

            if (!$recebimento) {
                $this->naoEncontrado('Recebimento não encontrado');
                return;
            }

            $this->sucesso($recebimento, 'Recebimento encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo recebimento
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação de formato HTTP
            $erros = AuxiliarValidacao::validar($dados, [
                'descricao' => 'obrigatorio|min:3|max:500',
                'valor' => 'obrigatorio|decimal',
                'entidade' => 'obrigatorio',
                'data_vencimento' => 'obrigatorio|data',
                'plano_contas_id' => 'obrigatorio|inteiro',
                'centro_custo_id' => 'obrigatorio|inteiro',
                'conta_bancaria_id' => 'obrigatorio|inteiro',
                'forma_pagamento_id' => 'obrigatorio|inteiro'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Recebimentos são sempre de Clientes (entidade C)
            if (($dados['entidade'] ?? '') !== 'C') {
                $this->validacao(['entidade' => 'Entidade deve ser C (Cliente) - Recebimentos são apenas contas a receber']);
                return;
            }

            if (empty($dados['cliente_id'])) {
                $this->validacao(['cliente_id' => 'Cliente é obrigatório']);
                return;
            }

            // Delega para o Service (validações de negócio + criação)
            $recebimento = $this->service->criar($dados);

            $this->criado($recebimento, 'Recebimento cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um recebimento
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            // Validação de formato HTTP (campos opcionais)
            $regras = [];

            if (isset($dados['descricao'])) {
                $regras['descricao'] = 'obrigatorio|min:3|max:500';
            }

            if (isset($dados['valor'])) {
                $regras['valor'] = 'obrigatorio|decimal';
            }

            if (isset($dados['juros'])) {
                $regras['juros'] = 'decimal';
            }

            if (isset($dados['desconto'])) {
                $regras['desconto'] = 'decimal';
            }

            if (isset($dados['data_vencimento'])) {
                $regras['data_vencimento'] = 'obrigatorio|data';
            }

            if (isset($dados['data_liquidacao'])) {
                $regras['data_liquidacao'] = 'data';
            }

            if (isset($dados['data_competencia'])) {
                $regras['data_competencia'] = 'data';
            }

            if (isset($dados['plano_contas_id'])) {
                $regras['plano_contas_id'] = 'obrigatorio|inteiro';
            }

            if (isset($dados['centro_custo_id'])) {
                $regras['centro_custo_id'] = 'obrigatorio|inteiro';
            }

            if (isset($dados['conta_bancaria_id'])) {
                $regras['conta_bancaria_id'] = 'obrigatorio|inteiro';
            }

            if (isset($dados['forma_pagamento_id'])) {
                $regras['forma_pagamento_id'] = 'obrigatorio|inteiro';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização)
            $recebimento = $this->service->atualizar((int) $id, $dados);

            $this->sucesso($recebimento, 'Recebimento atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um recebimento (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Delega para o Service
            $this->service->deletar((int) $id);

            $this->sucesso(null, 'Recebimento removido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Baixa/liquida um recebimento
     */
    public function baixar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            // Validação de data de liquidação
            if (isset($dados['data_liquidacao'])) {
                $erros = AuxiliarValidacao::validar($dados, [
                    'data_liquidacao' => 'data'
                ]);

                if (!empty($erros)) {
                    $this->validacao($erros);
                    return;
                }
            }

            // Delega para o Service
            $recebimento = $this->service->baixar((int) $id, $dados);

            $this->sucesso($recebimento, 'Recebimento baixado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos recebimentos
     */
    public function obterEstatisticas(): void
    {
        try {
            // Obtém filtros opcionais
            $filtros = [
                'entidade' => $_GET['entidade'] ?? null,
                'cliente_id' => $_GET['cliente_id'] ?? null,
                'fornecedor_id' => $_GET['fornecedor_id'] ?? null,
                'transportadora_id' => $_GET['transportadora_id'] ?? null,
                'loja_id' => $_GET['loja_id'] ?? null
            ];

            // Remove filtros vazios
            $filtros = array_filter($filtros, fn($valor) => $valor !== null && $valor !== '');

            $estatisticas = $this->service->obterEstatisticas($filtros);
            $this->sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
