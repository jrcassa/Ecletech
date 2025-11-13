<?php

namespace App\Controllers\ContaBancaria;

use App\Controllers\BaseController;

use App\Services\ContaBancaria\ServiceContaBancaria;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar contas bancárias
 */
class ControllerContaBancaria extends BaseController
{
    private ServiceContaBancaria $service;

    public function __construct()
    {
        $this->service = new ServiceContaBancaria();
    }

    /**
     * Lista todas as contas bancárias
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'tipo_conta' => $_GET['tipo_conta'] ?? null,
                'banco_codigo' => $_GET['banco_codigo'] ?? null,
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
            $contasBancarias = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $contasBancarias,
                $total,
                $paginaAtual,
                $porPagina,
                'Contas bancárias listadas com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma conta bancária por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $contaBancaria = $this->service->buscarPorId((int) $id);

            if (!$contaBancaria) {
                $this->naoEncontrado('Conta bancária não encontrada');
                return;
            }

            $this->sucesso($contaBancaria, 'Conta bancária encontrada');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova conta bancária
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:200'
            ]);

            // Validações opcionais
            if (isset($dados['tipo_conta']) && !empty($dados['tipo_conta'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, [
                    'tipo_conta' => 'em:corrente,poupanca,investimento,outro'
                ]);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['saldo_inicial']) && !empty($dados['saldo_inicial'])) {
                if (!is_numeric($dados['saldo_inicial'])) {
                    $erros['saldo_inicial'] = 'O saldo inicial deve ser um valor numérico';
                }
            }

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + criação)
            $contaBancaria = $this->service->criar($dados);

            $this->criado($contaBancaria, 'Conta bancária cadastrada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma conta bancária
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            // Validação de formato HTTP (campos opcionais)
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            if (isset($dados['tipo_conta']) && !empty($dados['tipo_conta'])) {
                $regras['tipo_conta'] = 'em:corrente,poupanca,investimento,outro';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (isset($dados['saldo_inicial']) && !empty($dados['saldo_inicial'])) {
                if (!is_numeric($dados['saldo_inicial'])) {
                    $erros['saldo_inicial'] = 'O saldo inicial deve ser um valor numérico';
                }
            }

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização)
            $contaBancaria = $this->service->atualizar((int) $id, $dados);

            $this->sucesso($contaBancaria, 'Conta bancária atualizada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma conta bancária (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Delega para o Service
            $this->service->deletar((int) $id);

            $this->sucesso(null, 'Conta bancária removida com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas das contas bancárias
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas das contas bancárias obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
