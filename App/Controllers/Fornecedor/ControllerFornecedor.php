<?php

namespace App\Controllers\Fornecedor;

use App\Services\Fornecedor\ServiceFornecedor;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar fornecedores
 */
class ControllerFornecedor
{
    private ServiceFornecedor $service;

    public function __construct()
    {
        $this->service = new ServiceFornecedor();
    }

    /**
     * Lista todos os fornecedores
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
            $fornecedores = $this->service->listar($filtros);
            $total = $this->service->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $fornecedores,
                $total,
                $paginaAtual,
                $porPagina,
                'Fornecedores listados com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um fornecedor por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $fornecedor = $this->service->buscarComRelacionamentos((int) $id);

            if (!$fornecedor) {
                AuxiliarResposta::naoEncontrado('Fornecedor não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($fornecedor, 'Fornecedor encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo fornecedor
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

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
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + criação + transação)
            $fornecedor = $this->service->criarCompleto($dados);

            AuxiliarResposta::criado($fornecedor, 'Fornecedor cadastrado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um fornecedor
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
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Delega para o Service (validações de negócio + atualização + transação)
            $fornecedor = $this->service->atualizarCompleto((int) $id, $dados);

            AuxiliarResposta::sucesso($fornecedor, 'Fornecedor atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um fornecedor (soft delete)
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

            AuxiliarResposta::sucesso(null, 'Fornecedor removido com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos fornecedores
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->service->obterEstatisticas();

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas dos fornecedores obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
