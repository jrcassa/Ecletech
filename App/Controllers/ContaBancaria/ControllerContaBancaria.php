<?php

namespace App\Controllers\ContaBancaria;

use App\Models\ContaBancaria\ModelContaBancaria;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar contas bancárias
 */
class ControllerContaBancaria
{
    private ModelContaBancaria $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelContaBancaria();
        $this->auth = new Autenticacao();
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

            // Busca dados
            $contasBancarias = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $contasBancarias,
                $total,
                $paginaAtual,
                $porPagina,
                'Contas bancárias listadas com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma conta bancária por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $contaBancaria = $this->model->buscarPorId((int) $id);

            if (!$contaBancaria) {
                AuxiliarResposta::naoEncontrado('Conta bancária não encontrada');
                return;
            }

            AuxiliarResposta::sucesso($contaBancaria, 'Conta bancária encontrada');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova conta bancária
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

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
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica duplicatas
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('External ID já cadastrado no sistema');
                    return;
                }
            }

            if (isset($dados['nome']) && !empty($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'])) {
                    AuxiliarResposta::conflito('Nome de conta já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria a conta bancária
            $id = $this->model->criar($dados);

            $contaBancaria = $this->model->buscarPorId($id);

            AuxiliarResposta::criado($contaBancaria, 'Conta bancária cadastrada com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma conta bancária
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se a conta bancária existe
            $contaExistente = $this->model->buscarPorId((int) $id);
            if (!$contaExistente) {
                AuxiliarResposta::naoEncontrado('Conta bancária não encontrada');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados (campos opcionais)
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
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica duplicatas (excluindo a própria conta)
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outra conta');
                    return;
                }
            }

            if (isset($dados['nome']) && !empty($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Nome de conta já cadastrado em outra conta');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza a conta bancária
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar conta bancária', 400);
                return;
            }

            $contaBancaria = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($contaBancaria, 'Conta bancária atualizada com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma conta bancária (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se a conta bancária existe
            $contaBancaria = $this->model->buscarPorId((int) $id);
            if (!$contaBancaria) {
                AuxiliarResposta::naoEncontrado('Conta bancária não encontrada');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta a conta bancária (soft delete)
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar conta bancária', 400);
                return;
            }

            AuxiliarResposta::sucesso(null, 'Conta bancária removida com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas das contas bancárias
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas das contas bancárias obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
