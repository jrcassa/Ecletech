<?php

namespace App\Controllers\GruposProdutos;

use App\Controllers\BaseController;

use App\Models\GruposProdutos\ModelGruposProdutos;
use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar grupos de produtos
 */
class ControllerGruposProdutos extends BaseController
{
    private ModelGruposProdutos $model;
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelGruposProdutos();
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Lista todos os grupos de produtos
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
            $gruposProdutos = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $gruposProdutos,
                $total,
                $paginaAtual,
                $porPagina,
                'Grupos de produtos listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um grupo de produtos por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $grupoProdutos = $this->model->buscarPorId((int) $id);

            if (!$grupoProdutos) {
                $this->naoEncontrado('Grupo de produtos não encontrado');
                return;
            }

            $this->sucesso($grupoProdutos, 'Grupo de produtos encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo grupo de produtos
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:200'
            ]);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o nome já existe
            if ($this->model->nomeExiste($dados['nome'])) {
                AuxiliarResposta::conflito('Já existe um grupo de produtos com este nome');
                return;
            }

            // Verifica external_id duplicado
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('External ID já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria o grupo de produtos
            $id = $this->model->criar($dados);

            $grupoProdutos = $this->model->buscarPorId($id);

            $this->criado($grupoProdutos, 'Grupo de produtos cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um grupo de produtos
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se o grupo existe
            $grupoProdutosExistente = $this->model->buscarPorId((int) $id);
            if (!$grupoProdutosExistente) {
                $this->naoEncontrado('Grupo de produtos não encontrado');
                return;
            }

            $dados = $this->obterDados();

            // Validação dos dados (campos opcionais)
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:200';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o nome já existe em outro grupo
            if (isset($dados['nome']) && !empty($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Já existe outro grupo de produtos com este nome');
                    return;
                }
            }

            // Verifica external_id duplicado
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outro grupo');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o grupo de produtos
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar grupo de produtos', 400);
                return;
            }

            $grupoProdutos = $this->model->buscarPorId((int) $id);

            $this->sucesso($grupoProdutos, 'Grupo de produtos atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um grupo de produtos (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se o grupo existe
            $grupoProdutos = $this->model->buscarPorId((int) $id);
            if (!$grupoProdutos) {
                $this->naoEncontrado('Grupo de produtos não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o grupo de produtos (soft delete)
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao deletar grupo de produtos', 400);
                return;
            }

            $this->sucesso(null, 'Grupo de produtos removido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos grupos de produtos
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas dos grupos de produtos obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }
}
