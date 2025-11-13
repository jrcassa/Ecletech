<?php

namespace App\Controllers\SituacaoVenda;

use App\Controllers\BaseController;

use App\Models\SituacaoVenda\ModelSituacaoVenda;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Controller para gerenciar situações de vendas
 */
class ControllerSituacaoVenda extends BaseController
{
    private ModelSituacaoVenda $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelSituacaoVenda();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todas as situações de vendas
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
            $situacoes = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $situacoes,
                $total,
                $paginaAtual,
                $porPagina,
                'Situações de vendas listadas com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma situação de venda por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $situacao = $this->model->buscarPorId((int) $id);

            if (!$situacao) {
                $this->naoEncontrado('Situação de venda não encontrada');
                return;
            }

            $this->sucesso($situacao, 'Situação de venda encontrada');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova situação de venda
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDadosSituacaoVenda($dados);

            // Validação dos dados
            $erros = $this->validarDados($dados);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o nome já existe
            if ($this->model->nomeExiste($dados['nome'])) {
                AuxiliarResposta::conflito('Nome já cadastrado no sistema');
                return;
            }

            // Verifica se o external_id já existe (se fornecido)
            if (!empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'])) {
                    AuxiliarResposta::conflito('ID externo já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria a situação de venda
            $id = $this->model->criar($dados);

            $situacao = $this->model->buscarPorId($id);

            $this->criado($situacao, 'Situação de venda cadastrada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma situação de venda
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se a situação de venda existe
            $situacaoExistente = $this->model->buscarPorId((int) $id);
            if (!$situacaoExistente) {
                $this->naoEncontrado('Situação de venda não encontrada');
                return;
            }

            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDadosSituacaoVenda($dados);

            // Validação dos dados (campos opcionais)
            $erros = $this->validarDados($dados, (int) $id);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o nome já existe (excluindo a própria situação)
            if (isset($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Nome já cadastrado em outra situação de venda');
                    return;
                }
            }

            // Verifica se o external_id já existe (excluindo a própria situação)
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('ID externo já cadastrado em outra situação de venda');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza a situação de venda
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar situação de venda', 400);
                return;
            }

            $situacao = $this->model->buscarPorId((int) $id);

            $this->sucesso($situacao, 'Situação de venda atualizada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma situação de venda (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se a situação de venda existe
            $situacao = $this->model->buscarPorId((int) $id);
            if (!$situacao) {
                $this->naoEncontrado('Situação de venda não encontrada');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta a situação de venda
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao deletar situação de venda', 400);
                return;
            }

            $this->sucesso(null, 'Situação de venda removida com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas de situações de vendas
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas de situações de vendas obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizarDadosSituacaoVenda(array $dados): array
    {
        $dadosSanitizados = [];

        if (isset($dados['external_id'])) {
            $dadosSanitizados['external_id'] = AuxiliarSanitizacao::string($dados['external_id']);
        }

        if (isset($dados['nome'])) {
            $dadosSanitizados['nome'] = AuxiliarSanitizacao::string($dados['nome']);
        }

        if (isset($dados['cor'])) {
            // Remove espaços e converte para maiúsculas
            $dadosSanitizados['cor'] = strtoupper(trim($dados['cor']));
        }

        if (isset($dados['ativo'])) {
            $dadosSanitizados['ativo'] = (bool) $dados['ativo'];
        }

        return $dadosSanitizados;
    }

    /**
     * Valida os dados de entrada
     */
    private function validarDados(array $dados, ?int $idAtual = null): array
    {
        $regras = [];

        // Validações obrigatórias para criação
        if ($idAtual === null) {
            $regras['nome'] = 'obrigatorio|min:3|max:100';
            $regras['cor'] = 'obrigatorio';
        } else {
            // Validações opcionais para atualização
            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:100';
            }

            if (isset($dados['cor'])) {
                $regras['cor'] = 'obrigatorio';
            }
        }

        // Validação do external_id (sempre opcional)
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $regras['external_id'] = 'max:50';
        }

        $erros = AuxiliarValidacao::validar($dados, $regras);

        // Validação adicional da cor hexadecimal
        if (isset($dados['cor']) && !empty($dados['cor'])) {
            if (!$this->validarCorHex($dados['cor'])) {
                $erros['cor'] = 'A cor deve estar no formato hexadecimal (#RRGGBB)';
            }
        }

        return $erros;
    }

    /**
     * Valida se a cor está no formato hexadecimal (#RRGGBB)
     */
    private function validarCorHex(string $cor): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $cor) === 1;
    }
}
