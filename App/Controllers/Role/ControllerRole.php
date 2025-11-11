<?php

namespace App\Controllers\Role;

use App\Models\Colaborador\ModelColaboradorRole;
use App\Models\Colaborador\ModelColaboradorPermission;
use App\Helpers\AuxiliarResposta;
use App\Helpers\AuxiliarValidacao;
use App\Core\BancoDados;
use App\Core\Autenticacao;

/**
 * Controlador para gerenciar roles (funções)
 */
class ControllerRole
{
    private ModelColaboradorRole $model;
    private ModelColaboradorPermission $modelPermission;
    private BancoDados $db;
    private Autenticacao $auth;

    public function __construct()
    {
        $this->model = new ModelColaboradorRole();
        $this->modelPermission = new ModelColaboradorPermission();
        $this->db = BancoDados::obterInstancia();
        $this->auth = new Autenticacao();
    }

    /**
     * Lista todas as roles
     */
    public function listar(): void
    {
        try {
            $filtros = [];

            if (isset($_GET['nivel_id'])) {
                $filtros['nivel_id'] = (int) $_GET['nivel_id'];
            }

            if (isset($_GET['ativo'])) {
                $filtros['ativo'] = (int) $_GET['ativo'];
            }

            if (isset($_GET['busca'])) {
                $filtros['busca'] = $_GET['busca'];
            }

            $roles = $this->model->listar($filtros);

            AuxiliarResposta::sucesso($roles);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Busca uma role por ID
     */
    public function buscar(int $id): void
    {
        try {
            $role = $this->model->buscarPorId($id);

            if (!$role) {
                AuxiliarResposta::naoEncontrado('Role não encontrada');
                return;
            }

            // Inclui as permissões da role
            $role['permissoes'] = $this->obterPermissoesRole($id);

            AuxiliarResposta::sucesso($role);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Obtém as permissões de uma role
     */
    public function obterPermissoes(int $id): void
    {
        try {
            $role = $this->model->buscarPorId($id);

            if (!$role) {
                AuxiliarResposta::naoEncontrado('Role não encontrada');
                return;
            }

            $permissoes = $this->obterPermissoesRole($id);

            AuxiliarResposta::sucesso($permissoes);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Obtém permissões de uma role do banco
     */
    private function obterPermissoesRole(int $roleId): array
    {
        return $this->db->buscarTodos("
            SELECT p.id, p.nome, p.codigo, p.descricao, p.modulo
            FROM colaborador_permissions p
            INNER JOIN colaborador_role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.ativo = 1
            ORDER BY p.modulo, p.nome
        ", [$roleId]);
    }

    /**
     * Cria uma nova role
     */
    public function criar(): void
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => ['obrigatorio', 'string', 'max:100'],
                'codigo' => ['obrigatorio', 'string', 'max:50'],
                'descricao' => ['string'],
                'nivel_id' => ['obrigatorio', 'inteiro'],
                'ativo' => ['inteiro']
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::erroValidacao('Dados inválidos', $erros);
                return;
            }

            // Verifica se o código já existe
            if ($this->model->codigoExiste($dados['codigo'])) {
                AuxiliarResposta::erroValidacao('Código já cadastrado', [
                    'codigo' => 'Este código já está em uso'
                ]);
                return;
            }

            $usuario = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuario['id'] ?? null;
            $id = $this->model->criar($dados, $usuarioId);

            AuxiliarResposta::sucesso([
                'id' => $id,
                'mensagem' => 'Role criada com sucesso'
            ], 201);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Atualiza uma role
     */
    public function atualizar(int $id): void
    {
        try {
            $role = $this->model->buscarPorId($id);

            if (!$role) {
                AuxiliarResposta::naoEncontrado('Role não encontrada');
                return;
            }

            $dados = json_decode(file_get_contents('php://input'), true);

            // Validação
            $erros = AuxiliarValidacao::validar($dados, [
                'nome' => ['string', 'max:100'],
                'codigo' => ['string', 'max:50'],
                'descricao' => ['string'],
                'nivel_id' => ['inteiro'],
                'ativo' => ['inteiro']
            ]);

            if (!empty($erros)) {
                AuxiliarResposta::erroValidacao('Dados inválidos', $erros);
                return;
            }

            // Verifica se o código já existe (excluindo o próprio registro)
            if (isset($dados['codigo']) && $this->model->codigoExiste($dados['codigo'], $id)) {
                AuxiliarResposta::erroValidacao('Código já cadastrado', [
                    'codigo' => 'Este código já está em uso'
                ]);
                return;
            }

            $usuario = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuario['id'] ?? null;
            $this->model->atualizar($id, $dados, $usuarioId);

            AuxiliarResposta::sucesso([
                'mensagem' => 'Role atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Deleta uma role (soft delete)
     */
    public function deletar(int $id): void
    {
        try {
            $role = $this->model->buscarPorId($id);

            if (!$role) {
                AuxiliarResposta::naoEncontrado('Role não encontrada');
                return;
            }

            $usuario = $this->auth->obterUsuarioAutenticado();
            $usuarioId = $usuario['id'] ?? null;
            $sucesso = $this->model->deletar($id, $usuarioId);

            if ($sucesso) {
                AuxiliarResposta::sucesso([
                    'mensagem' => 'Role deletada com sucesso'
                ]);
            } else {
                AuxiliarResposta::erro('Não foi possível deletar a role', 500);
            }
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }

    /**
     * Atribui permissões a uma role
     */
    public function atribuirPermissoes(int $id): void
    {
        try {
            $role = $this->model->buscarPorId($id);

            if (!$role) {
                AuxiliarResposta::naoEncontrado('Role não encontrada');
                return;
            }

            $dados = json_decode(file_get_contents('php://input'), true);

            if (!isset($dados['permissoes']) || !is_array($dados['permissoes'])) {
                AuxiliarResposta::erro('Permissões inválidas', 400);
                return;
            }

            // Remove permissões antigas
            $this->db->executar(
                "DELETE FROM colaborador_role_permissions WHERE role_id = ?",
                [$id]
            );

            // Adiciona novas permissões
            foreach ($dados['permissoes'] as $permissaoId) {
                $this->db->inserir('colaborador_role_permissions', [
                    'role_id' => $id,
                    'permission_id' => (int) $permissaoId,
                    'criado_em' => date('Y-m-d H:i:s')
                ]);
            }

            AuxiliarResposta::sucesso([
                'mensagem' => 'Permissões atualizadas com sucesso',
                'role_id' => $id,
                'total_permissoes' => count($dados['permissoes'])
            ]);
        } catch (\Exception $e) {
            AuxiliarResposta::erroInterno($e->getMessage());
        }
    }
}
