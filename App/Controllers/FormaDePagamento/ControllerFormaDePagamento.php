<?php

namespace App\Controllers\FormaDePagamento;

use App\Services\FormaDePagamento\ServiceFormaDePagamento;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar formas de pagamento
 */
class ControllerFormaDePagamento
{
    private ServiceFormaDePagamento $service;

    public function __construct()
    {
        $this->service = new ServiceFormaDePagamento();
    }

    /**
     * Lista todas as formas de pagamento
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'conta_bancaria_id' => $_GET['conta_bancaria_id'] ?? null,
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
            $formas = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $formas,
                $total,
                $paginaAtual,
                $porPagina,
                'Formas de pagamento listadas com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma forma de pagamento por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $forma = $this->service->buscarPorId((int) $id);

            if (!$forma) {
                AuxiliarResposta::naoEncontrado('Forma de pagamento não encontrada');
                return;
            }

            AuxiliarResposta::sucesso($forma, 'Forma de pagamento encontrada');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova forma de pagamento
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Validação de formato HTTP
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:200',
                'conta_bancaria_id' => 'obrigatorio|inteiro',
                'maximo_parcelas' => 'obrigatorio|inteiro|min:1',
                'intervalo_parcelas' => 'inteiro',
                'intervalo_primeira_parcela' => 'inteiro'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + criação)
            $forma = $this->service->criar($dados);

            AuxiliarResposta::criado($forma, 'Forma de pagamento cadastrada com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma forma de pagamento
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação de formato HTTP (campos opcionais)
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            if (isset($dados['conta_bancaria_id'])) {
                $regras['conta_bancaria_id'] = 'obrigatorio|inteiro';
            }

            if (isset($dados['maximo_parcelas'])) {
                $regras['maximo_parcelas'] = 'obrigatorio|inteiro|min:1';
            }

            if (isset($dados['intervalo_parcelas'])) {
                $regras['intervalo_parcelas'] = 'inteiro';
            }

            if (isset($dados['intervalo_primeira_parcela'])) {
                $regras['intervalo_primeira_parcela'] = 'inteiro';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização)
            $forma = $this->service->atualizar((int) $id, $dados);

            AuxiliarResposta::sucesso($forma, 'Forma de pagamento atualizada com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma forma de pagamento (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Delega para o Service
            $this->service->deletar((int) $id);

            AuxiliarResposta::sucesso(null, 'Forma de pagamento removida com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas das formas de pagamento
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();
            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
