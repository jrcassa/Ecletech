<?php

namespace App\Controllers\Cidade;

use App\Controllers\BaseController;

use App\Models\Cidade\ModelCidade;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Controller para gerenciar cidades
 */
class ControllerCidade extends BaseController
{
    private ModelCidade $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelCidade();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todas as cidades
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
            $cidades = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $cidades,
                $total,
                $paginaAtual,
                $porPagina,
                'Cidades listadas com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca uma cidade por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $cidade = $this->model->buscarPorId((int) $id);

            if (!$cidade) {
                $this->naoEncontrado('Cidade não encontrada');
                return;
            }

            $this->sucesso($cidade, 'Cidade encontrada');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria uma nova cidade
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDados($dados);

            // Validação dos dados
            $erros = $this->validarDados($dados);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o código IBGE já existe
            if ($this->model->codigoExiste($dados['codigo'])) {
                AuxiliarResposta::conflito('Código IBGE já cadastrado no sistema');
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

            // Cria a cidade
            $id = $this->model->criar($dados);

            $cidade = $this->model->buscarPorId($id);

            $this->criado($cidade, 'Cidade cadastrada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza uma cidade
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se a cidade existe
            $cidadeExistente = $this->model->buscarPorId((int) $id);
            if (!$cidadeExistente) {
                $this->naoEncontrado('Cidade não encontrada');
                return;
            }

            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDados($dados);

            // Validação dos dados (campos opcionais)
            $erros = $this->validarDados($dados, (int) $id);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o código IBGE já existe (excluindo a própria cidade)
            if (isset($dados['codigo'])) {
                if ($this->model->codigoExiste($dados['codigo'], (int) $id)) {
                    AuxiliarResposta::conflito('Código IBGE já cadastrado em outra cidade');
                    return;
                }
            }

            // Verifica se o external_id já existe (excluindo a própria cidade)
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('ID externo já cadastrado em outra cidade');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza a cidade
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar cidade', 400);
                return;
            }

            $cidade = $this->model->buscarPorId((int) $id);

            $this->sucesso($cidade, 'Cidade atualizada com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta uma cidade (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se a cidade existe
            $cidade = $this->model->buscarPorId((int) $id);
            if (!$cidade) {
                $this->naoEncontrado('Cidade não encontrada');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta a cidade
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao deletar cidade', 400);
                return;
            }

            $this->sucesso(null, 'Cidade removida com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas de cidades
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas de cidades obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizarDados(array $dados): array
    {
        $dadosSanitizados = [];

        if (isset($dados['external_id'])) {
            $dadosSanitizados['external_id'] = AuxiliarSanitizacao::string($dados['external_id']);
        }

        if (isset($dados['codigo'])) {
            $dadosSanitizados['codigo'] = AuxiliarSanitizacao::string($dados['codigo']);
        }

        if (isset($dados['nome'])) {
            $dadosSanitizados['nome'] = AuxiliarSanitizacao::string($dados['nome']);
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
            $regras['codigo'] = 'obrigatorio|min:1|max:20';
            $regras['nome'] = 'obrigatorio|min:2|max:150';
        } else {
            // Validações opcionais para atualização
            if (isset($dados['codigo'])) {
                $regras['codigo'] = 'obrigatorio|min:1|max:20';
            }

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:2|max:150';
            }
        }

        // Validação do external_id (sempre opcional)
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $regras['external_id'] = 'max:50';
        }

        return AuxiliarValidacao::validar($dados, $regras);
    }
}
