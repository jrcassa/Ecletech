<?php

namespace App\Controllers\TipoContato;

use App\Controllers\BaseController;

use App\Models\TipoContato\ModelTipoContato;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Controller para gerenciar tipos de contatos
 */
class ControllerTipoContato extends BaseController
{
    private ModelTipoContato $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelTipoContato();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todos os tipos de contatos
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
            $tipos = $this->model->listar($filtros);
            $total = $this->model->contar(array_diff_key($filtros, array_flip(['limite', 'offset', 'ordenacao', 'direcao'])));

            $this->paginado(
                $tipos,
                $total,
                $paginaAtual,
                $porPagina,
                'Tipos de contatos listados com sucesso'
            );
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um tipo de contato por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            $tipo = $this->model->buscarPorId((int) $id);

            if (!$tipo) {
                $this->naoEncontrado('Tipo de contato não encontrado');
                return;
            }

            $this->sucesso($tipo, 'Tipo de contato encontrado');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo tipo de contato
     */
    public function criar(): void
    {
        try {
            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDadosTipoContato($dados);

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

            // Cria o tipo de contato
            $id = $this->model->criar($dados);

            $tipo = $this->model->buscarPorId($id);

            $this->criado($tipo, 'Tipo de contato cadastrado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um tipo de contato
     */
    public function atualizar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se o tipo de contato existe
            $tipoExistente = $this->model->buscarPorId((int) $id);
            if (!$tipoExistente) {
                $this->naoEncontrado('Tipo de contato não encontrado');
                return;
            }

            $dados = $this->obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDadosTipoContato($dados);

            // Validação dos dados (campos opcionais)
            $erros = $this->validarDados($dados, (int) $id);

            if (!empty($erros)) {
                $this->validacao($erros);
                return;
            }

            // Verifica se o nome já existe (excluindo o próprio tipo)
            if (isset($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Nome já cadastrado em outro tipo de contato');
                    return;
                }
            }

            // Verifica se o external_id já existe (excluindo o próprio tipo)
            if (isset($dados['external_id']) && !empty($dados['external_id'])) {
                if ($this->model->externalIdExiste($dados['external_id'], (int) $id)) {
                    AuxiliarResposta::conflito('ID externo já cadastrado em outro tipo de contato');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o tipo de contato
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao atualizar tipo de contato', 400);
                return;
            }

            $tipo = $this->model->buscarPorId((int) $id);

            $this->sucesso($tipo, 'Tipo de contato atualizado com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um tipo de contato (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!$this->validarId($id)) { return; }

            // Verifica se o tipo de contato existe
            $tipo = $this->model->buscarPorId((int) $id);
            if (!$tipo) {
                $this->naoEncontrado('Tipo de contato não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o tipo de contato
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                $this->erro('Erro ao deletar tipo de contato', 400);
                return;
            }

            $this->sucesso(null, 'Tipo de contato removido com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas de tipos de contatos
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            $this->sucesso($estatisticas, 'Estatísticas de tipos de contatos obtidas com sucesso');
        } catch (\Exception $e) {
            $this->erro($e->getMessage(), 400);
        }
    }

    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizarDadosTipoContato(array $dados): array
    {
        $dadosSanitizados = [];

        if (isset($dados['external_id'])) {
            $dadosSanitizados['external_id'] = AuxiliarSanitizacao::string($dados['external_id']);
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
            $regras['nome'] = 'obrigatorio|min:3|max:100';
        } else {
            // Validações opcionais para atualização
            if (isset($dados['nome'])) {
                $regras['nome'] = 'obrigatorio|min:3|max:100';
            }
        }

        // Validação do external_id (sempre opcional)
        if (isset($dados['external_id']) && !empty($dados['external_id'])) {
            $regras['external_id'] = 'max:50';
        }

        return AuxiliarValidacao::validar($dados, $regras);
    }
}
