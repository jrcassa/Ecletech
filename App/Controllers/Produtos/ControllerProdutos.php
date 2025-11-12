<?php

namespace App\Controllers\Produtos;

use App\Models\Produtos\ModelProdutos;
use App\Core\Autenticacao;
use App\Core\BancoDados;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controller para gerenciar produtos (estrutura refatorada - 2 tabelas)
 */
class ControllerProdutos
{
    private ModelProdutos $model;
    private Autenticacao $auth;
    private BancoDados $db;

    public function __construct()
    {
        $this->model = new ModelProdutos();
        $this->auth = new Autenticacao();
        $this->db = BancoDados::obterInstancia();
    }

    /**
     * Lista todos os produtos
     */
    public function listar(): void
    {
        try {
            // Obtém parâmetros de filtro e paginação
            $filtros = [
                'ativo' => $_GET['ativo'] ?? null,
                'grupo_id' => $_GET['grupo_id'] ?? null,
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
            $produtos = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            AuxiliarResposta::paginado(
                $produtos,
                $total,
                $paginaAtual,
                $porPagina,
                'Produtos listados com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um produto por ID com relacionamentos
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $produto = $this->model->buscarPorId((int) $id);

            if (!$produto) {
                AuxiliarResposta::naoEncontrado('Produto não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($produto, 'Produto encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo produto
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Validação básica
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => 'obrigatorio|min:3|max:255'
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica external_id duplicado
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('External ID já cadastrado no sistema');
                    return;
                }
            }

            // Verifica código interno duplicado
            if (isset($dados['codigo_interno']) && !empty($dados['codigo_interno'])) {
                if ($this->model->codigoInternoExiste($dados['codigo_interno'])) {
                    AuxiliarResposta::conflito('Código interno já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria o produto (agora tudo numa única tabela + fornecedores)
            $produtoId = $this->model->criar($dados);

            // Busca o produto completo
            $produto = $this->model->buscarPorId($produtoId);

            AuxiliarResposta::criado($produto, 'Produto cadastrado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um produto
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o produto existe
            $produtoExistente = $this->model->buscarPorId((int) $id);
            if (!$produtoExistente) {
                AuxiliarResposta::naoEncontrado('Produto não encontrado');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Validação dos dados
            $regras = [];

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:255';
            }

            $erros = AuxiliarValidacao::validar($dados, $regras);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica external_id duplicado
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('External ID já cadastrado em outro produto');
                    return;
                }
            }

            // Verifica código interno duplicado
            if (isset($dados['codigo_interno']) && !empty($dados['codigo_interno'])) {
                if ($this->model->codigoInternoExiste($dados['codigo_interno'], (int) $id)) {
                    AuxiliarResposta::conflito('Código interno já cadastrado em outro produto');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o produto
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                throw new \Exception('Erro ao atualizar produto');
            }

            // Busca o produto completo
            $produto = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($produto, 'Produto atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um produto (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o produto existe
            $produto = $this->model->buscarPorId((int) $id);
            if (!$produto) {
                AuxiliarResposta::naoEncontrado('Produto não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o produto (soft delete)
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar produto', 400);
                return;
            }

            AuxiliarResposta::sucesso(null, 'Produto removido com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas dos produtos
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas dos produtos obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }
}
