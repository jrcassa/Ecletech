<?php

namespace App\Controllers\Estado;

use App\Controllers\BaseController;

use App\Models\Estado\ModelEstado;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Controller para gerenciar estados
 */
class ControllerEstado extends BaseController
{
    private ModelEstado $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelEstado();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todos os estados
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
            $estados = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $estados,
                $total,
                $paginaAtual,
                $porPagina,
                'Estados listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um estado por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $estado = $this->model->buscarPorId((int) $id);

            if (!$estado) {
                $this->naoEncontrado('Estado não encontrado');
                return;
            }

            $this->sucesso($estado, 'Estado encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo estado
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDadosEstado($dados);

            // Validação dos dados
            $erros = $this->validarDados($dados);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o código já existe
            if ($this->model->codigoExiste($dados['codigo'])) {
                AuxiliarResposta::conflito('Código já cadastrado no sistema');
                return;
            }

            // Verifica se a sigla já existe
            if ($this->model->siglaExiste($dados['sigla'])) {
                AuxiliarResposta::conflito('Sigla já cadastrada no sistema');
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

            // Cria o estado
            $id = $this->model->criar($dados);

            $estado = $this->model->buscarPorId($id);

            $this->criado($estado, 'Estado cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um estado
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se o estado existe
            $estadoExistente = $this->model->buscarPorId((int) $id);
            if (!$estadoExistente) {
                $this->naoEncontrado('Estado não encontrado');
                return;
            }

            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDadosEstado($dados);

            // Validação dos dados (campos opcionais)
            $erros = $this->validarDados($dados, (int) $id);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o código já existe (excluindo o próprio estado)
            if (isset($dados['codigo'])) {
                if ($this->model->codigoExiste($dados['codigo'], (int) $id)) {
                    AuxiliarResposta::conflito('Código já cadastrado em outro estado');
                    return;
                }
            }

            // Verifica se a sigla já existe (excluindo o próprio estado)
            if (isset($dados['sigla'])) {
                if ($this->model->siglaExiste($dados['sigla'], (int) $id)) {
                    AuxiliarResposta::conflito('Sigla já cadastrada em outro estado');
                    return;
                }
            }

            // Verifica se o external_id já existe (excluindo o próprio estado)
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('ID externo já cadastrado em outro estado');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o estado
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar estado', 400);
                return;
            }

            $estado = $this->model->buscarPorId((int) $id);

            $this->sucesso($estado, 'Estado atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um estado (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se o estado existe
            $estado = $this->model->buscarPorId((int) $id);
            if (!$estado) {
                $this->naoEncontrado('Estado não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o estado
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao deletar estado', 400);
                return;
            }

            $this->sucesso(null, 'Estado removido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas de estados
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas de estados obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizarDadosEstado(array $dados): array
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

        if (isset($dados['sigla'])) {
            $dadosSanitizados['sigla'] = strtoupper(AuxiliarSanitizacao::string($dados['sigla']));
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
            $regras['sigla'] = 'obrigatorio|min:2|max:2';
        } else {
            // Validações opcionais para atualização
            if (isset($dados['codigo'])) {
                $regras['codigo'] = 'obrigatorio|min:1|max:20';
            }

            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:2|max:150';
            }

            if (isset($dados['sigla'])) {
                $regras['sigla'] = 'obrigatorio|min:2|max:2';
            }
        }

        // Validação do external_id (sempre opcional)
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $regras['external_id'] = 'max:50';
        }

        return AuxiliarValidacao::validar($dados, $regras);
    }
}
