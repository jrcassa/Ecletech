<?php

namespace App\Controllers\FormaDePagamento;

use App\Models\FormaDePagamento\ModelFormaDePagamento;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar formas de pagamento
 */
class ControllerFormaDePagamento
{
    private ModelFormaDePagamento $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelFormaDePagamento();
        $this->auth = new Autenticacao();
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

            // Busca dados
            $formas = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

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

            $forma = $this->model->buscarPorId((int) $id);

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

            // Validação básica
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

            // Verifica duplicatas
            if ($this->model->nomeExiste($dados['nome'])) {
                AuxiliarResposta::conflito('Já existe uma forma de pagamento com este nome');
                return;
            }

            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('External ID já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria a forma de pagamento
            $id = $this->model->criar($dados);

            $forma = $this->model->buscarPorId($id);

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

            // Verifica se a forma de pagamento existe
            $formaExistente = $this->model->buscarPorId((int) $id);
            if (!$formaExistente) {
                AuxiliarResposta::naoEncontrado('Forma de pagamento não encontrada');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados (campos opcionais)
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

            // Verifica duplicatas (excluindo o próprio registro)
            if (isset($dados['nome']) && !empty($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Já existe outra forma de pagamento com este nome');
                    return;
                }
            }

            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outra forma de pagamento');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza a forma de pagamento
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar forma de pagamento', 400);
                return;
            }

            $forma = $this->model->buscarPorId((int) $id);

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

            // Verifica se a forma de pagamento existe
            $forma = $this->model->buscarPorId((int) $id);
            if (!$forma) {
                AuxiliarResposta::naoEncontrado('Forma de pagamento não encontrada');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta a forma de pagamento (soft delete)
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar forma de pagamento', 400);
                return;
            }

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
            $estatisticas = $this->model->obterEstatisticas();
            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
