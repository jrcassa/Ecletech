<?php

namespace App\Controllers\Pagamento;

use App\Controllers\BaseController;
use App\Services\Pagamento\ServicePagamento;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar pagamentos (contas a pagar e receber)
 */
class ControllerPagamento extends BaseController
{
    private ServicePagamento $service;

    public function __construct()
    {
        $this->service = new ServicePagamento();
    }

    /**
     * Lista todos os pagamentos
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
                'plano_contas_id' => $_GET['plano_contas_id'] ?? null,
                'centro_custo_id' => $_GET['centro_custo_id'] ?? null,
                'forma_pagamento_id' => $_GET['forma_pagamento_id'] ?? null,
                'data_vencimento_inicio' => $_GET['data_vencimento_inicio'] ?? null,
                'data_vencimento_fim' => $_GET['data_vencimento_fim'] ?? null,
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
            $pagamentos = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $pagamentos,
                $total,
                $paginaAtual,
                $porPagina,
                'Pagamentos listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um pagamento por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $pagamento = $this->service->buscarPorId((int) $id);

            if (!$pagamento) {
                $this->naoEncontrado('Pagamento não encontrado');
                return;
            }

            $this->sucesso($pagamento, 'Pagamento encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo pagamento
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
                'data_vencimento' => 'obrigatorio|data'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Validação de entidade específica
            switch ($dados['entidade'] ?? '') {
                case 'C':
                    if (empty($dados['cliente_id'])) {
                        $this->validacao(['cliente_id' => 'Cliente é obrigatório']);
                        return;
                    }
                    break;
                case 'F':
                    if (empty($dados['fornecedor_id'])) {
                        $this->validacao(['fornecedor_id' => 'Fornecedor é obrigatório']);
                        return;
                    }
                    break;
                case 'T':
                    if (empty($dados['transportadora_id'])) {
                        $this->validacao(['transportadora_id' => 'Transportadora é obrigatória']);
                        return;
                    }
                    break;
                case 'U':
                    if (empty($dados['funcionario_id'])) {
                        $this->validacao(['funcionario_id' => 'Funcionário é obrigatório']);
                        return;
                    }
                    break;
                default:
                    $this->validacao(['entidade' => 'Entidade inválida']);
                    return;
            }

            // Delega para o Service (validações de negócio + criação)
            $pagamento = $this->service->criar($dados);

            $this->criado($pagamento, 'Pagamento cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um pagamento
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

            if (isset($dados['taxa_banco'])) {
                $regras['taxa_banco'] = 'decimal';
            }

            if (isset($dados['taxa_operadora'])) {
                $regras['taxa_operadora'] = 'decimal';
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

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização)
            $pagamento = $this->service->atualizar((int) $id, $dados);

            $this->sucesso($pagamento, 'Pagamento atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um pagamento (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Delega para o Service
            $this->service->deletar((int) $id);

            $this->sucesso(null, 'Pagamento removido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Liquida um pagamento
     */
    public function liquidar(string $id): void
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
            $pagamento = $this->service->liquidar((int) $id, $dados);

            $this->sucesso($pagamento, 'Pagamento liquidado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos pagamentos
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();
            $this->sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
