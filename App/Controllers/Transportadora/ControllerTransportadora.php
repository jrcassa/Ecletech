<?php

namespace App\Controllers\Transportadora;

use App\Controllers\BaseController;

use App\Services\Transportadora\ServiceTransportadora;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar transportadoras
 */
class ControllerTransportadora extends BaseController
{
    private ServiceTransportadora $service;

    public function __construct()
    {
        $this->service = new ServiceTransportadora();
    }

    /**
     * Lista todas as transportadoras
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'tipo_pessoa' => $_GET['tipo_pessoa'] ?? null,
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
            $transportadoras = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $transportadoras,
                $total,
                $paginaAtual,
                $porPagina,
                'Transportadoras listadas com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma transportadora por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $transportadora = $this->service->buscarComRelacionamentos((int) $id);

            if (!$transportadora) {
                $this->naoEncontrado('Transportadora não encontrada');
                return;
            }

            $this->sucesso($transportadora, 'Transportadora encontrada');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova transportadora
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'tipo_pessoa' => 'obrigatorio|em:PF,PJ',
                'nome' => 'obrigatorio|min:3|max:200'
            ]);

            // Validações condicionais por tipo de pessoa
            if (isset($dados['tipo_pessoa'])) {
                if ($dados['tipo_pessoa'] === 'PJ') {
                    // Para PJ, CNPJ é obrigatório
                    $errosCondicional = AuxiliarValidacao::validar($dados, [
                        'cnpj' => 'obrigatorio|cnpj'
                    ]);
                    $erros = array_merge($erros, $errosCondicional);
                } elseif ($dados['tipo_pessoa'] === 'PF') {
                    // Para PF, CPF é obrigatório
                    $errosCondicional = AuxiliarValidacao::validar($dados, [
                        'cpf' => 'obrigatorio|cpf'
                    ]);
                    $erros = array_merge($erros, $errosCondicional);
                }
            }

            // Validações opcionais
            if (isset($dados['email']) && !empty($dados['email'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['email' => 'email']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (isset($dados['data_nascimento']) && !empty($dados['data_nascimento'])) {
                $errosOpcionais = AuxiliarValidacao::validar($dados, ['data_nascimento' => 'data']);
                $erros = array_merge($erros, $errosOpcionais);
            }

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + criação + transação)
            $transportadora = $this->service->criarCompleto($dados);

            $this->criado($transportadora, 'Transportadora cadastrada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma transportadora
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $dados = $this->obterDados();

            // Validação de formato HTTP (campos opcionais)
            $regras = [];

            if (isset($dados['tipo_pessoa'])) {
                $regras['tipo_pessoa'] = 'obrigatorio|em:PF,PJ';
            }

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            if (isset($dados['cnpj']) && !empty($dados['cnpj'])) {
                $regras['cnpj'] = 'cnpj';
            }

            if (isset($dados['cpf']) && !empty($dados['cpf'])) {
                $regras['cpf'] = 'cpf';
            }

            if (isset($dados['email']) && !empty($dados['email'])) {
                $regras['email'] = 'email';
            }

            if (isset($dados['data_nascimento']) && !empty($dados['data_nascimento'])) {
                $regras['data_nascimento'] = 'data';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização + transação)
            $transportadora = $this->service->atualizarCompleto((int) $id, $dados);

            $this->sucesso($transportadora, 'Transportadora atualizada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma transportadora (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Delega para o Service
            $this->service->deletar((int) $id);

            $this->sucesso(null, 'Transportadora removida com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas das transportadoras
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas das transportadoras obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
