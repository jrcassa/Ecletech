<?php

namespace App\Controllers\Administrador;

use App\Core\Autenticacao;
use App\Core\GerenciadorUsuario;
use App\Models\Colaborador\ModelColaborador;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Middleware\MiddlewareAcl;

/**
 * Controlador para gerenciar administradores
 * Todos os métodos verificam permissões ACL antes de executar
 */
class ControllerAdministrador
{
    private ModelColaborador $model;
    private GerenciadorUsuario $gerenciadorUsuario;
    private Autenticacao $auth;
    private MiddlewareAcl $acl;

    public function __construct()
    {
        $this->model = new ModelColaborador();
        $this->gerenciadorUsuario = new GerenciadorUsuario();
        $this->auth = new Autenticacao();
        $this->acl = new MiddlewareAcl();
    }

    /**
     * Lista todos os administradores
     * Requer permissão: colaboradores.visualizar
     */
    public function listar(): void
    {
        try {
            // Verifica permissão ACL
            if (!$this->acl->verificarPermissao('colaboradores.visualizar')) {
                AuxiliarResposta::proibido('Você não tem permissão para visualizar administradores');
                return;
            }

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

            $administradores = $this->model->listar($filtros);
            $total = $this->model->contar($filtros);

            // Remove senhas dos resultados
            foreach ($administradores as &$admin) {
                unset($admin['senha']);
            }

            AuxiliarResposta::paginado($administradores, $total, $pagina, $porPagina);
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Busca um administrador por ID
     * Requer permissão: colaboradores.visualizar
     */
    public function buscar(string $id): void
    {
        try {
            // Verifica permissão ACL
            if (!$this->acl->verificarPermissao('colaboradores.visualizar')) {
                AuxiliarResposta::proibido('Você não tem permissão para visualizar administradores');
                return;
            }

            $administrador = $this->model->buscarPorId((int) $id);

            if (!$administrador) {
                AuxiliarResposta::naoEncontrado('Administrador não encontrado');
                return;
            }

            // Remove a senha
            unset($administrador['senha']);

            AuxiliarResposta::sucesso($administrador, 'Administrador encontrado');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Cria um novo administrador
     * Requer permissão: colaboradores.criar
     */
    public function criar(): void
    {
        // Verifica permissão ACL
        if (!$this->acl->verificarPermissao('colaboradores.criar')) {
            AuxiliarResposta::proibido('Você não tem permissão para criar administradores');
            return;
        }

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
            $dados['colaborador_id'] = $usuarioAutenticado['id'] ?? null;

            // Cria o administrador
            $id = $this->gerenciadorUsuario->criar($dados);

            $administrador = $this->model->buscarPorId($id);
            unset($administrador['senha']);

            AuxiliarResposta::criado($administrador, 'Administrador criado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Atualiza um administrador
     * Requer permissão: colaboradores.editar
     */
    public function atualizar(string $id): void
    {
        // Verifica permissão ACL
        if (!$this->acl->verificarPermissao('colaboradores.editar')) {
            AuxiliarResposta::proibido('Você não tem permissão para editar administradores');
            return;
        }

        $dados = AuxiliarResposta::obterDados();
        $administradorId = (int) $id;

        try {
            $administrador = $this->model->buscarPorId($administradorId);

            if (!$administrador) {
                AuxiliarResposta::naoEncontrado('Administrador não encontrado');
                return;
            }

            // Verifica se o email já existe (para outro usuário)
            if (isset($dados['email']) && $this->model->emailExiste($dados['email'], $administradorId)) {
                AuxiliarResposta::conflito('Email já cadastrado');
                return;
            }

            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();

            // Atualiza o administrador
            $this->gerenciadorUsuario->atualizar($administradorId, $dados);

            $administradorAtualizado = $this->model->buscarPorId($administradorId);
            unset($administradorAtualizado['senha']);

            AuxiliarResposta::sucesso($administradorAtualizado, 'Administrador atualizado com sucesso');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 400);
        }
    }

    /**
     * Deleta um administrador (soft delete)
     * Requer permissão: colaboradores.deletar
     */
    public function deletar(string $id): void
    {
        // Verifica permissão ACL
        if (!$this->acl->verificarPermissao('colaboradores.deletar')) {
            AuxiliarResposta::proibido('Você não tem permissão para deletar administradores');
            return;
        }

        $administradorId = (int) $id;

        try {
            // Obtém usuário autenticado
            $usuarioAutenticado = $this->auth->obterUsuarioAutenticado();

            // Não permite deletar a si mesmo
            if ($usuarioAutenticado && $usuarioAutenticado['id'] == $administradorId) {
                AuxiliarResposta::erro('Não é possível deletar seu próprio usuário', 400);
                return;
            }

            $resultado = $this->gerenciadorUsuario->deletar($administradorId);

            if ($resultado) {
                AuxiliarResposta::sucesso(null, 'Administrador deletado com sucesso');
            } else {
                AuxiliarResposta::naoEncontrado('Administrador não encontrado');
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }

    /**
     * Verifica as permissões do usuário atual para o módulo de administradores
     * Retorna quais operações o usuário pode realizar
     */
    public function verificarPermissoes(): void
    {
        try {
            $permissoes = [
                'visualizar' => $this->acl->verificarPermissao('colaboradores.visualizar'),
                'criar' => $this->acl->verificarPermissao('colaboradores.criar'),
                'editar' => $this->acl->verificarPermissao('colaboradores.editar'),
                'deletar' => $this->acl->verificarPermissao('colaboradores.deletar')
            ];

            AuxiliarResposta::sucesso($permissoes, 'Permissões verificadas');
        } catch (\Exception $e) {
            AuxiliarResposta::erro($e->getMessage(), 500);
        }
    }
}
