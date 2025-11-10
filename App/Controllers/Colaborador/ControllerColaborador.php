<?php

namespace App\Controllers\Colaborador;

use App\Core\Autenticacao;
use App\Core\GerenciadorUsuario;
use App\Models\Colaborador\ModelColaborador;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;

/**
 * Controlador para gerenciar colaboradores
 */
class ControllerColaborador
{
    private ModelColaborador $model;
    private GerenciadorUsuario $gerenciadorUsuario;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelColaborador();
        $this->gerenciadorUsuario = new GerenciadorUsuario();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todos os colaboradores
     */
    public function listar(): void
    {
        try {
            $filtros = [];

            // Obtém parâmetros de query
            if (isset($_GET['ativo'])) {
                $filtros['ativo'] = (int) $_GET['ativo'];
            }

            if (isset($_GET['nivel_id'])) {
                $filtros['nivel_id'] = (int) $_GET['nivel_id'];
            }

            if (isset($_GET['busca'])) {
                $filtros['busca'] = $_GET['busca'];
            }

            // Paginação
            $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
            $porPagina = isset($_GET['por_pagina']) ? (int) $_GET['por_pagina'] : 20;

            $filtros['limite'] = $porPagina;
            $filtros['offset'] = ($pagina - 1) * $porPagina;

            $colaboradores = $this->model->listar($filtros);
            $total = $this->model->contar($filtros);

            // Remove senhas dos resultados
            foreach ($colaboradores as &$admin) {
                unset($admin['senha']);
            }

            AuxiliarResposta::paginado($colaboradores, $total, $pagina, $porPagina);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca um colaborador por ID
     */
    public function buscar(string $id): void
    {
        try {
            $colaborador = $this->model->buscarPorId((int) $id);

            if (!$colaborador) {
                AuxiliarResposta::naoEncontrado('Colaborador não encontrado');
                return;
            }

            // Remove a senha
            unset($colaborador['senha']);

            AuxiliarResposta::sucesso($colaborador, 'Colaborador encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Cria um novo colaborador
     */
    public function criar(): void
    {
        $dados = AuxiliarResposta::obterDados();

        // Validação
        $erros = AuxiliarValidacao::validar($dados, [
            'nome' => 'obrigatorio|min:3',
            'email' => 'obrigatorio|email',
            'senha' => 'obrigatorio|min:8',
            'nivel_id' => 'obrigatorio|inteiro'
        ]);

        if (!empty($erros)) {
            AuxiliarResposta::validacao($erros);
            return;
        }

        try {
            // Verifica se o email já existe
            if ($this->model->emailExiste($dados['email'])) {
                AuxiliarResposta::conflito('Email já cadastrado');
                return;
            }

            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();
            $dados['usuario_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria o colaborador
            $id = $this->gerenciadorUsuario->criar($dados);

            $colaborador = $this->model->buscarPorId($id);
            unset($colaborador['senha']);

            AuxiliarResposta::criado($colaborador, 'Colaborador criado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um colaborador
     */
    public function atualizar(string $id): void
    {
        $dados = AuxiliarResposta::obterDados();
        $colaboradorId = (int) $id;

        try {
            $colaborador = $this->model->buscarPorId($colaboradorId);

            if (!$colaborador) {
                AuxiliarResposta::naoEncontrado('Colaborador não encontrado');
                return;
            }

            // Verifica se o email já existe (para outro usuário)
            if (isset($dados['email']) && $this->model->emailExiste($dados['email'], $colaboradorId)) {
                AuxiliarResposta::conflito('Email já cadastrado');
                return;
            }

            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();

            // Atualiza o colaborador
            $this->gerenciadorUsuario->atualizar($colaboradorId, $dados);

            $colaboradorAtualizado = $this->model->buscarPorId($colaboradorId);
            unset($colaboradorAtualizado['senha']);

            AuxiliarResposta::sucesso($colaboradorAtualizado, 'Colaborador atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um colaborador
     */
    public function deletar(string $id): void
    {
        $colaboradorId = (int) $id;

        try {
            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();

            // Não permite deletar a si mesmo
            if ($usuarioAutenticado && $usuarioAutenticado['id'] == $colaboradorId) {
                AuxiliarResposta::erro('Não é possível deletar seu próprio usuário', 400);
                return;
            }

            $resultado = $this->gerenciadorUsuario->deletar($colaboradorId);

            if ($resultado) {
                AuxiliarResposta::sucesso(null, 'Colaborador deletado com sucesso');
            } else {
                AuxiliarResposta::naoEncontrado('Colaborador não encontrado');
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }
}
