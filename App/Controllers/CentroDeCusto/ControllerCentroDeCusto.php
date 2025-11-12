<?php

namespace App\Controllers\CentroDeCusto;

use App\Models\CentroDeCusto\ModelCentroDeCusto;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar centros de custo
 */
class ControllerCentroDeCusto
{
    private ModelCentroDeCusto $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelCentroDeCusto();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todos os centros de custo
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
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
            $centros = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $centros,
                $total,
                $paginaAtual,
                $porPagina,
                'Centros de custo listados com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um centro de custo por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $centro = $this->model->buscarPorId((int) $id);

            if (!$centro) {
                AuxiliarResposta::naoEncontrado('Centro de custo não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($centro, 'Centro de custo encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo centro de custo
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:200'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica duplicatas
            if ($this->model->nomeExiste($dados['nome'])) {
                AuxiliarResposta::conflito('Já existe um centro de custo com este nome');
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

            // Cria o centro de custo
            $id = $this->model->criar($dados);

            $centro = $this->model->buscarPorId($id);

            AuxiliarResposta::criado($centro, 'Centro de custo cadastrado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um centro de custo
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o centro de custo existe
            $centroExistente = $this->model->buscarPorId((int) $id);
            if (!$centroExistente) {
                AuxiliarResposta::naoEncontrado('Centro de custo não encontrado');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados (campos opcionais)
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica duplicatas (excluindo o próprio centro)
            if (isset($dados['nome']) && !empty($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Já existe outro centro de custo com este nome');
                    return;
                }
            }

            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outro centro de custo');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o centro de custo
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar centro de custo', 400);
                return;
            }

            $centro = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($centro, 'Centro de custo atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um centro de custo (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o centro de custo existe
            $centro = $this->model->buscarPorId((int) $id);
            if (!$centro) {
                AuxiliarResposta::naoEncontrado('Centro de custo não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o centro de custo (soft delete)
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar centro de custo', 400);
                return;
            }

            AuxiliarResposta::sucesso(null, 'Centro de custo removido com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos centros de custo
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
