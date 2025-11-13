<?php

namespace App\Controllers\PlanoDeContas;

use App\Controllers\BaseController;

use App\Models\PlanoDeContas\ModelPlanoDeContas;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar plano de contas
 */
class ControllerPlanoDeContas extends BaseController
{
    private ModelPlanoDeContas $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelPlanoDeContas();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todas as contas
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'tipo' => $_GET['tipo'] ?? null,
                'conta_mae_id' => $_GET['conta_mae_id'] ?? null,
                'nivel' => $_GET['nivel'] ?? null,
                'busca' => $_GET['busca'] ?? null,
                'ordenacao' => $_GET['ordenacao'] ?? 'classificacao',
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

            // Busca dados
            $contas = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $contas,
                $total,
                $paginaAtual,
                $porPagina,
                'Plano de contas listado com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma conta por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $conta = $this->model->buscarPorId((int) $id);

            if (!$conta) {
                $this->naoEncontrado('Conta não encontrada');
                return;
            }

            $this->sucesso($conta, 'Conta encontrada');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova conta
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:200',
                'tipo' => 'obrigatorio|em:D,C'
            ]);

            // Validações opcionais
            if (isset($dados['classificacao']) && !empty($dados['classificacao'])) {
                // Valida formato da classificação (ex: 1.1.1)
                if (!preg_match('/^\d+(\.\d+)*$/', $dados['classificacao'])) {
                    $erros['classificacao'] = 'A classificação deve estar no formato correto (ex: 1.1.1)';
                }
            }

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica duplicatas
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('External ID já cadastrado no sistema');
                    return;
                }
            }

            if (isset($dados['classificacao']) && !empty($dados['classificacao'])) {
                if ($this->model->classificacaoExiste($dados['classificacao'])) {
                    AuxiliarResposta::conflito('Classificação já cadastrada no sistema');
                    return;
                }
            }

            // Verifica se a conta mãe existe
            if (isset($dados['conta_mae_id']) && !empty($dados['conta_mae_id'])) {
                $contaMae = $this->model->buscarPorId((int) $dados['conta_mae_id']);
                if (!$contaMae) {
                    $this->erro('Conta mãe não encontrada', 400);
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria a conta
            $id = $this->model->criar($dados);

            $conta = $this->model->buscarPorId($id);

            $this->criado($conta, 'Conta cadastrada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma conta
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se a conta existe
            $contaExistente = $this->model->buscarPorId((int) $id);
            if (!$contaExistente) {
                $this->naoEncontrado('Conta não encontrada');
                return;
            }

            $dados = $this->obterDados();

            // Validação dos dados (campos opcionais)
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            if (isset($dados['tipo'])) {
                $regras['tipo'] = 'obrigatorio|em:D,C';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            // Validações opcionais
            if (isset($dados['classificacao']) && !empty($dados['classificacao'])) {
                if (!preg_match('/^\d+(\.\d+)*$/', $dados['classificacao'])) {
                    $erros['classificacao'] = 'A classificação deve estar no formato correto (ex: 1.1.1)';
                }
            }

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica duplicatas (excluindo a própria conta)
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outra conta');
                    return;
                }
            }

            if (isset($dados['classificacao']) && !empty($dados['classificacao'])) {
                if ($this->model->classificacaoExiste($dados['classificacao'], (int) $id)) {
                    AuxiliarResposta::conflito('Classificação já cadastrada em outra conta');
                    return;
                }
            }

            // Verifica se a conta mãe existe
            if (isset($dados['conta_mae_id']) && !empty($dados['conta_mae_id'])) {
                // Não pode ser conta mãe de si mesma
                if ((int) $dados['conta_mae_id'] === (int) $id) {
                    $this->erro('Uma conta não pode ser conta mãe de si mesma', 400);
                    return;
                }

                $contaMae = $this->model->buscarPorId((int) $dados['conta_mae_id']);
                if (!$contaMae) {
                    $this->erro('Conta mãe não encontrada', 400);
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza a conta
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar conta', 400);
                return;
            }

            $conta = $this->model->buscarPorId((int) $id);

            $this->sucesso($conta, 'Conta atualizada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma conta (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se a conta existe
            $conta = $this->model->buscarPorId((int) $id);
            if (!$conta) {
                $this->naoEncontrado('Conta não encontrada');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta a conta (soft delete)
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao deletar conta', 400);
                return;
            }

            $this->sucesso(null, 'Conta removida com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Lista contas principais (sem conta mãe)
     */
    public function listarPrincipais(): void
    {
        try {
            $contas = $this->model->listarContasPrincipais();
            $this->sucesso($contas, 'Contas principais listadas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Lista contas filhas de uma conta mãe
     */
    public function listarFilhas(string $contaMaeId): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($contaMaeId)) {
                $this->erro('ID da conta mãe inválido', 400);
                return;
            }

            $contas = $this->model->listarContasFilhas((int) $contaMaeId);
            $this->sucesso($contas, 'Contas filhas listadas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas das contas
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();
            $this->sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém a árvore hierárquica de contas
     */
    public function obterArvore(): void
    {
        try {
            $arvore = $this->model->obterArvore();
            $this->sucesso($arvore, 'Árvore de contas obtida com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
