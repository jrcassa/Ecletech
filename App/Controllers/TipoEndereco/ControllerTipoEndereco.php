<?php

namespace App\Controllers\TipoEndereco;

use App\Models\TipoEndereco\ModelTipoEndereco;
use App\Core\Autenticacao;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Helpers\AuxiliarSanitizacao;

/**
 * Controller para gerenciar tipos de endereços
 */
class ControllerTipoEndereco
{
    private ModelTipoEndereco $model;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelTipoEndereco();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todos os tipos de endereços
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

            AuxiliarResposta::paginado(
                $tipos,
                $total,
                $paginaAtual,
                $porPagina,
                'Tipos de endereços listados com sucesso'
            );
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Busca um tipo de endereço por ID
     */
    public function buscar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            $tipo = $this->model->buscarPorId((int) $id);

            if (!$tipo) {
                AuxiliarResposta::naoEncontrado('Tipo de endereço não encontrado');
                return;
            }

            AuxiliarResposta::sucesso($tipo, 'Tipo de endereço encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Cria um novo tipo de endereço
     */
    public function criar(): void
    {
        try {
            $dados = AuxiliarResposta::obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDados($dados);

            // Validação dos dados
            $erros = $this->validarDados($dados);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica se o nome já existe
            if ($this->model->nomeExiste($dados['nome'])) {
                AuxiliarResposta::conflito('Nome já cadastrado no sistema');
                return;
            }

            // Verifica se o id_externo já existe (se fornecido)
            if (!empty($dados['id_externo'])) {
                if ($this->model->idExternoExiste($dados['id_externo'])) {
                    AuxiliarResposta::conflito('ID externo já cadastrado no sistema');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria o tipo de endereço
            $id = $this->model->criar($dados);

            $tipo = $this->model->buscarPorId($id);

            AuxiliarResposta::criado($tipo, 'Tipo de endereço cadastrado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um tipo de endereço
     */
    public function atualizar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o tipo de endereço existe
            $tipoExistente = $this->model->buscarPorId((int) $id);
            if (!$tipoExistente) {
                AuxiliarResposta::naoEncontrado('Tipo de endereço não encontrado');
                return;
            }

            $dados = AuxiliarResposta::obterDados();

            // Sanitização dos dados
            $dados = $this->sanitizarDados($dados);

            // Validação dos dados (campos opcionais)
            $erros = $this->validarDados($dados, (int) $id);

            if (!empty($erros)) {
                AuxiliarResposta::validacao($erros);
                return;
            }

            // Verifica se o nome já existe (excluindo o próprio tipo)
            if (isset($dados['nome'])) {
                if ($this->model->nomeExiste($dados['nome'], (int) $id)) {
                    AuxiliarResposta::conflito('Nome já cadastrado em outro tipo de endereço');
                    return;
                }
            }

            // Verifica se o id_externo já existe (excluindo o próprio tipo)
            if (isset($dados['id_externo']) && !empty($dados['id_externo'])) {
                if ($this->model->idExternoExiste($dados['id_externo'], (int) $id)) {
                    AuxiliarResposta::conflito('ID externo já cadastrado em outro tipo de endereço');
                    return;
                }
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Atualiza o tipo de endereço
            $resultado = $this->model->atualizar((int) $id, $dados, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao atualizar tipo de endereço', 400);
                return;
            }

            $tipo = $this->model->buscarPorId((int) $id);

            AuxiliarResposta::sucesso($tipo, 'Tipo de endereço atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um tipo de endereço (soft delete)
     */
    public function deletar(string $id): void
    {
        try {
            if (!AuxiliarValidacao::inteiro($id)) {
                AuxiliarResposta::erro('ID inválido', 400);
                return;
            }

            // Verifica se o tipo de endereço existe
            $tipo = $this->model->buscarPorId((int) $id);
            if (!$tipo) {
                AuxiliarResposta::naoEncontrado('Tipo de endereço não encontrado');
                return;
            }

            // Obtém usuário autenticado para auditoria
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuarioAutenticado['id'] ?? null;

            // Deleta o tipo de endereço
            $resultado = $this->model->deletar((int) $id, $usuarioId);

            if (!$resultado) {
                AuxiliarResposta::erro('Erro ao deletar tipo de endereço', 400);
                return;
            }

            AuxiliarResposta::sucesso(null, 'Tipo de endereço removido com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Obtém estatísticas de tipos de endereços
     */
    public function obterEstatisticas(): void
    {
        try {
            $estatisticas = $this->model->obterEstatisticas();

            AuxiliarResposta::sucesso($estatisticas, 'Estatísticas de tipos de endereços obtidas com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Sanitiza os dados de entrada
     */
    private function sanitizarDados(array $dados): array
    {
        $dadosSanitizados = [];

        if (isset($dados['id_externo'])) {
            $dadosSanitizados['id_externo'] = AuxiliarSanitizacao::string($dados['id_externo']);
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

        // Validação do id_externo (sempre opcional)
        if (isset($dados['id_externo']) && !empty($dados['id_externo'])) {
            $regras['id_externo'] = 'max:50';
        }

        return AuxiliarValidacao::validar($dados, $regras);
    }
}
